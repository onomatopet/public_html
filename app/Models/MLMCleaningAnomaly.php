<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MLMCleaningAnomaly extends Model
{
    use HasFactory;

    protected $table = 'mlm_cleaning_anomalies';

    protected $fillable = [
        'session_id',
        'distributeur_id',
        'period',
        'type',
        'severity',
        'field_name',
        'current_value',
        'expected_value',
        'description',
        'metadata',
        'can_auto_fix',
        'is_fixed',
        'detected_at',
        'fixed_at'
    ];

    protected $casts = [
        'metadata' => 'array',
        'can_auto_fix' => 'boolean',
        'is_fixed' => 'boolean',
        'detected_at' => 'datetime',
        'fixed_at' => 'datetime'
    ];

    /**
     * Anomaly types
     */
    const TYPE_HIERARCHY_LOOP = 'hierarchy_loop';
    const TYPE_ORPHAN_PARENT = 'orphan_parent';
    const TYPE_CUMUL_INDIVIDUAL_NEGATIVE = 'cumul_individual_negative';
    const TYPE_CUMUL_COLLECTIVE_LESS_THAN_INDIVIDUAL = 'cumul_collective_less_than_individual';
    const TYPE_CUMUL_DECREASE = 'cumul_decrease';
    const TYPE_GRADE_REGRESSION = 'grade_regression';
    const TYPE_GRADE_SKIP = 'grade_skip';
    const TYPE_GRADE_CONDITIONS_NOT_MET = 'grade_conditions_not_met';
    const TYPE_MISSING_PERIOD = 'missing_period';
    const TYPE_DUPLICATE_PERIOD = 'duplicate_period';
    const TYPE_OTHER = 'other';

    /**
     * Severity levels
     */
    const SEVERITY_LOW = 'low';
    const SEVERITY_MEDIUM = 'medium';
    const SEVERITY_HIGH = 'high';
    const SEVERITY_CRITICAL = 'critical';

    /**
     * Relations
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(MLMCleaningSession::class, 'session_id');
    }

    public function distributeur(): BelongsTo
    {
        return $this->belongsTo(Distributeur::class, 'distributeur_id');
    }

    /**
     * Scopes
     */
    public function scopeUnfixed($query)
    {
        return $query->where('is_fixed', false);
    }

    public function scopeAutoFixable($query)
    {
        return $query->where('can_auto_fix', true);
    }

    public function scopeBySeverity($query, $severity)
    {
        return $query->where('severity', $severity);
    }

    public function scopeCritical($query)
    {
        return $query->where('severity', self::SEVERITY_CRITICAL);
    }

    /**
     * Helpers
     */
    public function getTypeLabel(): string
    {
        return match($this->type) {
            self::TYPE_HIERARCHY_LOOP => 'Boucle dans la hiérarchie',
            self::TYPE_ORPHAN_PARENT => 'Parent orphelin',
            self::TYPE_CUMUL_INDIVIDUAL_NEGATIVE => 'Cumul individuel négatif',
            self::TYPE_CUMUL_COLLECTIVE_LESS_THAN_INDIVIDUAL => 'Cumul collectif < individuel',
            self::TYPE_CUMUL_DECREASE => 'Diminution du cumul',
            self::TYPE_GRADE_REGRESSION => 'Régression de grade',
            self::TYPE_GRADE_SKIP => 'Saut de grade',
            self::TYPE_GRADE_CONDITIONS_NOT_MET => 'Conditions de grade non remplies',
            self::TYPE_MISSING_PERIOD => 'Période manquante',
            self::TYPE_DUPLICATE_PERIOD => 'Période dupliquée',
            default => 'Autre anomalie'
        };
    }

    public function getSeverityLabel(): string
    {
        return match($this->severity) {
            self::SEVERITY_LOW => 'Faible',
            self::SEVERITY_MEDIUM => 'Moyenne',
            self::SEVERITY_HIGH => 'Élevée',
            self::SEVERITY_CRITICAL => 'Critique',
            default => 'Inconnue'
        };
    }

    public function getSeverityColor(): string
    {
        return match($this->severity) {
            self::SEVERITY_LOW => 'blue',
            self::SEVERITY_MEDIUM => 'yellow',
            self::SEVERITY_HIGH => 'orange',
            self::SEVERITY_CRITICAL => 'red',
            default => 'gray'
        };
    }

    public function getFixButtonLabel(): string
    {
        if ($this->is_fixed) {
            return 'Corrigé';
        }

        return $this->can_auto_fix ? 'Corriger automatiquement' : 'Correction manuelle requise';
    }

    /**
     * Mark as fixed
     */
    public function markAsFixed(): void
    {
        $this->update([
            'is_fixed' => true,
            'fixed_at' => now()
        ]);
    }

    /**
     * Get suggested fix based on anomaly type
     */
    public function getSuggestedFix(): ?array
    {
        return match($this->type) {
            self::TYPE_CUMUL_INDIVIDUAL_NEGATIVE => [
                'action' => 'set_to_zero',
                'description' => 'Mettre le cumul individuel à 0'
            ],
            self::TYPE_CUMUL_COLLECTIVE_LESS_THAN_INDIVIDUAL => [
                'action' => 'recalculate_collective',
                'description' => 'Recalculer le cumul collectif'
            ],
            self::TYPE_GRADE_CONDITIONS_NOT_MET => [
                'action' => 'recalculate_grade',
                'description' => 'Recalculer le grade selon les conditions'
            ],
            self::TYPE_ORPHAN_PARENT => [
                'action' => 'set_parent_null',
                'description' => 'Supprimer la référence au parent invalide'
            ],
            default => null
        };
    }

    /**
     * Create anomaly record
     */
    public static function record(
        int $sessionId,
        int $distributeurId,
        string $period,
        string $type,
        string $description,
        array $options = []
    ): self {
        return self::create([
            'session_id' => $sessionId,
            'distributeur_id' => $distributeurId,
            'period' => $period,
            'type' => $type,
            'description' => $description,
            'severity' => $options['severity'] ?? self::SEVERITY_MEDIUM,
            'field_name' => $options['field_name'] ?? null,
            'current_value' => $options['current_value'] ?? null,
            'expected_value' => $options['expected_value'] ?? null,
            'metadata' => $options['metadata'] ?? null,
            'can_auto_fix' => $options['can_auto_fix'] ?? false,
            'detected_at' => now()
        ]);
    }
}
