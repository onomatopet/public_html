<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MLMCleaningProgress extends Model
{
    use HasFactory;

    protected $table = 'mlm_cleaning_progress';

    protected $fillable = [
        'session_id',
        'step',
        'sub_step',
        'total_items',
        'processed_items',
        'percentage',
        'current_item',
        'message',
        'status',
        'started_at',
        'completed_at'
    ];

    protected $casts = [
        'total_items' => 'integer',
        'processed_items' => 'integer',
        'percentage' => 'float',
        'started_at' => 'datetime',
        'completed_at' => 'datetime'
    ];

    /**
     * Status
     */
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    /**
     * Steps
     */
    const STEP_SNAPSHOT = 'snapshot_creation';
    const STEP_ANALYSIS = 'data_analysis';
    const STEP_HIERARCHY = 'hierarchy_validation';
    const STEP_CUMULS = 'cumuls_calculation';
    const STEP_GRADES = 'grades_validation';
    const STEP_PREVIEW = 'preview_generation';
    const STEP_CORRECTION = 'applying_corrections';
    const STEP_FINALIZATION = 'finalization';

    /**
     * Relations
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(MLMCleaningSession::class, 'session_id');
    }

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->started_at) {
                $model->started_at = now();
            }
        });
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_PROCESSING);
    }

    public function scopeByStep($query, $step)
    {
        return $query->where('step', $step);
    }

    /**
     * Helpers
     */
    public function getStepLabel(): string
    {
        return match($this->step) {
            self::STEP_SNAPSHOT => 'Création de la sauvegarde',
            self::STEP_ANALYSIS => 'Analyse des données',
            self::STEP_HIERARCHY => 'Validation de la hiérarchie',
            self::STEP_CUMULS => 'Calcul des cumuls',
            self::STEP_GRADES => 'Validation des grades',
            self::STEP_PREVIEW => 'Génération du preview',
            self::STEP_CORRECTION => 'Application des corrections',
            self::STEP_FINALIZATION => 'Finalisation',
            default => 'Étape inconnue'
        };
    }

    public function getStatusLabel(): string
    {
        return match($this->status) {
            self::STATUS_PENDING => 'En attente',
            self::STATUS_PROCESSING => 'En cours',
            self::STATUS_COMPLETED => 'Terminé',
            self::STATUS_FAILED => 'Échoué',
            default => 'Inconnu'
        };
    }

    public function getStatusColor(): string
    {
        return match($this->status) {
            self::STATUS_PENDING => 'gray',
            self::STATUS_PROCESSING => 'blue',
            self::STATUS_COMPLETED => 'green',
            self::STATUS_FAILED => 'red',
            default => 'gray'
        };
    }

    public function getDuration(): ?string
    {
        if (!$this->started_at) {
            return null;
        }

        $endTime = $this->completed_at ?? now();
        $duration = $this->started_at->diff($endTime);

        if ($duration->h > 0) {
            return sprintf('%dh %dm %ds', $duration->h, $duration->i, $duration->s);
        } elseif ($duration->i > 0) {
            return sprintf('%dm %ds', $duration->i, $duration->s);
        } else {
            return sprintf('%ds', $duration->s);
        }
    }

    /**
     * Update progress
     */
    public function updateProgress(int $processedItems, ?string $currentItem = null, ?string $message = null): void
    {
        $updates = [
            'processed_items' => $processedItems,
            'percentage' => $this->total_items > 0
                ? round(($processedItems / $this->total_items) * 100, 2)
                : 0
        ];

        if ($currentItem !== null) {
            $updates['current_item'] = $currentItem;
        }

        if ($message !== null) {
            $updates['message'] = $message;
        }

        if ($processedItems >= $this->total_items) {
            $updates['status'] = self::STATUS_COMPLETED;
            $updates['completed_at'] = now();
        }

        $this->update($updates);
    }

    /**
     * Mark as completed
     */
    public function markAsCompleted(?string $message = null): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'completed_at' => now(),
            'percentage' => 100,
            'message' => $message ?? 'Étape terminée avec succès'
        ]);
    }

    /**
     * Mark as failed
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'completed_at' => now(),
            'message' => $errorMessage
        ]);
    }

    /**
     * Create progress for a step
     */
    public static function createForStep(int $sessionId, string $step, int $totalItems, ?string $subStep = null): self
    {
        return self::create([
            'session_id' => $sessionId,
            'step' => $step,
            'sub_step' => $subStep,
            'total_items' => $totalItems,
            'processed_items' => 0,
            'percentage' => 0,
            'status' => self::STATUS_PROCESSING,
            'message' => 'Démarrage...'
        ]);
    }
}
