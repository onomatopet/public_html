<?php
// app/Providers/AppServiceProvider.php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
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
        // Utiliser les versions SharedHosting si on n'est pas en production avec Redis
        if (config('cache.default') !== 'redis') {
            $this->app->bind(CacheService::class, SharedHostingCacheService::class);
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
