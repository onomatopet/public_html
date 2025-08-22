<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TempGradeCalculation extends Model
{
    protected $table = 'temp_grade_calculations';

    protected $fillable = [
        'calculation_session_id',
        'period',
        'distributeur_id',
        'matricule',
        'level_current_id',
        'grade_initial',
        'grade_actuel',
        'grade_precedent',
        'cumul_individuel',
        'cumul_collectif',
        'pass_number',
        'qualification_method',
        'promoted',
        'promotion_history'
    ];

    protected $casts = [
        'promotion_history' => 'array',
        'promoted' => 'boolean',
        'cumul_individuel' => 'decimal:2',
        'cumul_collectif' => 'decimal:2'
    ];

    /**
     * Relation avec le distributeur
     */
    public function distributeur()
    {
        return $this->belongsTo(Distributeur::class, 'distributeur_id');
    }

    /**
     * Scope pour filtrer par session
     */
    public function scopeForSession($query, string $sessionId)
    {
        return $query->where('calculation_session_id', $sessionId);
    }

    /**
     * Scope pour filtrer les promus
     */
    public function scopePromoted($query)
    {
        return $query->where('promoted', true);
    }

    /**
     * Scope pour une période donnée
     */
    public function scopeForPeriod($query, string $period)
    {
        return $query->where('period', $period);
    }

    /**
     * Ajoute une promotion à l'historique
     */
    public function addPromotionToHistory(int $fromGrade, int $toGrade, int $passNumber, string $method)
    {
        $history = $this->promotion_history ?? [];
        $history[] = [
            'pass' => $passNumber,
            'from' => $fromGrade,
            'to' => $toGrade,
            'method' => $method,
            'timestamp' => now()->toISOString()
        ];
        $this->promotion_history = $history;
    }

    /**
     * Vérifie si a été promu dans une passe spécifique
     */
    public function wasPromotedInPass(int $passNumber): bool
    {
        if (!$this->promotion_history) return false;

        return collect($this->promotion_history)->contains('pass', $passNumber);
    }

    /**
     * Obtient le grade avant une passe donnée
     */
    public function getGradeBeforePass(int $passNumber): int
    {
        if (!$this->promotion_history || count($this->promotion_history) === 0) {
            return $this->grade_initial;
        }

        $previousPromotions = collect($this->promotion_history)
            ->filter(function ($promo) use ($passNumber) {
                return $promo['pass'] < $passNumber;
            })
            ->sortBy('pass');

        if ($previousPromotions->isEmpty()) {
            return $this->grade_initial;
        }

        return $previousPromotions->last()['to'];
    }
}
