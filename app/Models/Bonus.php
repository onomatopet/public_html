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
        // AJOUTER CES COLONNES IMPORTANTES !
        'bonus_direct',
        'bonus_indirect',
        'bonus_leadership',
        'bonus', // Total après épargne
        'montant', // Pour compatibilité
        'epargne',
        // Montants en CFA
        'montant_direct',
        'montant_indirect',
        'montant_leadership',
        'montant_total',
        // Autres champs
        'status',
        'details',
        'calculated_at',
        'validated_by',
        'validated_at',
        'paid_at',
        'payment_reference'
    ];

    protected $casts = [
        // Montants en euros
        'bonus_direct' => 'decimal:2',
        'bonus_indirect' => 'decimal:2',
        'bonus_leadership' => 'decimal:2',
        'bonus' => 'decimal:2',
        'montant' => 'decimal:2',
        'epargne' => 'decimal:2',
        // Montants en CFA
        'montant_direct' => 'decimal:2',
        'montant_indirect' => 'decimal:2',
        'montant_leadership' => 'decimal:2',
        'montant_total' => 'decimal:2',
        // Autres
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
     */
    public function scopeForPeriod($query, ?string $period = null)
    {
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
        if (strlen($this->num) >= 11) {
            return substr($this->num, 0, 4) . '-' .
                   substr($this->num, 4, 2) . '-' .
                   substr($this->num, 6, 2) . '-' .
                   substr($this->num, 8, 3);
        }
        return $this->num;
    }

    public function canBeValidated(): bool
    {
        return $this->status === self::STATUS_CALCULE;
    }

    public function canBePaid(): bool
    {
        return $this->status === self::STATUS_VALIDE;
    }

    /**
     * Accesseurs utiles
     */
    public function getTotalEurosAttribute(): float
    {
        return $this->bonus ?? 0;
    }

    public function getTotalCfaAttribute(): float
    {
        return $this->montant_total ?? 0;
    }
}
