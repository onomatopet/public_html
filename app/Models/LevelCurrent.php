<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Représente l'état de niveau/performance actuel d'un distributeur pour une période donnée.
 *
 * @property int $id
 * @property int $rang
 * @property string|null $period
 * @property int $distributeur_id (Devrait maintenant être l'ID primaire)
 * @property int $etoiles
 * @property float $cumul_individuel (Casté decimal)
 * @property float $new_cumul (Casté decimal)
 * @property float $cumul_total (Casté decimal)
 * @property float $cumul_collectif (Casté decimal)
 * @property int|null $id_distrib_parent (Devrait maintenant être l'ID primaire)
 * @property bool $is_children (Flag interne, ancien enum)
 * @property bool $is_indivual_cumul_checked (Flag interne, ancien enum)
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @property-read Distributeur $distributeur
 * @property-read Distributeur|null $parentDistributeur
 */
class LevelCurrent extends Model
{
    use HasFactory;

    // Utiliser le nouveau nom de table
    protected $table = 'level_currents';
    public $timestamps = true;

    protected $fillable = [
        'rang',
        'period',
        'distributeur_id',
        'etoiles',
        'cumul_individuel',
        'new_cumul',
        'cumul_total',
        'cumul_collectif',
        'id_distrib_parent',
        'is_children', // Renommer si souhaité
        'is_indivual_cumul_checked', // Renommer si souhaité
    ];

    protected $casts = [
        'id' => 'integer',
        'rang' => 'integer',
        'distributeur_id' => 'integer',
        'etoiles' => 'integer', // Ou smallInteger si changé en DB
        'cumul_individuel' => 'decimal:2',
        'new_cumul' => 'decimal:2',
        'cumul_total' => 'decimal:2',
        'cumul_collectif' => 'decimal:2',
        'id_distrib_parent' => 'integer', // Est nullable maintenant
        'is_children' => 'boolean', // Cast de l'ancien enum/tinyint
        'is_indivual_cumul_checked' => 'boolean', // Cast de l'ancien enum/tinyint
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relation: Cette entrée de niveau appartient à un Distributeur.
     */
    public function distributeur(): BelongsTo
    {
        return $this->belongsTo(Distributeur::class, 'distributeur_id', 'id');
    }

    /**
     * Relation: Cette entrée de niveau peut avoir un parent (via le distributeur parent à ce moment).
     */
    public function parentDistributeur(): BelongsTo
    {
        // Relation via la colonne id_distrib_parent de cette table vers l'ID primaire de Distributeur
        return $this->belongsTo(Distributeur::class, 'id_distrib_parent', 'id');
    }
}
