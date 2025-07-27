<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Distributeur extends Model
{
    use HasFactory;

    protected $table = 'distributeurs';
    public $timestamps = true;

    protected $fillable = [
        'distributeur_id',
        'nom_distributeur',
        'pnom_distributeur',
        'tel_distributeur',
        'adress_distributeur',
        'id_distrib_parent',
        'etoiles_id',
        'rang',
        'statut_validation_periode',
        'is_indivual_cumul_checked',
    ];

    protected $casts = [
        'etoiles_id' => 'integer',
        'rang' => 'integer',
        'id_distrib_parent' => 'integer',
        'statut_validation_periode' => 'boolean',
        'is_indivual_cumul_checked' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // --- Relations ---

    /**
     * Relation: Un Distributeur a un parent Distributeur (peut être null).
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Distributeur::class, 'id_distrib_parent', 'id');
    }

    /**
     * Relation: Un Distributeur a plusieurs enfants Distributeurs directs.
     */
    public function children(): HasMany
    {
        return $this->hasMany(Distributeur::class, 'id_distrib_parent', 'id');
    }

    /**
     * Alias pour children() - pour compatibilité avec le code existant
     */
    public function filleuls(): HasMany
    {
        return $this->children();
    }

    /**
     * Relation: Un Distributeur effectue plusieurs Achats.
     */
    public function achats(): HasMany
    {
        return $this->hasMany(Achat::class, 'distributeur_id', 'id');
    }

    /**
     * Relation: Un Distributeur a plusieurs entrées de niveau (toutes périodes confondues).
     */
    public function levelCurrents(): HasMany
    {
        return $this->hasMany(LevelCurrent::class, 'distributeur_id', 'id');
    }

    /**
     * Relation: Un Distributeur a UNE entrée de niveau pour la période courante.
     * Utilisé pour accéder facilement au niveau actuel.
     */
    public function levelCurrent(): HasOne
    {
        $currentPeriod = date('Y-m');
        return $this->hasOne(LevelCurrent::class, 'distributeur_id', 'id')
                    ->where('period', $currentPeriod);
    }

    /**
     * Relation: Pour une période spécifique
     */
    public function levelCurrentForPeriod(string $period): HasOne
    {
        return $this->hasOne(LevelCurrent::class, 'distributeur_id', 'id')
                    ->where('period', $period);
    }

    /**
     * Relation: Un Distributeur a plusieurs entrées d'historique de niveau.
     */
    public function levelHistories(): HasMany
    {
        return $this->hasMany(LevelCurrentHistory::class, 'distributeur_id', 'id');
    }

    /**
     * Relation: Un Distributeur peut avoir plusieurs enregistrements de Bonus.
     */
    public function bonuses(): HasMany
    {
        return $this->hasMany(Bonus::class, 'distributeur_id', 'id');
    }

    /**
     * Relation: Un Distributeur a plusieurs enregistrements d'historique d'avancement.
     */
    public function avancementHistory(): HasMany
    {
        return $this->hasMany(AvancementHistory::class, 'distributeur_id', 'id');
    }

    // --- Accesseurs ---

    /**
     * Obtient le nom complet du distributeur.
     */
    public function getFullNameAttribute(): string
    {
        return trim("{$this->pnom_distributeur} {$this->nom_distributeur}");
    }

    /**
     * Obtient le grade actuel (alias pour etoiles_id)
     */
    public function getGradeAttribute(): int
    {
        return $this->etoiles_id ?? 1;
    }

    /**
     * Obtient le niveau actuel depuis la relation levelCurrent
     * Retourne null si aucun niveau pour la période courante
     */
    public function getCurrentLevelAttribute(): ?LevelCurrent
    {
        return $this->levelCurrent;
    }

    /**
     * Obtient le cumul individuel de la période courante
     */
    public function getCumulIndividuelAttribute(): float
    {
        return $this->levelCurrent?->cumul_individuel ?? 0;
    }

    /**
     * Obtient le cumul collectif de la période courante
     */
    public function getCumulCollectifAttribute(): float
    {
        return $this->levelCurrent?->cumul_collectif ?? 0;
    }

    // --- Méthodes utilitaires ---

    /**
     * Récupère les avancements pour une période spécifique
     */
    public function getAvancementsForPeriod(string $period)
    {
        return $this->avancementHistory()->forPeriod($period)->get();
    }

    /**
     * Récupère le dernier avancement
     */
    public function getLastAdvancement()
    {
        return $this->avancementHistory()->latest('date_avancement')->first();
    }

    /**
     * Vérifie si le distributeur a des enfants
     */
    public function hasChildren(): bool
    {
        return $this->children()->exists();
    }

    /**
     * Compte le nombre total de descendants (récursif)
     */
    public function getTotalDescendantsCount(): int
    {
        $count = 0;
        $children = $this->children;

        foreach ($children as $child) {
            $count++;
            $count += $child->getTotalDescendantsCount();
        }

        return $count;
    }

    /**
     * Récupère le niveau pour une période spécifique
     */
    public function getLevelForPeriod(string $period): ?LevelCurrent
    {
        return $this->levelCurrents()->where('period', $period)->first();
    }

    /**
     * Vérifie si le distributeur est actif pour une période
     */
    public function isActiveForPeriod(string $period): bool
    {
        return $this->achats()->where('period', $period)->exists();
    }

    /**
     * Scope pour filtrer les distributeurs actifs
     */
    public function scopeActive($query)
    {
        return $query->where('statut_validation_periode', true);
    }

    /**
     * Scope pour filtrer par grade
     */
    public function scopeByGrade($query, int $grade)
    {
        return $query->where('etoiles_id', $grade);
    }

    /**
     * Scope pour filtrer par période avec level_current
     */
    public function scopeWithLevelForPeriod($query, string $period)
    {
        return $query->with(['levelCurrents' => function($q) use ($period) {
            $q->where('period', $period);
        }]);
    }
}
