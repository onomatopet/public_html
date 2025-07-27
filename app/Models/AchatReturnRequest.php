<?php 

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;

class AchatReturnRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'achat_id',
        'requested_by_id',
        'approved_by_id',
        'type',
        'status',
        'reason',
        'notes',
        'rejection_reason',
        'quantity_to_return',
        'amount_to_refund',
        'validation_data',
        'impact_analysis',
        'approved_at',
        'completed_at'
    ];

    protected $casts = [
        'validation_data' => 'array',
        'impact_analysis' => 'array',
        'amount_to_refund' => 'decimal:2',
        'approved_at' => 'datetime',
        'completed_at' => 'datetime'
    ];

    // Types de demandes
    const TYPE_CANCELLATION = 'cancellation';
    const TYPE_RETURN = 'return';
    const TYPE_PARTIAL_RETURN = 'partial_return';

    // Statuts
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_COMPLETED = 'completed';

    /**
     * Relations
     */
    public function achat(): BelongsTo
    {
        return $this->belongsTo(Achat::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
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

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function canBeApproved(): bool
    {
        return $this->isPending() && $this->achat->status === 'active';
    }

    public function canBeExecuted(): bool
    {
        return $this->isApproved() && !$this->isCompleted();
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
            'notes' => $note
        ]);

        Log::info("Demande de retour/annulation approuvée", [
            'request_id' => $this->id,
            'achat_id' => $this->achat_id,
            'type' => $this->type,
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

        Log::info("Demande de retour/annulation rejetée", [
            'request_id' => $this->id,
            'achat_id' => $this->achat_id,
            'type' => $this->type,
            'rejected_by' => $rejectorId,
            'reason' => $reason
        ]);

        return true;
    }

    /**
     * Getters utilitaires
     */
    public function getTypeLabel(): string
    {
        $labels = [
            self::TYPE_CANCELLATION => 'Annulation',
            self::TYPE_RETURN => 'Retour complet',
            self::TYPE_PARTIAL_RETURN => 'Retour partiel'
        ];

        return $labels[$this->type] ?? $this->type;
    }

    public function getStatusLabel(): string
    {
        $labels = [
            self::STATUS_PENDING => 'En attente',
            self::STATUS_APPROVED => 'Approuvée',
            self::STATUS_REJECTED => 'Rejetée',
            self::STATUS_COMPLETED => 'Exécutée'
        ];

        return $labels[$this->status] ?? $this->status;
    }

    public function getStatusColor(): string
    {
        $colors = [
            self::STATUS_PENDING => 'yellow',
            self::STATUS_APPROVED => 'green',
            self::STATUS_REJECTED => 'red',
            self::STATUS_COMPLETED => 'blue'
        ];

        return $colors[$this->status] ?? 'gray';
    }
}