<?php

namespace App\Contracts;

/**
 * Interface pour les services de cache
 */
interface CacheServiceInterface
{
    /**
     * Cache avec mécanisme de rappel
     *
     * @param string $key
     * @param int $ttl
     * @param callable $callback
     * @param array $tags
     * @return mixed
     */
    public function remember(string $key, int $ttl, callable $callback, array $tags = []);

    /**
     * Invalide le cache pour des tags spécifiques
     *
     * @param array $tags
     * @return void
     */
    public function invalidateTags(array $tags): void;

    /**
     * Invalide un cache spécifique
     *
     * @param string $key
     * @return void
     */
    public function forget(string $key): void;

    /**
     * Cache warming pour les données critiques
     *
     * @param string $period
     * @return void
     */
    public function warmCache(string $period): void;
}
