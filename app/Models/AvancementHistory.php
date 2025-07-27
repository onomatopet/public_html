<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modèle pour l'historique des avancements de grade des distributeurs
 *
 * @property int $id
 * @property int $distributeur_id
 * @property string $period
 * @property int $ancien_grade
 * @property int $nouveau_grade
 * @property string $type_calcul
 * @property \Illuminate\Support\Carbon $date_avancement
 * @property string|null $details
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @property-read Distributeur $distributeur
 */
class AvancementHistory extends Model
{
    use HasFactory;

    protected $table = 'avancement_history';

    protected $fillable = [
        'distributeur_id',
        'period',
        'ancien_grade',
        'nouveau_grade',
        'type_calcul',
        'date_avancement',
        'details',
    ];

    protected $casts = [
        'id' => 'integer',
        'distributeur_id' => 'integer',
        'ancien_grade' => 'integer',
        'nouveau_grade' => 'integer',
        'date_avancement' => 'datetime',
        'details' => 'array', // Cast automatique JSON
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relation: Un avancement appartient à un distributeur
     */
    public function distributeur(): BelongsTo
    {
        return $this->belongsTo(Distributeur::class, 'distributeur_id', 'id');
    }

    /**
     * Scope pour filtrer par période
     */
    public function scopeForPeriod($query, string $period)
    {
        return $query->where('period', $period);
    }

    /**
     * Scope pour filtrer par type de calcul
     */
    public function scopeForCalculType($query, string $type)
    {
        return $query->where('type_calcul', $type);
    }

    /**
     * Scope pour les avancements récents
     */
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('date_avancement', '>=', now()->subDays($days));
    }

    /**
     * Accesseur pour calculer la progression
     */
    public function getProgressionAttribute(): int
    {
        return $this->nouveau_grade - $this->ancien_grade;
    }

    /**
     * Accesseur pour le libellé du type de calcul
     */
    public function getTypeCalculLabelAttribute(): string
    {
        return match($this->type_calcul) {
            'normal' => 'Calcul normal (tous distributeurs)',
            'validated_only' => 'Achats validés uniquement',
            default => 'Type inconnu'
        };
    }

    /**
     * Méthode statique pour créer un enregistrement d'avancement
     */
    public static function createAdvancement(
        int $distributeurId,
        string $period,
        int $ancienGrade,
        int $nouveauGrade,
        string $typeCalcul = 'normal',
        array $details = null
    ): self {
        return self::create([
            'distributeur_id' => $distributeurId,
            'period' => $period,
            'ancien_grade' => $ancienGrade,
            'nouveau_grade' => $nouveauGrade,
            'type_calcul' => $typeCalcul,
            'date_avancement' => now(),
            'details' => $details,
        ]);
    }

    /**
     * Méthode statique pour obtenir les stats d'avancements pour une période
     */
    public static function getStatsForPeriod(string $period): array
    {
        $stats = self::forPeriod($period)
            ->selectRaw('
                COUNT(*) as total_avancements,
                COUNT(CASE WHEN type_calcul = "normal" THEN 1 END) as calcul_normal,
                COUNT(CASE WHEN type_calcul = "validated_only" THEN 1 END) as calcul_validated_only,
                AVG(nouveau_grade - ancien_grade) as progression_moyenne,
                MAX(nouveau_grade - ancien_grade) as progression_max
            ')
            ->first();

        return [
            'total_avancements' => $stats->total_avancements ?? 0,
            'calcul_normal' => $stats->calcul_normal ?? 0,
            'calcul_validated_only' => $stats->calcul_validated_only ?? 0,
            'progression_moyenne' => round($stats->progression_moyenne ?? 0, 2),
            'progression_max' => $stats->progression_max ?? 0,
        ];
    }
}
