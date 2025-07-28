<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class MLMCleaningSnapshot extends Model
{
    use HasFactory;

    protected $table = 'mlm_cleaning_snapshots';

    protected $fillable = [
        'session_id',
        'type',
        'status',
        'storage_path',
        'file_size',
        'records_count',
        'tables_included',
        'metadata',
        'compression_info',
        'expires_at'
    ];

    protected $casts = [
        'tables_included' => 'array',
        'metadata' => 'array',
        'expires_at' => 'datetime',
        'file_size' => 'integer',
        'records_count' => 'integer'
    ];

    /**
     * Types
     */
    const TYPE_FULL = 'full';
    const TYPE_PARTIAL = 'partial';

    /**
     * Status
     */
    const STATUS_CREATING = 'creating';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_EXPIRED = 'expired';

    /**
     * Relations
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(MLMCleaningSession::class, 'session_id');
    }

    /**
     * Scopes
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeNotExpired($query)
    {
        return $query->where('status', '!=', self::STATUS_EXPIRED)
                    ->where(function ($q) {
                        $q->whereNull('expires_at')
                          ->orWhere('expires_at', '>', now());
                    });
    }

    /**
     * Helpers
     */
    public function getFileSizeFormatted(): string
    {
        $bytes = $this->file_size;

        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }

    public function isAvailable(): bool
    {
        return $this->status === self::STATUS_COMPLETED
            && !$this->isExpired()
            && $this->fileExists();
    }

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED
            || ($this->expires_at && $this->expires_at->isPast());
    }

    public function fileExists(): bool
    {
        return $this->storage_path && Storage::disk('local')->exists($this->storage_path);
    }

    /**
     * Mark as expired
     */
    public function markAsExpired(): void
    {
        $this->update(['status' => self::STATUS_EXPIRED]);

        if ($this->fileExists()) {
            Storage::disk('local')->delete($this->storage_path);
        }
    }

    /**
     * Get download path
     */
    public function getDownloadPath(): ?string
    {
        if (!$this->isAvailable()) {
            return null;
        }

        return storage_path('app/' . $this->storage_path);
    }

    /**
     * Create snapshot metadata
     */
    public function generateMetadata(): array
    {
        return [
            'created_at' => now()->toIso8601String(),
            'session_code' => $this->session->session_code,
            'type' => $this->session->type,
            'period_range' => [
                'start' => $this->session->period_start,
                'end' => $this->session->period_end
            ],
            'statistics' => [
                'total_records' => $this->records_count,
                'anomalies_found' => $this->session->records_with_anomalies,
                'tables_backed_up' => count($this->tables_included)
            ]
        ];
    }
}
