<?php
// app/Services/PerformanceMonitoringService.php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use App\Models\LevelCurrent;
use App\Models\Achat;
use App\Models\Distributeur;

class PerformanceMonitoringService
{
    protected CacheService $cache;

    public function __construct(CacheService $cache)
    {
        $this->cache = $cache;
    }

    /**
     * Collecte toutes les métriques de performance
     */
    public function collectMetrics(string $period): array
    {
        $startTime = microtime(true);

        $metrics = [
            'timestamp' => now()->toISOString(),
            'period' => $period,
            'database' => $this->getDatabaseMetrics(),
            'business' => $this->getBusinessMetrics($period),
            'system' => $this->getSystemMetrics(),
            'performance' => []
        ];

        $metrics['performance']['collection_time'] = round(microtime(true) - $startTime, 3);

        // Stocker dans Redis pour l'historique
        $this->storeMetricsHistory($metrics);

        return $metrics;
    }

    /**
     * Métriques de base de données
     */
    protected function getDatabaseMetrics(): array
    {
        $metrics = [];

        // Taille des tables principales
        $tables = ['distributeurs', 'achats', 'level_currents', 'bonuses'];
        foreach ($tables as $table) {
            $result = DB::select("SELECT COUNT(*) as count FROM {$table}")[0];
            $metrics['table_sizes'][$table] = $result->count;
        }

        // Requêtes lentes (si le slow query log est activé)
        $metrics['slow_queries'] = $this->getSlowQueries();

        // Statistiques de connexion
        $metrics['connections'] = [
            'active' => DB::select("SHOW STATUS LIKE 'Threads_connected'")[0]->Value ?? 0,
            'max_used' => DB::select("SHOW STATUS LIKE 'Max_used_connections'")[0]->Value ?? 0
        ];

        return $metrics;
    }

    /**
     * Métriques métier
     */
    protected function getBusinessMetrics(string $period): array
    {
        return $this->cache->remember(
            CacheService::PREFIX_PERFORMANCE . "business:{$period}",
            CacheService::TTL_SHORT,
            function() use ($period) {
                return [
                    'active_distributors' => LevelCurrent::where('period', $period)
                                                        ->where('new_cumul', '>', 0)
                                                        ->count(),
                    'total_sales_points' => LevelCurrent::where('period', $period)
                                                       ->sum('new_cumul'),
                    'average_grade' => round(LevelCurrent::where('period', $period)
                                                        ->avg('etoiles'), 2),
                    'grade_distribution' => $this->getGradeDistribution($period),
                    'purchase_velocity' => $this->getPurchaseVelocity($period),
                    'hierarchy_depth' => $this->getMaxHierarchyDepth()
                ];
            },
            ['business', "period:{$period}"]
        );
    }

    /**
     * Métriques système
     */
    protected function getSystemMetrics(): array
    {
        return [
            'memory' => [
                'current' => round(memory_get_usage(true) / 1048576, 2), // MB
                'peak' => round(memory_get_peak_usage(true) / 1048576, 2) // MB
            ],
            'cache' => [
                'redis_memory' => $this->getRedisMemoryUsage(),
                'hit_rate' => $this->getCacheHitRate()
            ],
            'queue' => [
                'pending_jobs' => $this->getPendingJobsCount(),
                'failed_jobs' => DB::table('failed_jobs')->count()
            ]
        ];
    }

    /**
     * Distribution des grades
     */
    protected function getGradeDistribution(string $period): array
    {
        return LevelCurrent::where('period', $period)
                         ->groupBy('etoiles')
                         ->selectRaw('etoiles, COUNT(*) as count')
                         ->orderBy('etoiles')
                         ->pluck('count', 'etoiles')
                         ->toArray();
    }

    /**
     * Vélocité des achats (achats par heure sur les dernières 24h)
     */
    protected function getPurchaseVelocity(string $period): array
    {
        $velocity = [];
        $now = now();

        for ($i = 23; $i >= 0; $i--) {
            $hourStart = $now->copy()->subHours($i);
            $hourEnd = $hourStart->copy()->addHour();

            $count = Achat::where('period', $period)
                        ->whereBetween('created_at', [$hourStart, $hourEnd])
                        ->count();

            $velocity[$hourStart->format('H:00')] = $count;
        }

        return $velocity;
    }

    /**
     * Profondeur maximale de la hiérarchie
     */
    protected function getMaxHierarchyDepth(): int
    {
        return $this->cache->remember(
            CacheService::PREFIX_PERFORMANCE . 'max_hierarchy_depth',
            CacheService::TTL_LONG,
            function() {
                $maxDepth = 0;
                $distributors = Distributeur::whereNull('id_distrib_parent')->pluck('id');

                foreach ($distributors as $rootId) {
                    $depth = $this->calculateDepth($rootId, 0);
                    $maxDepth = max($maxDepth, $depth);
                }

                return $maxDepth;
            }
        );
    }

    protected function calculateDepth(int $distributorId, int $currentDepth): int
    {
        if ($currentDepth > 50) return $currentDepth; // Protection

        $children = Distributeur::where('id_distrib_parent', $distributorId)->pluck('id');
        if ($children->isEmpty()) {
            return $currentDepth;
        }

        $maxChildDepth = $currentDepth;
        foreach ($children as $childId) {
            $childDepth = $this->calculateDepth($childId, $currentDepth + 1);
            $maxChildDepth = max($maxChildDepth, $childDepth);
        }

        return $maxChildDepth;
    }

    /**
     * Requêtes lentes
     */
    protected function getSlowQueries(): array
    {
        // Simulé pour l'exemple - en production, parser le slow query log
        return [
            'count' => 0,
            'threshold_ms' => 1000
        ];
    }

    /**
     * Usage mémoire Redis
     */
    protected function getRedisMemoryUsage(): array
    {
        try {
            $info = Redis::info('memory');
            return [
                'used_memory_mb' => round($info['used_memory'] / 1048576, 2),
                'used_memory_peak_mb' => round($info['used_memory_peak'] / 1048576, 2)
            ];
        } catch (\Exception $e) {
            return ['error' => 'Unable to fetch Redis metrics'];
        }
    }

    /**
     * Taux de hit du cache
     */
    protected function getCacheHitRate(): float
    {
        // Implémenter selon votre système de tracking
        // Pour l'exemple, on retourne une valeur simulée
        return 85.5;
    }

    /**
     * Nombre de jobs en attente
     */
    protected function getPendingJobsCount(): int
    {
        try {
            return Redis::llen('queues:default');
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Stocke l'historique des métriques
     */
    protected function storeMetricsHistory(array $metrics): void
    {
        $key = 'metrics_history:' . now()->format('Y-m-d:H');
        Redis::lpush($key, json_encode($metrics));
        Redis::expire($key, 86400 * 7); // Garder 7 jours
        Redis::ltrim($key, 0, 59); // Garder max 60 entrées par heure
    }

    /**
     * Récupère l'historique des métriques
     */
    public function getMetricsHistory(int $hours = 24): array
    {
        $history = [];
        $now = now();

        for ($i = 0; $i < $hours; $i++) {
            $hour = $now->copy()->subHours($i);
            $key = 'metrics_history:' . $hour->format('Y-m-d:H');

            $data = Redis::lrange($key, 0, -1);
            foreach ($data as $json) {
                $history[] = json_decode($json, true);
            }
        }

        return array_reverse($history);
    }
}
