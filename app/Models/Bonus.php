<?php
// app/Models/Bonus.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bonus extends Model
{
    protected $fillable = [
        'num',
        'distributeur_id',
        'period',
        'montant_direct',
        'montant_indirect',
        'montant_leadership',
        'montant_total',
        'status',
        'details',
        'calculated_at',
        'validated_by',
        'validated_at',
        'paid_at',
        'payment_reference'
    ];

    protected $casts = [
        'montant_direct' => 'decimal:2',
        'montant_indirect' => 'decimal:2',
        'montant_leadership' => 'decimal:2',
        'montant_total' => 'decimal:2',
        'details' => 'array',
        'calculated_at' => 'datetime',
        'validated_at' => 'datetime',
        'paid_at' => 'datetime'
    ];

    const STATUS_CALCULE = 'calculé';
    const STATUS_VALIDE = 'validé';
    const STATUS_EN_PAIEMENT = 'en_paiement';
    const STATUS_PAYE = 'payé';
    const STATUS_ANNULE = 'annulé';

    public function distributeur(): BelongsTo
    {
        return $this->belongsTo(Distributeur::class, 'distributeur_id');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    /**
     * Scope pour filtrer par période
     * Correction : accepte maintenant une période nullable
     */
    public function scopeForPeriod($query, ?string $period = null)
    {
        // Si la période est null ou vide, on ne filtre pas
        if (empty($period)) {
            return $query;
        }

        return $query->where('period', $period);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_CALCULE);
    }

    public function scopeValidated($query)
    {
        return $query->where('status', self::STATUS_VALIDE);
    }

    public function getFormattedNumAttribute(): string
    {
        // Format: 7770-MM-YY-XXX
        return substr($this->num, 0, 4) . '-' .
               substr($this->num, 4, 2) . '-' .
               substr($this->num, 6, 2) . '-' .
               substr($this->num, 8, 3);
    }

    public function canBeValidated(): bool
    {
        return $this->status === self::STATUS_CALCULE;
    }

    public function canBePaid(): bool
    {
        return $this->status === self::STATUS_VALIDE;
    }
}
