<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MLM\MLMDataCleaningService;
use App\Models\MLMCleaningSession;
use Symfony\Component\Console\Helper\Table;
use App\Models\MLMCleaningAnomaly;

class MLMDataCleaning extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mlm:clean-data
                            {--period-start= : Période de début (YYYY-MM)}
                            {--period-end= : Période de fin (YYYY-MM)}
                            {--analyze-only : Analyser uniquement sans corriger}
                            {--fix-hierarchy : Corriger les problèmes de hiérarchie}
                            {--fix-cumuls : Corriger les cumuls}
                            {--fix-grades : Corriger les grades}
                            {--fix-all : Corriger tous les problèmes détectés}
                            {--no-snapshot : Ne pas créer de snapshot}
                            {--force : Forcer l\'exécution sans confirmation}
                            {--dry-run : Mode simulation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Nettoyer et corriger les données MLM';

    protected MLMDataCleaningService $cleaningService;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🧹 MLM Data Cleaning Tool');
        $this->info('========================');

        // Préparer les options
        $options = $this->prepareOptions();

        // Afficher le résumé
        $this->displaySummary($options);

        // Demander confirmation
        if (!$this->option('force') && !$this->option('dry-run')) {
            if (!$this->confirm('Voulez-vous continuer ?')) {
                $this->comment('Opération annulée.');
                return self::FAILURE;
            }
        }

        // Démarrer le chronomètre
        $startTime = microtime(true);

        try {
            // Initialiser le service
            $this->cleaningService = new MLMDataCleaningService();

            // Démarrer la session
            $this->info("\n📊 Démarrage de l'analyse...");
            $session = $this->cleaningService->startCleaningSession($options);

            $this->info("Session créée : {$session->session_code}");

            // Mode analyse uniquement
            if ($this->option('analyze-only')) {
                $result = $this->cleaningService->execute($session, array_merge($options, [
                    'preview_only' => true
                ]));

                $this->displayAnalysisResults($result['analysis']);
                $this->displayPreview($result['preview']);

                return self::SUCCESS;
            }

            // Exécuter le nettoyage complet
            $result = $this->cleaningService->execute($session, $options);

            // Afficher les résultats
            $this->displayResults($result);

            $executionTime = round(microtime(true) - $startTime, 2);
            $this->info("\n✅ Processus terminé en {$executionTime} secondes");

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("\n❌ Erreur : " . $e->getMessage());

            if ($this->option('verbose')) {
                $this->error($e->getTraceAsString());
            }

            return self::FAILURE;
        }
    }

    /**
     * Préparer les options
     */
    protected function prepareOptions(): array
    {
        $options = [
            'period_start' => $this->option('period-start'),
            'period_end' => $this->option('period-end'),
            'dry_run' => $this->option('dry-run'),
            'create_snapshot' => !$this->option('no-snapshot'),
            'user_id' => 1 // Admin par défaut en CLI
        ];

        // Types de corrections à appliquer
        $fixTypes = [];

        if ($this->option('fix-all')) {
            $fixTypes = [
                MLMCleaningAnomaly::TYPE_ORPHAN_PARENT,
                MLMCleaningAnomaly::TYPE_CUMUL_INDIVIDUAL_NEGATIVE,
                MLMCleaningAnomaly::TYPE_CUMUL_COLLECTIVE_LESS_THAN_INDIVIDUAL,
                MLMCleaningAnomaly::TYPE_GRADE_CONDITIONS_NOT_MET
            ];
        } else {
            if ($this->option('fix-hierarchy')) {
                $fixTypes[] = MLMCleaningAnomaly::TYPE_ORPHAN_PARENT;
            }
            if ($this->option('fix-cumuls')) {
                $fixTypes[] = MLMCleaningAnomaly::TYPE_CUMUL_INDIVIDUAL_NEGATIVE;
                $fixTypes[] = MLMCleaningAnomaly::TYPE_CUMUL_COLLECTIVE_LESS_THAN_INDIVIDUAL;
            }
            if ($this->option('fix-grades')) {
                $fixTypes[] = MLMCleaningAnomaly::TYPE_GRADE_CONDITIONS_NOT_MET;
            }
        }

        if (!empty($fixTypes)) {
            $options['fix_types'] = $fixTypes;
        }

        // Déterminer le type de session
        if ($options['period_start'] && $options['period_end']) {
            $options['type'] = MLMCleaningSession::TYPE_PERIOD;
        } else {
            $options['type'] = MLMCleaningSession::TYPE_FULL;
        }

        return $options;
    }

    /**
     * Afficher le résumé
     */
    protected function displaySummary(array $options): void
    {
        $this->info("\n📋 Configuration :");

        $table = new Table($this->output);
        $table->setHeaders(['Paramètre', 'Valeur']);

        $rows = [
            ['Type', $options['type']],
            ['Période début', $options['period_start'] ?? 'Toutes'],
            ['Période fin', $options['period_end'] ?? 'Toutes'],
            ['Mode', $this->option('analyze-only') ? 'Analyse uniquement' : 'Analyse et correction'],
            ['Snapshot', $options['create_snapshot'] ? 'Oui' : 'Non'],
            ['Dry run', $options['dry_run'] ? 'Oui' : 'Non']
        ];

        if (!$this->option('analyze-only')) {
            $corrections = [];
            if ($this->option('fix-all')) {
                $corrections[] = 'Toutes';
            } else {
                if ($this->option('fix-hierarchy')) $corrections[] = 'Hiérarchie';
                if ($this->option('fix-cumuls')) $corrections[] = 'Cumuls';
                if ($this->option('fix-grades')) $corrections[] = 'Grades';
            }

            $rows[] = ['Corrections', empty($corrections) ? 'Aucune' : implode(', ', $corrections)];
        }

        $table->setRows($rows);
        $table->render();
    }

    /**
     * Afficher les résultats d'analyse
     */
    protected function displayAnalysisResults(array $analysis): void
    {
        $this->info("\n📊 Résultats de l'analyse :");

        // Résumé
        $table = new Table($this->output);
        $table->setHeaders(['Métrique', 'Valeur']);
        $table->setRows([
            ['Enregistrements analysés', number_format($analysis['summary']['total_records'])],
            ['Anomalies détectées', number_format($analysis['summary']['total_anomalies'])],
            ['Corrections automatiques possibles', number_format($analysis['summary']['can_auto_fix'])],
            ['Problèmes critiques', number_format($analysis['summary']['critical_issues'])],
            ['Durée d\'analyse', $analysis['analysis_duration'] . ' secondes']
        ]);
        $table->render();

        // Par catégorie
        $this->info("\n📈 Anomalies par catégorie :");
        $categoryTable = new Table($this->output);
        $categoryTable->setHeaders(['Catégorie', 'Nombre']);
        $categoryTable->setRows([
            ['Hiérarchie', $analysis['by_category']['hierarchy']],
            ['Cumuls', $analysis['by_category']['cumuls']],
            ['Grades', $analysis['by_category']['grades']],
            ['Périodes', $analysis['by_category']['periods']]
        ]);
        $categoryTable->render();

        // Par sévérité
        $this->info("\n🚨 Anomalies par sévérité :");
        $severityTable = new Table($this->output);
        $severityTable->setHeaders(['Sévérité', 'Nombre']);

        $severityColors = [
            'critical' => 'error',
            'high' => 'comment',
            'medium' => 'info',
            'low' => 'line'
        ];

        foreach ($analysis['by_severity'] as $severity => $count) {
            $color = $severityColors[$severity] ?? 'line';
            $this->$color("  $severity: $count");
        }

        // Top distributeurs affectés
        if (!empty($analysis['top_distributeurs'])) {
            $this->info("\n👥 Top 5 distributeurs affectés :");
            $distTable = new Table($this->output);
            $distTable->setHeaders(['ID', 'Matricule', 'Nom', 'Anomalies']);

            foreach ($analysis['top_distributeurs']->take(5) as $dist) {
                $distTable->addRow([
                    $dist->distributeur_id,
                    $dist->distributeur->distributeur_id ?? 'N/A',
                    $dist->distributeur->nom_distributeur ?? 'N/A',
                    $dist->anomaly_count
                ]);
            }
            $distTable->render();
        }
    }

    /**
     * Afficher le preview
     */
    protected function displayPreview(array $preview): void
    {
        $this->info("\n🔍 Preview des corrections :");

        $table = new Table($this->output);
        $table->setHeaders(['Type', 'Nombre', 'Auto-correction']);

        foreach ($preview['by_type'] as $type => $data) {
            $autoFix = MLMCleaningAnomaly::where('type', $type)
                ->where('can_auto_fix', true)
                ->exists() ? '✓' : '✗';

            $table->addRow([
                $data['label'],
                $data['count'],
                $autoFix
            ]);
        }
        $table->render();

        // Échantillon
        if (!empty($preview['sample_corrections'])) {
            $this->info("\n📝 Échantillon de corrections (5 premières) :");

            foreach (array_slice($preview['sample_corrections'], 0, 5) as $correction) {
                $this->line("\n• Distributeur: {$correction['distributeur']['nom']} ({$correction['distributeur']['matricule']})");
                $this->line("  Type: {$correction['type']}");
                $this->line("  Description: {$correction['description']}");

                if ($correction['can_auto_fix']) {
                    $this->info("  ✓ Correction automatique possible");
                } else {
                    $this->comment("  ⚠ Correction manuelle requise");
                }
            }
        }
    }

    /**
     * Afficher les résultats finaux
     */
    protected function displayResults(array $result): void
    {
        $this->info("\n✅ Nettoyage terminé !");

        // Résumé des corrections
        $corrections = $result['corrections'] ?? [];

        $table = new Table($this->output);
        $table->setHeaders(['Métrique', 'Valeur']);
        $table->setRows([
            ['Corrections appliquées', $corrections['total_corrections'] ?? 0],
            ['Hiérarchie corrigée', $corrections['hierarchy_fixed'] ?? 0],
            ['Cumuls corrigés', $corrections['cumuls_fixed'] ?? 0],
            ['Grades corrigés', $corrections['grades_fixed'] ?? 0],
            ['Corrections ignorées', $corrections['skipped'] ?? 0],
            ['Erreurs', $corrections['errors'] ?? 0]
        ]);
        $table->render();

        // Rapport final
        if (isset($result['report'])) {
            $report = $result['report'];

            $this->info("\n📊 Rapport final :");
            $this->line("Taux de succès : {$report['summary']['success_rate']}%");
            $this->line("Temps d'exécution : {$report['session']['execution_time']}");

            if (!empty($report['unresolved_issues']['total'])) {
                $this->comment("\n⚠️  {$report['unresolved_issues']['total']} anomalies non résolues");
            }
        }

        // Lien vers l'interface web
        $this->info("\n🌐 Pour plus de détails, consultez l'interface web :");
        $this->line(url("/admin/mlm-cleaning/report/{$result['session']->id}"));
    }
}
