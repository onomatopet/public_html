<?php
// app/Services/SharedHostingCacheService.php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SharedHostingCacheService
{
    // Durées de cache
    const TTL_SHORT = 300;      // 5 minutes
    const TTL_MEDIUM = 3600;    // 1 heure
    const TTL_LONG = 86400;     // 24 heures

    // Préfixes de cache
    const PREFIX_STATS = 'mlm_stats:';
    const PREFIX_HIERARCHY = 'mlm_hierarchy:';
    const PREFIX_PERFORMANCE = 'mlm_performance:';
    const PREFIX_DASHBOARD = 'mlm_dashboard:';

    /**
     * Cache simple avec fallback database
     */
    public function remember(string $key, int $ttl, callable $callback, array $tags = [])
    {
        // Utiliser le cache Laravel (file ou database)
        $cacheKey = $this->buildKey($key, $tags);

        return Cache::remember($cacheKey, $ttl, $callback);
    }

    /**
     * Alternative : Utiliser une table de cache personnalisée
     */
    public function rememberInDatabase(string $key, int $ttl, callable $callback)
    {
        // Vérifier si existe dans la base
        $cached = DB::table('cache_custom')
                    ->where('key', $key)
                    ->where('expiration', '>', now())
                    ->first();

        if ($cached) {
            return unserialize($cached->value);
        }

        // Calculer la valeur
        $value = $callback();

        // Stocker dans la base
        DB::table('cache_custom')->updateOrInsert(
            ['key' => $key],
            [
                'value' => serialize($value),
                'expiration' => now()->addSeconds($ttl),
                'created_at' => now()
            ]
        );

        return $value;
    }

    /**
     * Construire une clé unique
     */
    protected function buildKey(string $key, array $tags): string
    {
        if (empty($tags)) {
            return $key;
        }
        return implode(':', $tags) . ':' . $key;
    }

    /**
     * Nettoyer le cache expiré
     */
    public function cleanExpiredCache(): void
    {
        if (config('cache.default') === 'database') {
            DB::table('cache')->where('expiration', '<', now()->timestamp)->delete();
        }

        // Nettoyer notre table custom si elle existe
        if (Schema::hasTable('cache_custom')) {
            DB::table('cache_custom')->where('expiration', '<', now())->delete();
        }
    }

    /**
     * Invalider par tags (version simplifiée)
     */
    public function invalidateTags(array $tags): void
    {
        $pattern = implode(':', $tags) . ':*';

        if (config('cache.default') === 'database') {
            DB::table('cache')->where('key', 'like', $pattern . '%')->delete();
        }
    }
}
