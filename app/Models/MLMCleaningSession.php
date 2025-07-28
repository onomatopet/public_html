<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MLMCleaningSession extends Model
{
    use HasFactory;

    protected $table = 'mlm_cleaning_sessions';

    protected $fillable = [
        'session_code',
        'status',
        'type',
        'period_start',
        'period_end',
        'created_by',
        'total_records',
        'records_analyzed',
        'records_with_anomalies',
        'records_corrected',
        'hierarchy_issues',
        'cumul_issues',
        'grade_issues',
        'execution_time',
        'configuration',
        'started_at',
        'analyzed_at',
        'preview_generated_at',
        'processed_at',
        'completed_at',
        'failed_at',
        'rolled_back_at',
        'error_message'
    ];

    protected $casts = [
        'configuration' => 'array',
        'started_at' => 'datetime',
        'analyzed_at' => 'datetime',
        'preview_generated_at' => 'datetime',
        'processed_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
        'rolled_back_at' => 'datetime'
    ];

    /**
     * Constants for status
     */
    const STATUS_PENDING = 'pending';
    const STATUS_ANALYZING = 'analyzing';
    const STATUS_PREVIEW = 'preview';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_ROLLED_BACK = 'rolled_back';

    /**
     * Constants for type
     */
    const TYPE_FULL = 'full';
    const TYPE_PERIOD = 'period';
    const TYPE_DISTRIBUTOR = 'distributor';
    const TYPE_HIERARCHY = 'hierarchy';
    const TYPE_CUMULS = 'cumuls';
    const TYPE_GRADES = 'grades';

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->session_code)) {
                $model->session_code = 'CLN-' . date('YmdHis') . '-' . strtoupper(substr(uniqid(), -4));
            }
        });
    }

    /**
     * Relations
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function anomalies(): HasMany
    {
        return $this->hasMany(MLMCleaningAnomaly::class, 'session_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(MLMCleaningLog::class, 'session_id');
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(MLMCleaningSnapshot::class, 'session_id');
    }

    public function progress(): HasMany
    {
        return $this->hasMany(MLMCleaningProgress::class, 'session_id');
    }

    public function currentProgress(): HasOne
    {
        return $this->hasOne(MLMCleaningProgress::class, 'session_id')
                    ->where('status', 'processing')
                    ->latest();
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', [
            self::STATUS_PENDING,
            self::STATUS_ANALYZING,
            self::STATUS_PREVIEW,
            self::STATUS_PROCESSING
        ]);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Helpers
     */
    public function isActive(): bool
    {
        return in_array($this->status, [
            self::STATUS_PENDING,
            self::STATUS_ANALYZING,
            self::STATUS_PREVIEW,
            self::STATUS_PROCESSING
        ]);
    }

    public function canBeProcessed(): bool
    {
        return $this->status === self::STATUS_PREVIEW;
    }

    public function canBeRolledBack(): bool
    {
        return $this->status === self::STATUS_COMPLETED
            && $this->created_at->diffInDays(now()) <= 30;
    }

    public function getProgressPercentage(): float
    {
        if ($this->total_records == 0) {
            return 0;
        }

        switch ($this->status) {
            case self::STATUS_ANALYZING:
                return ($this->records_analyzed / $this->total_records) * 25;
            case self::STATUS_PREVIEW:
                return 50;
            case self::STATUS_PROCESSING:
                return 50 + (($this->records_corrected / $this->records_with_anomalies) * 40);
            case self::STATUS_COMPLETED:
                return 100;
            default:
                return 0;
        }
    }

    public function getExecutionTimeFormatted(): string
    {
        if (!$this->execution_time) {
            return 'N/A';
        }

        $minutes = floor($this->execution_time / 60);
        $seconds = $this->execution_time % 60;

        return sprintf('%d min %d sec', $minutes, $seconds);
    }

    public function getSummaryStats(): array
    {
        return [
            'total_anomalies' => $this->records_with_anomalies,
            'hierarchy_issues' => $this->hierarchy_issues,
            'cumul_issues' => $this->cumul_issues,
            'grade_issues' => $this->grade_issues,
            'corrections_applied' => $this->records_corrected,
            'success_rate' => $this->records_with_anomalies > 0
                ? round(($this->records_corrected / $this->records_with_anomalies) * 100, 2)
                : 0
        ];
    }

    /**
     * Update status with timestamp
     */
    public function updateStatus(string $status, ?string $errorMessage = null): void
    {
        $updates = ['status' => $status];

        switch ($status) {
            case self::STATUS_ANALYZING:
                $updates['started_at'] = now();
                break;
            case self::STATUS_PREVIEW:
                $updates['analyzed_at'] = now();
                $updates['preview_generated_at'] = now();
                break;
            case self::STATUS_PROCESSING:
                $updates['processed_at'] = now();
                break;
            case self::STATUS_COMPLETED:
                $updates['completed_at'] = now();
                $updates['execution_time'] = $this->started_at
                    ? now()->diffInSeconds($this->started_at)
                    : 0;
                break;
            case self::STATUS_FAILED:
                $updates['failed_at'] = now();
                $updates['error_message'] = $errorMessage;
                break;
            case self::STATUS_ROLLED_BACK:
                $updates['rolled_back_at'] = now();
                break;
        }

        $this->update($updates);
    }
}
