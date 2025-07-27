<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth; // AJOUT DE L'IMPORT MANQUANT

/**
 * Modèle pour gérer les demandes de suppression avec workflow d'approbation
 *
 * @property int $id
 * @property string $entity_type Type d'entité (distributeur, achat, etc.)
 * @property int $entity_id ID de l'entité à supprimer
 * @property int $requested_by_id ID de l'utilisateur demandeur
 * @property int|null $approved_by_id ID de l'utilisateur approbateur
 * @property string $status Statut: pending, approved, rejected, completed
 * @property string $reason Raison de la suppression
 * @property array $validation_data Données de validation
 * @property array $backup_info Informations de backup
 * @property string|null $rejection_reason Raison du rejet
 * @property \Carbon\Carbon|null $approved_at Date d'approbation
 * @property \Carbon\Carbon|null $completed_at Date de completion
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class DeletionRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'entity_type',
        'entity_id',
        'requested_by_id',
        'approved_by_id',
        'status',
        'reason',
        'validation_data',
        'backup_info',
        'rejection_reason',
        'approved_at',
        'completed_at'
    ];

    protected $casts = [
        'validation_data' => 'array',
        'backup_info' => 'array',
        'approved_at' => 'datetime',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Statuts possibles
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    // Types d'entités
    const ENTITY_DISTRIBUTEUR = 'distributeur';
    const ENTITY_ACHAT = 'achat';
    const ENTITY_PRODUCT = 'product';
    const ENTITY_BONUS = 'bonus';

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
     * Relation polymorphe vers l'entité à supprimer
     */
    public function entity()
    {
        switch ($this->entity_type) {
            case self::ENTITY_DISTRIBUTEUR:
                return Distributeur::find($this->entity_id);
            case self::ENTITY_ACHAT:
                return Achat::find($this->entity_id);
            case self::ENTITY_PRODUCT:
                return Product::find($this->entity_id);
            case self::ENTITY_BONUS:
                return Bonus::find($this->entity_id);
            default:
                return null;
        }
    }

    /**
     * Scopes
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopeByEntityType($query, string $entityType)
    {
        return $query->where('entity_type', $entityType);
    }

    public function scopeRequestedBy($query, int $userId)
    {
        return $query->where('requested_by_id', $userId);
    }

    /**
     * Méthodes d'état
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function canBeApproved(): bool
    {
        return $this->isPending() && $this->entity() !== null;
    }

    public function canBeRejected(): bool
    {
        return $this->isPending();
    }

    public function canBeExecuted(): bool
    {
        return $this->isApproved() && $this->entity() !== null;
    }

    /**
     * Actions sur les demandes
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
            'rejection_reason' => null
        ]);

        // Log de l'approbation
        Log::info("Demande de suppression approuvée", [
            'deletion_request_id' => $this->id,
            'entity_type' => $this->entity_type,
            'entity_id' => $this->entity_id,
            'approved_by' => $approverId,
            'note' => $note
        ]);

        return true;
    }

    public function reject(int $rejectorId, string $reason): bool
    {
        if (!$this->canBeRejected()) {
            return false;
        }

        $this->update([
            'status' => self::STATUS_REJECTED,
            'approved_by_id' => $rejectorId,
            'rejection_reason' => $reason,
            'approved_at' => now()
        ]);

        // Log du rejet
        Log::info("Demande de suppression rejetée", [
            'deletion_request_id' => $this->id,
            'entity_type' => $this->entity_type,
            'entity_id' => $this->entity_id,
            'rejected_by' => $rejectorId,
            'reason' => $reason
        ]);

        return true;
    }

    public function markAsCompleted(array $executionDetails = []): bool
    {
        if (!$this->isApproved()) {
            return false;
        }

        $backupInfo = $this->backup_info ?? [];
        $backupInfo['execution_details'] = $executionDetails;
        $backupInfo['executed_at'] = now()->toISOString();

        $this->update([
            'status' => self::STATUS_COMPLETED,
            'completed_at' => now(),
            'backup_info' => $backupInfo
        ]);

        // Log de l'exécution
        Log::info("Suppression exécutée avec succès", [
            'deletion_request_id' => $this->id,
            'entity_type' => $this->entity_type,
            'entity_id' => $this->entity_id,
            'execution_details' => $executionDetails
        ]);

        return true;
    }

    /**
     * Créer une demande de suppression pour un distributeur
     * MÉTHODE MANQUANTE AJOUTÉE
     */
    public static function createForDistributeur(Distributeur $distributeur, string $reason, array $validationResult): self
    {
        return self::create([
            'entity_type' => self::ENTITY_DISTRIBUTEUR,
            'entity_id' => $distributeur->id,
            'requested_by_id' => Auth::id(),
            'status' => self::STATUS_PENDING,
            'reason' => $reason,
            'validation_data' => $validationResult,
        ]);
    }

    /**
     * Méthodes utilitaires
     */
    public function getEntityName(): string
    {
        $entity = $this->entity();
        if (!$entity) {
            return "Entité supprimée (ID: {$this->entity_id})";
        }

        switch ($this->entity_type) {
            case self::ENTITY_DISTRIBUTEUR:
                return $entity->full_name ?? $entity->name ?? "Distributeur #{$this->entity_id}";
            case self::ENTITY_ACHAT:
                return "Achat #{$this->entity_id}";
            case self::ENTITY_PRODUCT:
                return $entity->name ?? "Produit #{$this->entity_id}";
            case self::ENTITY_BONUS:
                return "Bonus période " . ($entity->period ?? '');
            default:
                return "Entité inconnue";
        }
    }

    public function getStatusLabel(): string
    {
        return match($this->status) {
            self::STATUS_PENDING => 'En attente',
            self::STATUS_APPROVED => 'Approuvée',
            self::STATUS_REJECTED => 'Rejetée',
            self::STATUS_COMPLETED => 'Exécutée',
            self::STATUS_CANCELLED => 'Annulée',
            default => 'Inconnu'
        };
    }

    public function getStatusColor(): string
    {
        return match($this->status) {
            self::STATUS_PENDING => 'yellow',
            self::STATUS_APPROVED => 'blue',
            self::STATUS_REJECTED => 'red',
            self::STATUS_COMPLETED => 'green',
            self::STATUS_CANCELLED => 'gray',
            default => 'gray'
        };
    }

    /**
     * Détermine l'impact de la suppression
     */
    public function getImpactLevel(): string
    {
        $validationData = $this->validation_data ?? [];
        $relatedCount = 0;

        if (isset($validationData['related_data'])) {
            foreach ($validationData['related_data'] as $data) {
                $relatedCount += is_array($data) ? count($data) : 0;
            }
        }

        if ($relatedCount > 100) {
            return 'critical';
        } elseif ($relatedCount > 50) {
            return 'high';
        } elseif ($relatedCount > 10) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    /**
     * Vérifier si la demande est expirée
     */
    public function isExpired(): bool
    {
        // Les demandes sont considérées expirées après 30 jours si toujours en attente
        return $this->isPending() && $this->created_at->addDays(30)->isPast();
    }
}
