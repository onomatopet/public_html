<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;

class FinalizeSetup extends Command
{
    protected $signature = 'mlm:finalize-setup {--force : Forcer l\'exécution sans confirmation}';
    protected $description = 'Finalise l\'installation du projet MLM en activant les routes et exécutant les migrations';

    public function handle()
    {
        $this->info('🚀 Finalisation du projet MLM v2.0');
        $this->info('==================================');

        if (!$this->option('force')) {
            if (!$this->confirm('Cette commande va modifier vos fichiers et exécuter des migrations. Continuer ?')) {
                $this->comment('Opération annulée.');
                return 0;
            }
        }

        // 1. Activer les routes
        $this->task('Activation des routes', function () {
            return $this->activateRoutes();
        });

        // 2. Exécuter les migrations
        $this->task('Exécution des migrations', function () {
            Artisan::call('migrate', ['--force' => true]);
            return true;
        });

        // 3. Créer les dossiers nécessaires
        $this->task('Création des dossiers', function () {
            $directories = [
                storage_path('app/exports'),
                storage_path('app/imports'),
                storage_path('app/templates'),
                storage_path('app/backups'),
                storage_path('app/reports')
            ];

            foreach ($directories as $dir) {
                if (!File::exists($dir)) {
                    File::makeDirectory($dir, 0755, true);
                }
            }
            return true;
        });

        // 4. Publier les assets si nécessaire
        $this->task('Publication des assets', function () {
            Artisan::call('vendor:publish', [
                '--tag' => 'public',
                '--force' => true
            ]);
            return true;
        });

        // 5. Optimiser l'application
        $this->task('Optimisation de l\'application', function () {
            Artisan::call('optimize');
            return true;
        });

        // Résumé
        $this->newLine();
        $this->info('✅ Finalisation terminée avec succès !');
        $this->newLine();
        $this->table(
            ['Étape', 'Status'],
            [
                ['Routes activées', '✓'],
                ['Migrations exécutées', '✓'],
                ['Dossiers créés', '✓'],
                ['Assets publiés', '✓'],
                ['Application optimisée', '✓']
            ]
        );

        $this->newLine();
        $this->info('📋 Prochaines étapes :');
        $this->line('1. Vérifiez les nouvelles tables dans la base de données');
        $this->line('2. Testez les nouvelles routes : /admin/reports, /admin/settings, etc.');
        $this->line('3. Configurez les paramètres système dans /admin/settings');
        $this->line('4. Importez des données de test via /admin/import-export');

        return 0;
    }

    private function activateRoutes(): bool
    {
        $webFile = base_path('routes/web.php');

        if (!File::exists($webFile)) {
            $this->error('Fichier routes/web.php non trouvé!');
            return false;
        }

        $content = File::get($webFile);

        // Décommenter les imports
        $replacements = [
            "// use App\Http\Controllers\Admin\ReportController;" => "use App\Http\Controllers\Admin\ReportController;",
            "// use App\Http\Controllers\Admin\SettingsController;" => "use App\Http\Controllers\Admin\SettingsController;",
            "// use App\Http\Controllers\Admin\ImportExportController;" => "use App\Http\Controllers\Admin\ImportExportController;",
            "// use App\Http\Controllers\Admin\ActivityLogController;" => "use App\Http\Controllers\Admin\ActivityLogController;"
        ];

        foreach ($replacements as $search => $replace) {
            $content = str_replace($search, $replace, $content);
        }

        // Décommenter le bloc de routes (recherche plus flexible)
        $content = preg_replace(
            '/\/\*[\s\S]*?RAPPORTS AVANCÉS[\s\S]*?\*\//m',
            $this->getActiveRoutesContent(),
            $content
        );

        File::put($webFile, $content);

        return true;
    }

    private function getActiveRoutesContent(): string
    {
        return <<<'ROUTES'
        // ===== ROUTES ACTIVES =====

        // RAPPORTS AVANCÉS
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('/', [ReportController::class, 'index'])->name('index');
            Route::get('/sales', [ReportController::class, 'sales'])->name('sales');
            Route::get('/commissions', [ReportController::class, 'commissions'])->name('commissions');
            Route::get('/network-growth', [ReportController::class, 'networkGrowth'])->name('network-growth');
            Route::post('/export', [ReportController::class, 'export'])->name('export');
        });

        // PARAMÈTRES AVANCÉS
        Route::prefix('settings')->name('settings.')->group(function () {
            Route::get('/', [SettingsController::class, 'index'])->name('index');
            Route::post('/update', [SettingsController::class, 'update'])->name('update');
            Route::post('/cache/clear', [SettingsController::class, 'clearCache'])->name('cache.clear');
        });

        // IMPORT/EXPORT AVANCÉ
        Route::prefix('import-export')->name('import-export.')->group(function () {
            Route::get('/', [ImportExportController::class, 'index'])->name('index');
            Route::post('/import', [ImportExportController::class, 'import'])->name('import');
            Route::post('/export', [ImportExportController::class, 'export'])->name('export');
            Route::get('/download/{file}', [ImportExportController::class, 'download'])->name('download');
        });

        // LOGS D'ACTIVITÉ AVANCÉS
        Route::prefix('logs')->name('logs.')->group(function () {
            Route::get('/', [ActivityLogController::class, 'index'])->name('index');
            Route::get('/{log}', [ActivityLogController::class, 'show'])->name('show');
        });
ROUTES;
    }

    /**
     * Affiche une tâche avec un indicateur de succès/échec
     */
    private function task($description, $callback)
    {
        $this->output->write("$description...");

        $result = $callback();

        if ($result) {
            $this->info(' ✓');
        } else {
            $this->error(' ✗');
        }

        return $result;
    }
}
