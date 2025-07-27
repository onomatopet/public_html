<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ModificationRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'entity_type',
        'entity_id',
        'modification_type',
        'requested_by_id',
        'approved_by_id',
        'status',
        'original_values',
        'new_values',
        'changes_summary',
        'validation_data',
        'impact_analysis',
        'risk_level',
        'reason',
        'notes',
        'rejection_reason',
        'approved_at',
        'executed_at',
        'expires_at'
    ];

    protected $casts = [
        'original_values' => 'array',
        'new_values' => 'array',
        'changes_summary' => 'array',
        'validation_data' => 'array',
        'impact_analysis' => 'array',
        'approved_at' => 'datetime',
        'executed_at' => 'datetime',
        'expires_at' => 'datetime'
    ];

    // Types d'entités
    const ENTITY_DISTRIBUTEUR = 'distributeur';
    const ENTITY_ACHAT = 'achat';
    const ENTITY_BONUS = 'bonus';
    const ENTITY_LEVEL_CURRENT = 'level_current';

    // Types de modifications
    const MOD_CHANGE_PARENT = 'change_parent';
    const MOD_MANUAL_GRADE = 'manual_grade';
    const MOD_ADJUST_CUMUL = 'adjust_cumul';
    const MOD_MODIFY_BONUS = 'modify_bonus';
    const MOD_REASSIGN_CHILDREN = 'reassign_children';
    const MOD_MERGE_ACCOUNTS = 'merge_accounts';

    // Statuts
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_EXECUTED = 'executed';
    const STATUS_CANCELLED = 'cancelled';

    // Niveaux de risque
    const RISK_LOW = 'low';
    const RISK_MEDIUM = 'medium';
    const RISK_HIGH = 'high';
    const RISK_CRITICAL = 'critical';

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            // Définir l'expiration par défaut (7 jours)
            if (!$model->expires_at) {
                $model->expires_at = now()->addDays(7);
            }

            // Calculer le niveau de risque si non défini
            if (!$model->risk_level) {
                $model->risk_level = $model->calculateRiskLevel();
            }
        });
    }

    /**
     * Relations
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    /**
     * Obtenir l'entité concernée
     */
    public function getEntity()
    {
        switch ($this->entity_type) {
            case self::ENTITY_DISTRIBUTEUR:
                return Distributeur::find($this->entity_id);
            case self::ENTITY_ACHAT:
                return Achat::find($this->entity_id);
            case self::ENTITY_BONUS:
                return Bonus::find($this->entity_id);
            case self::ENTITY_LEVEL_CURRENT:
                return LevelCurrent::find($this->entity_id);
            default:
                return null;
        }
    }

    /**
     * Scopes
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING)
                    ->where('expires_at', '>', now());
    }

    public function scopeHighRisk($query)
    {
        return $query->whereIn('risk_level', [self::RISK_HIGH, self::RISK_CRITICAL]);
    }

    public function scopeExpiringSoon($query)
    {
        return $query->where('status', self::STATUS_PENDING)
                    ->whereBetween('expires_at', [now(), now()->addDay()]);
    }

    /**
     * Vérifications d'état
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING && !$this->isExpired();
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function canBeApproved(): bool
    {
        return $this->isPending() && $this->getEntity() !== null;
    }

    public function canBeExecuted(): bool
    {
        return $this->status === self::STATUS_APPROVED && !$this->isExecuted();
    }

    public function isExecuted(): bool
    {
        return $this->status === self::STATUS_EXECUTED;
    }

    public function requiresHighLevelApproval(): bool
    {
        return in_array($this->risk_level, [self::RISK_HIGH, self::RISK_CRITICAL]);
    }

    /**
     * Actions
     */
    public function approve(int $approverId, ?string $note = null): bool
    {
        if (!$this->canBeApproved()) {
            return false;
        }

        $this->update([
            'status' => self::STATUS_APPROVED,
            'approved_by_id' => $approverId,
            'approved_at' => now(),
            'notes' => $note ? $this->notes . "\n[Approbation] " . $note : $this->notes
        ]);

        Log::info("Modification approuvée", [
            'request_id' => $this->id,
            'type' => $this->modification_type,
            'entity' => $this->entity_type . '#' . $this->entity_id,
            'approved_by' => $approverId
        ]);

        return true;
    }

    public function reject(int $rejectorId, string $reason): bool
    {
        if (!$this->isPending()) {
            return false;
        }

        $this->update([
            'status' => self::STATUS_REJECTED,
            'approved_by_id' => $rejectorId,
            'rejection_reason' => $reason,
            'approved_at' => now()
        ]);

        Log::info("Modification rejetée", [
            'request_id' => $this->id,
            'type' => $this->modification_type,
            'rejected_by' => $rejectorId,
            'reason' => $reason
        ]);

        return true;
    }

    public function markAsExecuted(array $executionDetails = []): bool
    {
        if (!$this->canBeExecuted()) {
            return false;
        }

        $this->update([
            'status' => self::STATUS_EXECUTED,
            'executed_at' => now(),
            'notes' => $this->notes . "\n[Exécution] " . json_encode($executionDetails)
        ]);

        return true;
    }

    /**
     * Calcul du niveau de risque
     */
    public function calculateRiskLevel(): string
    {
        // Modifications critiques
        $criticalTypes = [
            self::MOD_MERGE_ACCOUNTS,
            self::MOD_REASSIGN_CHILDREN
        ];

        if (in_array($this->modification_type, $criticalTypes)) {
            return self::RISK_CRITICAL;
        }

        // Modifications à haut risque
        $highRiskTypes = [
            self::MOD_CHANGE_PARENT,
            self::MOD_MANUAL_GRADE
        ];

        if (in_array($this->modification_type, $highRiskTypes)) {
            // Vérifier l'ampleur du changement
            if ($this->modification_type === self::MOD_MANUAL_GRADE) {
                $oldGrade = $this->original_values['etoiles_id'] ?? 0;
                $newGrade = $this->new_values['etoiles_id'] ?? 0;
                
                if (abs($newGrade - $oldGrade) > 2) {
                    return self::RISK_CRITICAL;
                }
            }
            
            return self::RISK_HIGH;
        }

        // Modifications à risque moyen
        if ($this->modification_type === self::MOD_ADJUST_CUMUL) {
            $impact = $this->impact_analysis['affected_distributors'] ?? 0;
            if ($impact > 10) {
                return self::RISK_HIGH;
            }
        }

        return self::RISK_MEDIUM;
    }

    /**
     * Labels et formatage
     */
    public function getModificationTypeLabel(): string
    {
        $labels = [
            self::MOD_CHANGE_PARENT => 'Changement de parent',
            self::MOD_MANUAL_GRADE => 'Modification manuelle de grade',
            self::MOD_ADJUST_CUMUL => 'Ajustement de cumuls',
            self::MOD_MODIFY_BONUS => 'Modification de bonus',
            self::MOD_REASSIGN_CHILDREN => 'Réassignation d\'enfants',
            self::MOD_MERGE_ACCOUNTS => 'Fusion de comptes'
        ];

        return $labels[$this->modification_type] ?? $this->modification_type;
    }

    public function getRiskLevelLabel(): string
    {
        $labels = [
            self::RISK_LOW => 'Faible',
            self::RISK_MEDIUM => 'Moyen',
            self::RISK_HIGH => 'Élevé',
            self::RISK_CRITICAL => 'Critique'
        ];

        return $labels[$this->risk_level] ?? $this->risk_level;
    }

    public function getRiskLevelColor(): string
    {
        $colors = [
            self::RISK_LOW => 'green',
            self::RISK_MEDIUM => 'yellow',
            self::RISK_HIGH => 'orange',
            self::RISK_CRITICAL => 'red'
        ];

        return $colors[$this->risk_level] ?? 'gray';
    }

    /**
     * Helpers pour créer des demandes spécifiques
     */
    public static function createParentChangeRequest(
        Distributeur $distributeur,
        Distributeur $newParent,
        string $reason,
        int $requesterId
    ): self {
        $originalValues = [
            'id_distrib_parent' => $distributeur->id_distrib_parent,
            'parent_name' => optional($distributeur->parent)->full_name
        ];

        $newValues = [
            'id_distrib_parent' => $newParent->id,
            'parent_name' => $newParent->full_name
        ];

        $changesSummary = [
            'distributeur' => $distributeur->full_name,
            'ancien_parent' => $originalValues['parent_name'] ?? 'Aucun',
            'nouveau_parent' => $newValues['parent_name']
        ];

        return self::create([
            'entity_type' => self::ENTITY_DISTRIBUTEUR,
            'entity_id' => $distributeur->id,
            'modification_type' => self::MOD_CHANGE_PARENT,
            'requested_by_id' => $requesterId,
            'original_values' => $originalValues,
            'new_values' => $newValues,
            'changes_summary' => $changesSummary,
            'reason' => $reason,
            'risk_level' => self::RISK_HIGH
        ]);
    }

    public static function createGradeChangeRequest(
        Distributeur $distributeur,
        int $newGrade,
        string $reason,
        int $requesterId,
        array $justification = []
    ): self {
        $originalValues = [
            'etoiles_id' => $distributeur->etoiles_id,
            'grade_name' => $distributeur->grade_name ?? "Grade {$distributeur->etoiles_id}"
        ];

        $newValues = [
            'etoiles_id' => $newGrade,
            'grade_name' => "Grade {$newGrade}"
        ];

        $changesSummary = [
            'distributeur' => $distributeur->full_name,
            'ancien_grade' => $originalValues['grade_name'],
            'nouveau_grade' => $newValues['grade_name'],
            'difference' => $newGrade - $distributeur->etoiles_id
        ];

        $riskLevel = abs($newGrade - $distributeur->etoiles_id) > 2 
            ? self::RISK_CRITICAL 
            : self::RISK_HIGH;

        return self::create([
            'entity_type' => self::ENTITY_DISTRIBUTEUR,
            'entity_id' => $distributeur->id,
            'modification_type' => self::MOD_MANUAL_GRADE,
            'requested_by_id' => $requesterId,
            'original_values' => $originalValues,
            'new_values' => $newValues,
            'changes_summary' => $changesSummary,
            'validation_data' => $justification,
            'reason' => $reason,
            'risk_level' => $riskLevel
        ]);
    }
}
