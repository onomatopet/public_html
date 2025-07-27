<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Services\PerformanceMonitoringService;
use App\Models\SystemPeriod;
use Illuminate\Support\Facades\DB;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Collecter les métriques toutes les 5 minutes
        $schedule->call(function () {
            $period = SystemPeriod::getCurrentPeriod()?->period;
            if ($period) {
                app(PerformanceMonitoringService::class)->collectMetrics($period);
            }
        })->everyFiveMinutes()->name('collect-metrics')->withoutOverlapping();

        // Préchauffer le cache toutes les heures
        $schedule->command('mlm:warm-cache')
                 ->hourly()
                 ->name('warm-cache')
                 ->withoutOverlapping();

        // Agrégation batch quotidienne (à 2h du matin)
        $schedule->command('mlm:aggregate-batch')
                 ->dailyAt('02:00')
                 ->name('daily-aggregation')
                 ->withoutOverlapping();

        // Nettoyage des métriques anciennes (hebdomadaire)
        $schedule->call(function () {
            // Garder seulement 30 jours d'historique
            $cutoff = now()->subDays(30);
            DB::table('performance_metrics')
              ->where('created_at', '<', $cutoff)
              ->delete();
        })->weekly()->name('cleanup-metrics');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
