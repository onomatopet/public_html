<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Représente une ligne d'un Achat effectué par un Distributeur.
 *
 * @property int $id
 * @property string $period
 * @property int $distributeur_id
 * @property int $products_id
 * @property int $qt
 * @property float $points_unitaire_achat (Casté decimal)
 * @property float $montant_total_ligne (Casté decimal)
 * @property float $prix_unitaire_achat (Casté decimal)
 * @property bool $online
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @property-read Distributeur $distributeur
 * @property-read Product $product
 */
class Achat extends Model
{
    use HasFactory;

    protected $table = 'achats';
    public $timestamps = true;

    protected $fillable = [
    'period',
    'distributeur_id',
    'products_id',
    'qt',
    'online',
    'prix_unitaire_achat',
    'points_unitaire_achat',
    'montant_total_ligne',
    'purchase_date', // NOUVEAU CHAMP
];

// Dans la propriété $casts, ajouter :
protected $casts = [
    'id' => 'integer',
    'distributeur_id' => 'integer',
    'products_id' => 'integer',
    'qt' => 'integer',
    'online' => 'boolean',
    'prix_unitaire_achat' => 'decimal:2',
    'points_unitaire_achat' => 'decimal:2',
    'montant_total_ligne' => 'decimal:2',
    'purchase_date' => 'date', // NOUVEAU CAST
    'created_at' => 'datetime',
    'updated_at' => 'datetime',
];

    /**
     * Relation: Un Achat appartient à un Distributeur.
     */
    public function distributeur(): BelongsTo
    {
        return $this->belongsTo(Distributeur::class, 'distributeur_id', 'id');
    }

    /**
     * Relation: Un Achat concerne un Produit.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'products_id', 'id');
    }
}
