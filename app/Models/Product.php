<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Représente un Produit.
 *
 * @property int $id
 * @property int|null $category_id
 * @property int $pointvaleur_id
 * @property string $code_product
 * @property string $nom_produit
 * @property float $prix_product (Casté en decimal)
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @property-read Category|null $category
 * @property-read PointValeur $pointValeur
 * @property-read \Illuminate\Database\Eloquent\Collection|Achat[] $achats
 */
class Product extends Model
{
    use HasFactory;

    protected $table = 'products';
    public $timestamps = true;

    protected $fillable = [
        'category_id',
        'pointvaleur_id',
        'code_product',
        'nom_produit',
        'prix_product',
        'description',
    ];

    protected $casts = [
        'id' => 'integer',
        'category_id' => 'integer',
        'pointvaleur_id' => 'integer', // Ou smallInteger si changé en DB
        'prix_product' => 'decimal:2', // Important pour les prix
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relation: Un Produit appartient à une Catégorie (peut être null).
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id', 'id');
    }

    /**
     * Relation: Un Produit a une valeur en Points.
     */
    public function pointValeur(): BelongsTo
    {
        return $this->belongsTo(PointValeur::class, 'pointvaleur_id', 'id');
    }

    /**
     * Relation: Un Produit peut être présent dans plusieurs lignes d'Achat.
     */
    public function achats(): HasMany
    {
        // Assurez-vous que le modèle Achat existe
        return $this->hasMany(Achat::class, 'products_id', 'id');
    }
}
