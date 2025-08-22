<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Contracts\CacheServiceInterface;
use App\Services\CacheService;
use App\Services\SharedHostingCacheService;
use App\Services\PerformanceMonitoringService;
use App\Services\SharedHostingPerformanceService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Enregistrer l'interface CacheServiceInterface
        if (config('cache.default') !== 'redis') {
            // Utiliser SharedHostingCacheService pour l'hébergement mutualisé
            $this->app->bind(CacheServiceInterface::class, SharedHostingCacheService::class);
        } else {
            // Utiliser CacheService standard pour Redis
            $this->app->bind(CacheServiceInterface::class, CacheService::class);
        }

        // Pour la compatibilité avec le code existant qui utilise directement les classes
        $this->app->bind(CacheService::class, function ($app) {
            return $app->make(CacheServiceInterface::class);
        });

        // Gestion du service de monitoring de performance
        if (config('cache.default') !== 'redis') {
            $this->app->bind(PerformanceMonitoringService::class, SharedHostingPerformanceService::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
