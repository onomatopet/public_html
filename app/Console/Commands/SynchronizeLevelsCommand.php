<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\LevelSynchronizationService; // Importez le service

class SynchronizeLevelsCommand extends Command
{
     /**
     * On copie les enregistrements de la table distributeurs ver la table level_current_tests.
     * - UTILISATION : php artisan levels:synchronize YYYY-MM
     */

    protected $signature = 'levels:synchronize {period : La période à synchroniser (YYYY-MM)}';
    protected $description = "S'assure que tous les distributeurs ont une entrée dans level_current_tests pour la période donnée.";

    protected LevelSynchronizationService $syncService;

    public function __construct(LevelSynchronizationService $syncService)
    {
        parent::__construct();
        $this->syncService = $syncService;
    }

    public function handle()
    {
        $period = $this->argument('period');
        if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
            $this->error("Format de période invalide. Utilisez YYYY-MM.");
            return Command::FAILURE;
        }

        $this->info("Lancement de la synchronisation des niveaux pour la période : {$period}");
        $result = $this->syncService->ensureDistributeurLevelsExist($period);

        if (isset($result['error'])) {
            $this->error($result['message'] . " Erreur: " . $result['error']);
            return Command::FAILURE;
        }

        $this->info($result['message']);
        $this->info("Nombre d'enregistrements créés : " . $result['created_count']);
        return Command::SUCCESS;
    }
}
