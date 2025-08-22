<?php

namespace App\Services;

use App\Models\Distributeur;
use App\Models\LevelCurrent;
use App\Models\Achat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class NetworkStatsService
{
    /**
     * Obtient les statistiques réseau d'un distributeur
     */
    public function getStats(int $distributeurId, string $period): array
    {
        $cacheKey = "network_stats_{$distributeurId}_{$period}";

        return Cache::remember($cacheKey, 3600, function () use ($distributeurId, $period) {
            return [
                'direct_count' => $this->getDirectCount($distributeurId),
                'total_count' => $this->getTotalNetworkCount($distributeurId),
                'active_count' => $this->getActiveCount($distributeurId, $period),
                'new_this_month' => $this->getNewThisMonth($distributeurId, $period),
                'network_volume' => $this->getNetworkVolume($distributeurId, $period),
                'network_points' => $this->getNetworkPoints($distributeurId, $period)
            ];
        });
    }

    /**
     * Compte les filleuls directs
     */
    public function getDirectCount(int $distributeurId): int
    {
        return Distributeur::where('id_distrib_parent', $distributeurId)->count();
    }

    /**
     * Compte le réseau total (récursif)
     */
    public function getTotalNetworkCount(int $distributeurId): int
    {
        return DB::table(DB::raw('(
            WITH RECURSIVE network AS (
                SELECT id
                FROM distributeurs
                WHERE id_distrib_parent = ' . $distributeurId . '

                UNION ALL

                SELECT d.id
                FROM distributeurs d
                INNER JOIN network n ON d.id_distrib_parent = n.id
            )
            SELECT COUNT(*) as count FROM network
        ) as result'))->value('count');
    }

    /**
     * Compte les membres actifs pour une période
     */
    public function getActiveCount(int $distributeurId, string $period): int
    {
        $networkIds = $this->getNetworkIds($distributeurId);

        return DB::table('distributeurs as d')
            ->join('achats as a', 'd.id', '=', 'a.distributeur_id')
            ->whereIn('d.id', $networkIds)
            ->where('a.period', $period)
            ->where('a.status', 'validated')
            ->distinct('d.id')
            ->count('d.id');
    }

    /**
     * Compte les nouveaux membres du mois
     */
    public function getNewThisMonth(int $distributeurId, string $period): int
    {
        $startDate = $period . '-01';
        $endDate = date('Y-m-t', strtotime($startDate));

        $networkIds = $this->getNetworkIds($distributeurId);

        return Distributeur::whereIn('id', $networkIds)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();
    }

    /**
     * Calcule le volume total du réseau
     */
    public function getNetworkVolume(int $distributeurId, string $period): float
    {
        $networkIds = $this->getNetworkIds($distributeurId);

        return Achat::whereIn('distributeur_id', $networkIds)
            ->where('period', $period)
            ->where('status', 'validated')
            ->sum('montant_total_ligne');
    }

    /**
     * Calcule les points totaux du réseau
     */
    public function getNetworkPoints(int $distributeurId, string $period): int
    {
        $networkIds = $this->getNetworkIds($distributeurId);

        return LevelCurrent::whereIn('distributeur_id', $networkIds)
            ->where('period', $period)
            ->sum('pv');
    }

    /**
     * Obtient les IDs de tous les membres du réseau
     */
    protected function getNetworkIds(int $distributeurId): array
    {
        $cacheKey = "network_ids_{$distributeurId}";

        return Cache::remember($cacheKey, 3600, function () use ($distributeurId) {
            return DB::table(DB::raw('(
                WITH RECURSIVE network AS (
                    SELECT id
                    FROM distributeurs
                    WHERE id_distrib_parent = ' . $distributeurId . '

                    UNION ALL

                    SELECT d.id
                    FROM distributeurs d
                    INNER JOIN network n ON d.id_distrib_parent = n.id
                )
                SELECT id FROM network
            ) as network_members'))
                ->pluck('id')
                ->toArray();
        });
    }

    /**
     * Invalide le cache pour un distributeur
     */
    public function invalidateCache(int $distributeurId): void
    {
        Cache::forget("network_ids_{$distributeurId}");

        // Invalider aussi pour toutes les périodes récentes
        $periods = DB::table('system_periods')
            ->orderBy('period', 'desc')
            ->limit(6)
            ->pluck('period');

        foreach ($periods as $period) {
            Cache::forget("network_stats_{$distributeurId}_{$period}");
        }
    }
}
