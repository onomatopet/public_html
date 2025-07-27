<?php
// app/Models/SystemPeriod.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class SystemPeriod extends Model
{
    const STATUS_OPEN = 'open';
    const STATUS_VALIDATION = 'validation';
    const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'period',
        'status',
        'opened_at',
        'validation_started_at',
        'closed_at',
        'closed_by_user_id',
        'closure_summary',
        'is_current',
        // Nouveaux champs workflow
        'purchases_validated',
        'purchases_validated_at',
        'purchases_validated_by',
        'purchases_aggregated',
        'purchases_aggregated_at',
        'purchases_aggregated_by',
        'advancements_calculated',
        'advancements_calculated_at',
        'advancements_calculated_by',
        'snapshot_created',
        'snapshot_created_at',
        'snapshot_created_by',
    ];

    protected $casts = [
        'opened_at' => 'date',
        'validation_started_at' => 'date',
        'closed_at' => 'date',
        'closure_summary' => 'array',
        'is_current' => 'boolean',
        // Nouveaux casts workflow
        'purchases_validated' => 'boolean',
        'purchases_validated_at' => 'datetime',
        'purchases_aggregated' => 'boolean',
        'purchases_aggregated_at' => 'datetime',
        'advancements_calculated' => 'boolean',
        'advancements_calculated_at' => 'datetime',
        'snapshot_created' => 'boolean',
        'snapshot_created_at' => 'datetime',
    ];

    // Relations existantes
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    // Nouvelles relations workflow
    public function purchasesValidatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'purchases_validated_by');
    }

    public function purchasesAggregatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'purchases_aggregated_by');
    }

    public function advancementsCalculatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'advancements_calculated_by');
    }

    public function snapshotCreatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'snapshot_created_by');
    }

    // Méthodes existantes
    public static function getCurrentPeriod(): ?self
    {
        return self::where('is_current', true)->first();
    }

    public function canBeClosed(): bool
    {
        return $this->status === self::STATUS_VALIDATION && $this->snapshot_created;
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    // Nouvelles méthodes workflow
    public function getWorkflowStatus(): array
    {
        return [
            'period_opened' => true,
            'validation_started' => in_array($this->status, [self::STATUS_VALIDATION, self::STATUS_CLOSED]),
            'purchases_validated' => $this->purchases_validated,
            'purchases_aggregated' => $this->purchases_aggregated,
            'advancements_calculated' => $this->advancements_calculated,
            'snapshot_created' => $this->snapshot_created,
            'period_closed' => $this->status === self::STATUS_CLOSED,
        ];
    }

    public function getWorkflowProgress(): int
    {
        $steps = $this->getWorkflowStatus();
        $completed = count(array_filter($steps, fn($step) => $step === true));
        return round(($completed / count($steps)) * 100);
    }

    public function canValidatePurchases(): bool
    {
        return $this->status === self::STATUS_VALIDATION && !$this->purchases_validated;
    }

    public function canAggregatePurchases(): bool
    {
        return $this->status === self::STATUS_VALIDATION
            && $this->purchases_validated
            && !$this->purchases_aggregated;
    }

    public function canCalculateAdvancements(): bool
    {
        return $this->status === self::STATUS_VALIDATION
            && $this->purchases_aggregated
            && !$this->advancements_calculated;
    }

    public function canCreateSnapshot(): bool
    {
        return $this->status === self::STATUS_VALIDATION
            && $this->advancements_calculated
            && !$this->snapshot_created;
    }

    public function canClose(): bool
    {
        return $this->status === self::STATUS_VALIDATION
            && $this->snapshot_created;
    }

    public function getNextStep(): ?string
    {
        if ($this->status === self::STATUS_OPEN) return 'start_validation';
        if (!$this->purchases_validated) return 'validate_purchases';
        if (!$this->purchases_aggregated) return 'aggregate_purchases';
        if (!$this->advancements_calculated) return 'calculate_advancements';
        if (!$this->snapshot_created) return 'create_snapshot';
        if ($this->status !== self::STATUS_CLOSED) return 'close_period';
        return null;
    }

    public function getNextStepLabel(): ?string
    {
        $step = $this->getNextStep();
        return match($step) {
            'start_validation' => 'Démarrer la validation',
            'validate_purchases' => 'Valider les achats',
            'aggregate_purchases' => 'Agréger les achats',
            'calculate_advancements' => 'Calculer les avancements',
            'create_snapshot' => 'Créer le snapshot',
            'close_period' => 'Clôturer la période',
            default => null
        };
    }

    public function updateWorkflowStep(string $step, int $userId): void
    {
        $updates = match($step) {
            'purchases_validated' => [
                'purchases_validated' => true,
                'purchases_validated_at' => now(),
                'purchases_validated_by' => $userId,
            ],
            'purchases_aggregated' => [
                'purchases_aggregated' => true,
                'purchases_aggregated_at' => now(),
                'purchases_aggregated_by' => $userId,
            ],
            'advancements_calculated' => [
                'advancements_calculated' => true,
                'advancements_calculated_at' => now(),
                'advancements_calculated_by' => $userId,
            ],
            'snapshot_created' => [
                'snapshot_created' => true,
                'snapshot_created_at' => now(),
                'snapshot_created_by' => $userId,
            ],
            default => []
        };

        if (!empty($updates)) {
            $this->update($updates);
        }
    }

    /**
     * Helper pour obtenir l'utilisateur qui a complété une étape
     */
    public function completedByUser($field)
    {
        $relation = str_replace('_', '', ucwords($field, '_'));
        $method = lcfirst($relation);

        if (method_exists($this, $method)) {
            return $this->$method;
        }

        return null;
    }

    /**
     * Vérifie si la période peut être modifiée
     */
    public function canBeModified(): bool
    {
        return $this->status !== 'closed';
    }

    /**
     * Vérifie si la période est dans le workflow
     */
    public function isInWorkflow(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Obtient le nombre d'étapes complétées
     */
    public function getCompletedStepsCount(): int
    {
        $count = 0;

        if ($this->status !== 'draft') $count++;
        if ($this->status === 'active') $count++;
        if ($this->purchases_validated) $count++;
        if ($this->purchases_aggregated) $count++;
        if ($this->advancements_calculated) $count++;
        if ($this->snapshot_created) $count++;
        if ($this->status === 'closed') $count++;

        return $count;
    }

    /**
     * Obtient le pourcentage de progression
     */
    public function getProgressPercentage(): float
    {
        return ($this->getCompletedStepsCount() / 7) * 100;
    }
}
