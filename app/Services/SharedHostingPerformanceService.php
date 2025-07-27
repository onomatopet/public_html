<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\LevelCurrent;
use App\Models\Achat;
use App\Models\Distributeur;

class SharedHostingPerformanceService
{
    protected SharedHostingCacheService $cache;

    public function __construct(SharedHostingCacheService $cache)
    {
        $this->cache = $cache;
    }

    /**
     * Collecter les métriques sans Redis
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

        // Stocker dans la base de données
        $this->storeMetricsInDatabase($metrics);

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
            try {
                $result = DB::select("SELECT COUNT(*) as count FROM {$table}")[0] ?? null;
                $metrics['table_sizes'][$table] = $result ? $result->count : 0;
            } catch (\Exception $e) {
                $metrics['table_sizes'][$table] = 0;
            }
        }

        // Statistiques de connexion (adaptées pour hébergement mutualisé)
        $metrics['connections'] = [
            'status' => 'limited',
            'info' => 'Shared hosting - connection pooling active'
        ];

        return $metrics;
    }

    /**
     * Métriques métier
     */
    protected function getBusinessMetrics(string $period): array
    {
        return $this->cache->remember(
            SharedHostingCacheService::PREFIX_PERFORMANCE . "business:{$period}",
            SharedHostingCacheService::TTL_SHORT,
            function() use ($period) {
                return [
                    'active_distributors' => LevelCurrent::where('period', $period)
                                                        ->where('new_cumul', '>', 0)
                                                        ->count(),
                    'total_sales_points' => LevelCurrent::where('period', $period)
                                                       ->sum('new_cumul'),
                    'average_grade' => round(LevelCurrent::where('period', $period)
                                                        ->avg('etoiles') ?? 0, 2),
                    'total_distributors' => Distributeur::count(),
                    'purchases_count' => Achat::where('period', $period)->count(),
                    'purchases_total' => Achat::where('period', $period)->sum('montant_total_ligne')
                ];
            }
        );
    }

    /**
     * Métriques système adaptées
     */
    protected function getSystemMetrics(): array
    {
        return [
            'memory' => [
                'current' => round(memory_get_usage(true) / 1048576, 2), // MB
                'peak' => round(memory_get_peak_usage(true) / 1048576, 2), // MB
                'limit' => ini_get('memory_limit')
            ],
            'execution_time' => [
                'max' => ini_get('max_execution_time'),
                'current' => round(microtime(true) - ($_SERVER["REQUEST_TIME_FLOAT"] ?? microtime(true)), 2)
            ],
            'cache' => [
                'driver' => config('cache.default'),
                'size' => $this->getCacheSize()
            ],
            'queue' => [
                'pending_jobs' => $this->getQueueJobsCount(),
                'failed_jobs' => $this->getFailedJobsCount()
            ]
        ];
    }

    /**
     * Nombre de jobs en attente
     */
    protected function getQueueJobsCount(): int
    {
        try {
            return DB::table('jobs')->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Nombre de jobs échoués
     */
    protected function getFailedJobsCount(): int
    {
        try {
            return DB::table('failed_jobs')->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Taille du cache (approximative)
     */
    protected function getCacheSize(): string
    {
        if (config('cache.default') === 'file') {
            $cacheDir = storage_path('framework/cache/data');
            $size = 0;

            if (is_dir($cacheDir)) {
                try {
                    foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($cacheDir)) as $file) {
                        if ($file->isFile()) {
                            $size += $file->getSize();
                        }
                    }
                } catch (\Exception $e) {
                    // Ignorer les erreurs de lecture
                }
            }

            return round($size / 1048576, 2) . ' MB';
        } elseif (config('cache.default') === 'database') {
            try {
                $count = DB::table('cache')->count();
                return $count . ' entries';
            } catch (\Exception $e) {
                return '0 entries';
            }
        }

        return 'N/A';
    }

    /**
     * Stocker les métriques dans la base
     */
    protected function storeMetricsInDatabase(array $metrics): void
    {
        try {
            // Vérifier si la table existe
            if (!\Schema::hasTable('performance_metrics')) {
                Log::warning('Table performance_metrics does not exist');
                return;
            }

            DB::table('performance_metrics')->insert([
                'period' => $metrics['period'],
                'metrics' => json_encode($metrics),
                'created_at' => now()
            ]);

            // Garder seulement 7 jours d'historique
            DB::table('performance_metrics')
                ->where('created_at', '<', now()->subDays(7))
                ->delete();
        } catch (\Exception $e) {
            Log::error('Failed to store metrics: ' . $e->getMessage());
        }
    }

    /**
     * Récupérer l'historique depuis la base
     */
    public function getMetricsHistory(int $hours = 24): array
    {
        try {
            // Vérifier si la table existe
            if (!\Schema::hasTable('performance_metrics')) {
                return [];
            }

            return DB::table('performance_metrics')
                ->where('created_at', '>=', now()->subHours($hours))
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($row) {
                    return json_decode($row->metrics, true);
                })
                ->toArray();
        } catch (\Exception $e) {
            Log::error('Failed to get metrics history: ' . $e->getMessage());
            return [];
        }
    }
}
