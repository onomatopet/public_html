<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MLMCleaningLog extends Model
{
    use HasFactory;

    protected $table = 'mlm_cleaning_logs';

    protected $fillable = [
        'session_id',
        'distributeur_id',
        'period',
        'table_name',
        'field_name',
        'old_value',
        'new_value',
        'action',
        'reason',
        'context',
        'applied_at'
    ];

    protected $casts = [
        'context' => 'array',
        'applied_at' => 'datetime'
    ];

    /**
     * Action types
     */
    const ACTION_UPDATE = 'update';
    const ACTION_INSERT = 'insert';
    const ACTION_DELETE = 'delete';
    const ACTION_SKIP = 'skip';

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
    public function scopeUpdates($query)
    {
        return $query->where('action', self::ACTION_UPDATE);
    }

    public function scopeByTable($query, $tableName)
    {
        return $query->where('table_name', $tableName);
    }

    public function scopeByField($query, $fieldName)
    {
        return $query->where('field_name', $fieldName);
    }

    /**
     * Helpers
     */
    public function getActionLabel(): string
    {
        return match($this->action) {
            self::ACTION_UPDATE => 'Mise à jour',
            self::ACTION_INSERT => 'Insertion',
            self::ACTION_DELETE => 'Suppression',
            self::ACTION_SKIP => 'Ignoré',
            default => 'Inconnu'
        };
    }

    public function getActionColor(): string
    {
        return match($this->action) {
            self::ACTION_UPDATE => 'blue',
            self::ACTION_INSERT => 'green',
            self::ACTION_DELETE => 'red',
            self::ACTION_SKIP => 'gray',
            default => 'gray'
        };
    }

    public function getFormattedOldValue(): string
    {
        if ($this->old_value === null) {
            return 'NULL';
        }

        if (is_numeric($this->old_value)) {
            return number_format((float)$this->old_value, 2);
        }

        return (string)$this->old_value;
    }

    public function getFormattedNewValue(): string
    {
        if ($this->new_value === null) {
            return 'NULL';
        }

        if (is_numeric($this->new_value)) {
            return number_format((float)$this->new_value, 2);
        }

        return (string)$this->new_value;
    }

    public function getChangeDescription(): string
    {
        return sprintf(
            '%s.%s: %s → %s',
            $this->table_name,
            $this->field_name,
            $this->getFormattedOldValue(),
            $this->getFormattedNewValue()
        );
    }

    /**
     * Log a change
     */
    public static function logChange(
        int $sessionId,
        int $distributeurId,
        string $period,
        string $tableName,
        string $fieldName,
        $oldValue,
        $newValue,
        string $action,
        string $reason,
        array $context = []
    ): self {
        return self::create([
            'session_id' => $sessionId,
            'distributeur_id' => $distributeurId,
            'period' => $period,
            'table_name' => $tableName,
            'field_name' => $fieldName,
            'old_value' => is_array($oldValue) || is_object($oldValue) ? json_encode($oldValue) : $oldValue,
            'new_value' => is_array($newValue) || is_object($newValue) ? json_encode($newValue) : $newValue,
            'action' => $action,
            'reason' => $reason,
            'context' => $context,
            'applied_at' => now()
        ]);
    }

    /**
     * Get rollback data
     */
    public function getRollbackData(): array
    {
        return [
            'table' => $this->table_name,
            'distributeur_id' => $this->distributeur_id,
            'period' => $this->period,
            'field' => $this->field_name,
            'value' => $this->old_value,
            'action' => $this->action === self::ACTION_INSERT ? 'delete' : 'update'
        ];
    }
}
