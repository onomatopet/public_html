<?php
// app/Console/Commands/WarmDashboardCache.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\CacheService;
use App\Services\SharedHostingCacheService;
use App\Models\SystemPeriod;

class WarmDashboardCache extends Command
{
    protected $signature = 'mlm:warm-cache {period?}';
    protected $description = 'Précharge le cache pour les tableaux de bord';

    protected $cacheService;

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        // Récupérer le service de cache approprié
        $this->cacheService = app()->make(
            config('cache.default') === 'redis'
                ? CacheService::class
                : SharedHostingCacheService::class
        );

        $period = $this->argument('period') ?? SystemPeriod::getCurrentPeriod()?->period;

        if (!$period) {
            $this->error('Aucune période spécifiée');
            return 1;
        }

        $this->info("Préchauffage du cache pour la période: {$period}");

        $this->cacheService->warmCache($period);

        $this->info("Cache préchauffé avec succès!");

        return 0;
    }
}
