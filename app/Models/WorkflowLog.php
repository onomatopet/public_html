<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowLog extends Model
{
    // Constantes de statut
    const STATUS_STARTED = 'started';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_SKIPPED = 'skipped';

    // Constantes d'étapes
    const STEP_VALIDATION = 'validation';
    const STEP_AGGREGATION = 'aggregation';
    const STEP_ADVANCEMENT = 'advancement';
    const STEP_SNAPSHOT = 'snapshot';
    const STEP_CLOSURE = 'closure';

    protected $fillable = [
        'period',
        'step',
        'action',
        'status',
        'user_id',
        'started_at',
        'completed_at',
        'duration_seconds',
        'details',
        'error_message'
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'details' => 'array'
    ];

    /**
     * Relation avec l'utilisateur
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Obtient le label de l'étape
     */
    public function getStepLabelAttribute(): string
    {
        $labels = [
            'validation' => 'Validation des achats',
            'aggregation' => 'Agrégation des achats',
            'advancement' => 'Calcul des avancements',
            'snapshot' => 'Création du snapshot',
            'closing' => 'Clôture de la période'
        ];

        return $labels[$this->step] ?? $this->step;
    }

    // Méthodes statiques
    public static function logStart(string $period, string $step, string $action, int $userId, array $details = []): self
    {
        return self::create([
            'period' => $period,
            'step' => $step,
            'action' => $action,
            'status' => self::STATUS_STARTED,
            'user_id' => $userId,
            'details' => $details,
            'started_at' => now(),
        ]);
    }

    public function complete(array $additionalDetails = []): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'completed_at' => now(),
            'details' => array_merge($this->details ?? [], $additionalDetails),
        ]);
    }

    public function fail(string $errorMessage, array $additionalDetails = []): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'completed_at' => now(),
            'error_message' => $errorMessage,
            'details' => array_merge($this->details ?? [], $additionalDetails),
        ]);
    }

    public function skip(string $reason = null): void
    {
        $this->update([
            'status' => self::STATUS_SKIPPED,
            'completed_at' => now(),
            'details' => array_merge($this->details ?? [], ['skip_reason' => $reason]),
        ]);
    }

    /**
     * Obtient le label du statut
     */
    public function getStatusLabelAttribute(): string
    {
        $labels = [
            'started' => 'En cours',
            'completed' => 'Terminé',
            'failed' => 'Échoué'
        ];

        return $labels[$this->status] ?? $this->status;
    }

    /**
     * Obtient la couleur du statut
     */
    public function getStatusColorAttribute(): string
    {
        $colors = [
            'started' => 'yellow',
            'completed' => 'green',
            'failed' => 'red'
        ];

        return $colors[$this->status] ?? 'gray';
    }

    /**
     * Obtient la durée formatée
     */
    public function getDurationForHumansAttribute(): ?string
    {
        if (!$this->duration_seconds) {
            return null;
        }

        if ($this->duration_seconds < 60) {
            return $this->duration_seconds . ' secondes';
        }

        if ($this->duration_seconds < 3600) {
            $minutes = floor($this->duration_seconds / 60);
            $seconds = $this->duration_seconds % 60;
            return "{$minutes}m {$seconds}s";
        }

        $hours = floor($this->duration_seconds / 3600);
        $minutes = floor(($this->duration_seconds % 3600) / 60);
        return "{$hours}h {$minutes}m";
    }
}
