<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

class CacheService
{
    // Durées de cache par défaut (en secondes)
    const TTL_SHORT = 300;      // 5 minutes
    const TTL_MEDIUM = 3600;    // 1 heure
    const TTL_LONG = 86400;     // 24 heures

    // Préfixes de cache
    const PREFIX_STATS = 'mlm_stats:';
    const PREFIX_HIERARCHY = 'mlm_hierarchy:';
    const PREFIX_PERFORMANCE = 'mlm_performance:';
    const PREFIX_DASHBOARD = 'mlm_dashboard:';

    /**
     * Cache avec Redis uniquement
     */
    public function remember(string $key, int $ttl, callable $callback, array $tags = [])
    {
        // Utiliser uniquement Redis/Cache Laravel
        return Cache::tags($tags)->remember($key, $ttl, $callback);
    }

    /**
     * Invalide le cache pour des tags spécifiques
     */
    public function invalidateTags(array $tags): void
    {
        Cache::tags($tags)->flush();
        Log::info("Cache invalidated for tags: " . implode(', ', $tags));
    }

    /**
     * Invalide un cache spécifique
     */
    public function forget(string $key): void
    {
        Cache::forget($key);
    }

    /**
     * Cache warming pour les données critiques
     */
    public function warmCache(string $period): void
    {
        Log::info("Starting cache warming for period: {$period}");

        // Préchauffer les statistiques globales
        $this->warmGlobalStats($period);

        // Préchauffer les top performers
        $this->warmTopPerformers($period);

        // Préchauffer les métriques de performance
        $this->warmPerformanceMetrics($period);

        Log::info("Cache warming completed for period: {$period}");
    }

    protected function warmGlobalStats(string $period): void
    {
        $key = self::PREFIX_STATS . "global:{$period}";
        $this->remember($key, self::TTL_MEDIUM, function() use ($period) {
            return app(DashboardService::class)->getGlobalStats($period);
        }, ['stats', "period:{$period}"]);
    }

    protected function warmTopPerformers(string $period): void
    {
        $key = self::PREFIX_STATS . "top_performers:{$period}";
        $this->remember($key, self::TTL_MEDIUM, function() use ($period) {
            return app(DashboardService::class)->getTopPerformers($period);
        }, ['stats', "period:{$period}"]);
    }

    protected function warmPerformanceMetrics(string $period): void
    {
        $key = self::PREFIX_PERFORMANCE . "metrics:{$period}";
        $this->remember($key, self::TTL_SHORT, function() use ($period) {
            return app(PerformanceMonitoringService::class)->collectMetrics($period);
        }, ['performance', "period:{$period}"]);
    }
}
