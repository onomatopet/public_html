<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Représente une valeur en points (PV) possible pour un produit.
 *
 * @property int $id
 * @property int|null $numbers Le nombre de points.
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection|Product[] $products
 */
class PointValeur extends Model
{
    use HasFactory;

    // Nom de la table au pluriel
    protected $table = 'pointvaleurs';
    public $timestamps = true;

    protected $fillable = [
        'numbers',
    ];

    protected $casts = [
        'id' => 'integer',
        'numbers' => 'integer', // tinyint est traité comme integer
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relation: Une valeur en points peut être associée à plusieurs Produits.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'pointvaleur_id', 'id');
    }
}
