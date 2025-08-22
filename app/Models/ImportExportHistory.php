<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportExportHistory extends Model
{
    use HasFactory;

    protected $table = 'import_export_histories';

    protected $fillable = [
        'type',
        'entity_type',
        'filename',
        'file_path',
        'file_size',
        'status',
        'user_id',
        'options',
        'result',
        'error_message',
        'started_at',
        'completed_at'
    ];

    protected $casts = [
        'options' => 'array',
        'result' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Statuts possibles
     */
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * Relations
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scopes
     */
    public function scopeImports($query)
    {
        return $query->where('type', 'import');
    }

    public function scopeExports($query)
    {
        return $query->where('type', 'export');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function scopeRecent($query, $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Accesseurs
     */
    public function getIsCompleteAttribute(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function getIsFailedAttribute(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function getIsProcessingAttribute(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    public function getDurationAttribute()
    {
        if (!$this->started_at || !$this->completed_at) {
            return null;
        }

        return $this->completed_at->diffInSeconds($this->started_at);
    }

    public function getFormattedDurationAttribute()
    {
        $duration = $this->duration;

        if (!$duration) {
            return 'N/A';
        }

        if ($duration < 60) {
            return $duration . 's';
        }

        if ($duration < 3600) {
            return round($duration / 60, 1) . 'min';
        }

        return round($duration / 3600, 1) . 'h';
    }

    public function getFormattedFileSizeAttribute()
    {
        return $this->formatBytes($this->file_size);
    }

    public function getStatusColorAttribute()
    {
        return match($this->status) {
            self::STATUS_COMPLETED => 'green',
            self::STATUS_PROCESSING => 'blue',
            self::STATUS_FAILED => 'red',
            self::STATUS_CANCELLED => 'gray',
            default => 'yellow'
        };
    }

    public function getStatusLabelAttribute()
    {
        return match($this->status) {
            self::STATUS_PENDING => 'En attente',
            self::STATUS_PROCESSING => 'En cours',
            self::STATUS_COMPLETED => 'Terminé',
            self::STATUS_FAILED => 'Échoué',
            self::STATUS_CANCELLED => 'Annulé',
            default => ucfirst($this->status)
        };
    }

    /**
     * Méthodes
     */
    public function markAsProcessing()
    {
        $this->update([
            'status' => self::STATUS_PROCESSING,
            'started_at' => now()
        ]);
    }

    public function markAsCompleted($result = [])
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'completed_at' => now(),
            'result' => $result
        ]);
    }

    public function markAsFailed($errorMessage)
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'completed_at' => now(),
            'error_message' => $errorMessage
        ]);
    }

    public function markAsCancelled()
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
            'completed_at' => now()
        ]);
    }

    /**
     * Formate les bytes en unité lisible
     */
    protected function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * Nettoie les anciens enregistrements
     */
    public static function cleanup($days = 30)
    {
        return static::where('created_at', '<', now()->subDays($days))
            ->whereIn('status', [self::STATUS_COMPLETED, self::STATUS_FAILED, self::STATUS_CANCELLED])
            ->delete();
    }
}
