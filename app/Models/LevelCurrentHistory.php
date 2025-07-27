<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Représente un enregistrement HISTORIQUE de l'état de niveau/performance
 * d'un distributeur pour une période donnée.
 * Note: Pas de contraintes FK en base, mais les relations Eloquent peuvent exister.
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
 * @property \Illuminate\Support\Carbon|null $created_at (Date d'archivage)
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @property-read Distributeur|null $distributeur Le distributeur ACTUEL (peut être null si supprimé)
 * @property-read Distributeur|null $parentDistributeur Le distributeur parent ACTUEL (peut être null)
 */
class LevelCurrentHistory extends Model
{
    use HasFactory;

    protected $table = 'level_current_histories';
    public $timestamps = true;

    // $guarded est souvent utilisé pour les tables où l'on insère des données copiées
    // plutôt que via formulaire, pour éviter d'oublier un champ dans $fillable.
    // $guarded = []; // Autorise tous les champs sauf ceux gardés par Eloquent (id, etc.)
    // OU définir $fillable si vous préférez
    protected $fillable = [
        'rang', 'period', 'distributeur_id', 'etoiles', 'cumul_individuel',
        'new_cumul', 'cumul_total', 'cumul_collectif', 'id_distrib_parent',
        'is_children', 'is_indivual_cumul_checked',
        // On ne met généralement pas created_at/updated_at dans fillable
    ];


    protected $casts = [
        'id' => 'integer',
        'rang' => 'integer',
        'distributeur_id' => 'integer',
        'etoiles' => 'integer', // Ou smallInteger
        'cumul_individuel' => 'decimal:2',
        'new_cumul' => 'decimal:2',
        'cumul_total' => 'decimal:2',
        'cumul_collectif' => 'decimal:2',
        'id_distrib_parent' => 'integer', // Est nullable
        'is_children' => 'boolean',
        'is_indivual_cumul_checked' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relation (Optionnelle): Tente de lier à l'enregistrement Distributeur ACTUEL.
     * Fonctionne même sans FK en DB. Peut retourner NULL si le distributeur a été supprimé.
     */
    public function distributeur(): BelongsTo
    {
        return $this->belongsTo(Distributeur::class, 'distributeur_id', 'id');
    }

    /**
     * Relation (Optionnelle): Tente de lier à l'enregistrement Distributeur parent ACTUEL.
     * Fonctionne même sans FK en DB. Peut retourner NULL si le parent a été supprimé.
     */
    public function parentDistributeur(): BelongsTo
    {
        return $this->belongsTo(Distributeur::class, 'id_distrib_parent', 'id');
    }
}
