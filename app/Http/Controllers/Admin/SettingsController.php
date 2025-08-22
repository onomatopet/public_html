<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemConfig;
use App\Contracts\CacheServiceInterface;
use App\Services\BackupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

class SettingsController extends Controller
{
    protected CacheServiceInterface $cacheService;
    protected BackupService $backupService;

    public function __construct(CacheServiceInterface $cacheService, BackupService $backupService)
    {
        $this->cacheService = $cacheService;
        $this->backupService = $backupService;
    }

    /**
     * Affiche la page des paramètres
     */
    public function index()
    {
        // Récupérer les configurations système
        $settings = $this->getSystemSettings();

        // Statistiques du cache
        $cacheStats = $this->getCacheStatistics();

        // Informations système
        $systemInfo = $this->getSystemInfo();

        // État des services
        $servicesStatus = $this->getServicesStatus();

        return view('admin.settings.index', compact('settings', 'cacheStats', 'systemInfo', 'servicesStatus'));
    }

    /**
     * Met à jour les paramètres généraux
     */
    public function update(Request $request)
    {
        $request->validate([
            'app_name' => 'required|string|max:255',
            'app_url' => 'required|url',
            'timezone' => 'required|timezone',
            'locale' => 'required|string|size:2',
            'mlm_calculation_period' => 'required|integer|min:1|max:365',
            'mlm_min_advancement_threshold' => 'required|integer|min:0',
            'mlm_max_network_depth' => 'required|integer|min:1|max:20',
            'enable_auto_advancements' => 'boolean',
            'enable_realtime_bonus' => 'boolean',
            'maintenance_mode' => 'boolean',
            'cache_ttl' => 'required|integer|min:60|max:86400',
            'pagination_size' => 'required|integer|min:10|max:100'
        ]);

        DB::beginTransaction();
        try {
            // Mettre à jour les configurations
            foreach ($request->except(['_token', '_method']) as $key => $value) {
                $this->updateOrCreateSetting($key, $value);
            }

            // Mise à jour des fichiers de configuration si nécessaire
            $this->updateConfigFiles($request->only(['app_name', 'app_url', 'timezone', 'locale']));

            // Vider le cache de configuration
            Artisan::call('config:clear');
            Cache::flush();

            DB::commit();

            // Activer/désactiver le mode maintenance si demandé
            if ($request->has('maintenance_mode')) {
                if ($request->maintenance_mode) {
                    Artisan::call('down', [
                        '--message' => 'Maintenance en cours. Nous serons bientôt de retour.',
                        '--retry' => 60
                    ]);
                } else {
                    Artisan::call('up');
                }
            }

            return redirect()->route('admin.settings.index')
                ->with('success', 'Paramètres mis à jour avec succès.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur mise à jour paramètres: ' . $e->getMessage());

            return back()->withInput()
                ->with('error', 'Erreur lors de la mise à jour des paramètres.');
        }
    }

    /**
     * Gestion du cache
     */
    public function clearCache(Request $request)
    {
        $request->validate([
            'cache_type' => 'required|in:all,views,routes,config,application'
        ]);

        try {
            switch ($request->cache_type) {
                case 'all':
                    Artisan::call('cache:clear');
                    Artisan::call('view:clear');
                    Artisan::call('route:clear');
                    Artisan::call('config:clear');
                    Cache::flush();
                    $message = 'Tous les caches ont été vidés.';
                    break;

                case 'views':
                    Artisan::call('view:clear');
                    $message = 'Cache des vues vidé.';
                    break;

                case 'routes':
                    Artisan::call('route:clear');
                    $message = 'Cache des routes vidé.';
                    break;

                case 'config':
                    Artisan::call('config:clear');
                    $message = 'Cache de configuration vidé.';
                    break;

                case 'application':
                    Cache::flush();
                    $message = 'Cache applicatif vidé.';
                    break;
            }

            // Régénérer les caches critiques
            if (in_array($request->cache_type, ['all', 'routes'])) {
                Artisan::call('route:cache');
            }

            if (in_array($request->cache_type, ['all', 'config'])) {
                Artisan::call('config:cache');
            }

            return back()->with('success', $message);

        } catch (\Exception $e) {
            Log::error('Erreur vidage cache: ' . $e->getMessage());
            return back()->with('error', 'Erreur lors du vidage du cache.');
        }
    }

    /**
     * Optimise le système
     */
    public function optimize()
    {
        try {
            // Optimisations Laravel
            Artisan::call('optimize');
            Artisan::call('view:cache');

            // Préchauffage du cache
            if (method_exists($this->cacheService, 'warmCache')) {
                $currentPeriod = DB::table('system_periods')
                    ->where('is_active', true)
                    ->value('period');

                if ($currentPeriod) {
                    $this->cacheService->warmCache($currentPeriod);
                }
            }

            return back()->with('success', 'Système optimisé avec succès.');

        } catch (\Exception $e) {
            Log::error('Erreur optimisation: ' . $e->getMessage());
            return back()->with('error', 'Erreur lors de l\'optimisation.');
        }
    }

    /**
     * Gestion des backups
     */
    public function backup(Request $request)
    {
        $request->validate([
            'type' => 'required|in:full,database,files'
        ]);

        try {
            $filename = $this->backupService->createBackup($request->type);

            return back()->with('success', "Backup créé avec succès: {$filename}");

        } catch (\Exception $e) {
            Log::error('Erreur backup: ' . $e->getMessage());
            return back()->with('error', 'Erreur lors de la création du backup.');
        }
    }

    /**
     * Récupère les paramètres système
     */
    protected function getSystemSettings(): array
    {
        return [
            'general' => [
                'app_name' => config('app.name'),
                'app_url' => config('app.url'),
                'timezone' => config('app.timezone'),
                'locale' => config('app.locale'),
                'debug_mode' => config('app.debug'),
                'environment' => config('app.env'),
            ],
            'mlm' => SystemConfig::getGroup('mlm') ?: [
                'calculation_period' => 30,
                'min_advancement_threshold' => 100,
                'max_network_depth' => 10,
                'enable_auto_advancements' => true,
                'enable_realtime_bonus' => false,
            ],
            'performance' => [
                'cache_driver' => config('cache.default'),
                'queue_driver' => config('queue.default'),
                'session_driver' => config('session.driver'),
                'cache_ttl' => SystemConfig::getValue('cache_ttl', 3600),
                'pagination_size' => SystemConfig::getValue('pagination_size', 20),
            ],
            'security' => [
                'password_min_length' => 8,
                'session_lifetime' => config('session.lifetime'),
                'remember_me_duration' => 43200, // 30 jours
                'max_login_attempts' => 5,
                'lockout_duration' => 15, // minutes
            ]
        ];
    }

    /**
     * Récupère les statistiques du cache
     */
    protected function getCacheStatistics(): array
    {
        $stats = [
            'driver' => config('cache.default'),
            'size' => 'N/A',
            'hits' => 0,
            'misses' => 0,
            'uptime' => 'N/A'
        ];

        // Statistiques spécifiques selon le driver
        if (config('cache.default') === 'file') {
            $cacheDir = storage_path('framework/cache/data');
            $size = 0;
            $fileCount = 0;

            if (is_dir($cacheDir)) {
                foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($cacheDir)) as $file) {
                    if ($file->isFile()) {
                        $size += $file->getSize();
                        $fileCount++;
                    }
                }
            }

            $stats['size'] = $this->formatBytes($size);
            $stats['files'] = $fileCount;
        } elseif (config('cache.default') === 'database') {
            try {
                $count = DB::table('cache')->count();
                $stats['entries'] = $count;
            } catch (\Exception $e) {
                $stats['entries'] = 0;
            }
        }

        return $stats;
    }

    /**
     * Récupère les informations système
     */
    protected function getSystemInfo(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'database_version' => $this->getDatabaseVersion(),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'disk_free_space' => $this->formatBytes(disk_free_space('/')),
            'disk_total_space' => $this->formatBytes(disk_total_space('/')),
        ];
    }

    /**
     * Récupère l'état des services
     */
    protected function getServicesStatus(): array
    {
        return [
            'database' => $this->checkDatabaseConnection(),
            'cache' => $this->checkCacheConnection(),
            'queue' => $this->checkQueueConnection(),
            'storage' => $this->checkStoragePermissions(),
            'scheduler' => $this->checkScheduler(),
        ];
    }

    /**
     * Vérifie la connexion à la base de données
     */
    protected function checkDatabaseConnection(): array
    {
        try {
            DB::connection()->getPdo();
            return ['status' => 'online', 'message' => 'Connexion OK'];
        } catch (\Exception $e) {
            return ['status' => 'offline', 'message' => 'Erreur de connexion'];
        }
    }

    /**
     * Vérifie la connexion au cache
     */
    protected function checkCacheConnection(): array
    {
        try {
            Cache::put('test_key', 'test_value', 1);
            $value = Cache::get('test_key');
            Cache::forget('test_key');

            return ['status' => 'online', 'message' => 'Cache opérationnel'];
        } catch (\Exception $e) {
            return ['status' => 'offline', 'message' => 'Erreur cache'];
        }
    }

    /**
     * Vérifie la connexion aux queues
     */
    protected function checkQueueConnection(): array
    {
        try {
            $count = DB::table('jobs')->count();
            $failed = DB::table('failed_jobs')->count();

            return [
                'status' => 'online',
                'message' => "Jobs en attente: {$count}, Échoués: {$failed}"
            ];
        } catch (\Exception $e) {
            return ['status' => 'unknown', 'message' => 'Impossible de vérifier'];
        }
    }

    /**
     * Vérifie les permissions de stockage
     */
    protected function checkStoragePermissions(): array
    {
        $directories = [
            'storage/app',
            'storage/framework',
            'storage/logs',
            'bootstrap/cache'
        ];

        foreach ($directories as $dir) {
            if (!is_writable(base_path($dir))) {
                return [
                    'status' => 'error',
                    'message' => "Le dossier {$dir} n'est pas accessible en écriture"
                ];
            }
        }

        return ['status' => 'online', 'message' => 'Permissions OK'];
    }

    /**
     * Vérifie le scheduler
     */
    protected function checkScheduler(): array
    {
        $lastRun = Cache::get('schedule:last_run');

        if (!$lastRun) {
            return [
                'status' => 'unknown',
                'message' => 'Aucune exécution détectée'
            ];
        }

        $diff = now()->diffInMinutes($lastRun);

        if ($diff > 5) {
            return [
                'status' => 'warning',
                'message' => "Dernière exécution il y a {$diff} minutes"
            ];
        }

        return [
            'status' => 'online',
            'message' => 'Scheduler actif'
        ];
    }

    /**
     * Met à jour ou crée un paramètre
     */
    protected function updateOrCreateSetting($key, $value): void
    {
        // Déterminer le groupe
        $group = 'general';
        if (str_starts_with($key, 'mlm_')) {
            $group = 'mlm';
        } elseif (in_array($key, ['cache_ttl', 'pagination_size'])) {
            $group = 'performance';
        }

        SystemConfig::setValue($key, $value);
    }

    /**
     * Met à jour les fichiers de configuration
     */
    protected function updateConfigFiles(array $settings): void
    {
        // Cette méthode pourrait mettre à jour les fichiers .env
        // mais c'est généralement déconseillé en production
        // On se contente de logger les changements

        Log::info('Configuration update requested', $settings);
    }

    /**
     * Récupère la version de la base de données
     */
    protected function getDatabaseVersion(): string
    {
        try {
            $version = DB::select('SELECT VERSION() as version')[0]->version ?? 'Unknown';
            return $version;
        } catch (\Exception $e) {
            return 'Unknown';
        }
    }

    /**
     * Formate une taille en octets
     */
    protected function formatBytes($bytes, $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
