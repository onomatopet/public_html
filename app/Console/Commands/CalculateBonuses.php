<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BonusCalculateService;
use App\Models\SystemPeriod;
use App\Models\Bonus;
use Illuminate\Support\Facades\Log;

class CalculateBonuses extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:calculate-bonuses
                            {period? : The period to calculate bonuses for (YYYY-MM format)}
                            {--dry-run : Run calculation without saving to database}
                            {--force : Skip confirmation}
                            {--batch-size=100 : Process distributors in batches}
                            {--debug : Show detailed debug information}
                            {--recalculate : Delete existing bonuses and recalculate}
                            {--matricule= : Calculate bonus for a specific distributor only}
                            {--export= : Export results to CSV file}
                            {--report : Generate and display report after calculation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate MLM bonuses for all distributors for a given period';

    private BonusCalculateService $bonusService;

    public function __construct(BonusCalculateService $bonusService)
    {
        parent::__construct();
        $this->bonusService = $bonusService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $period = $this->argument('period') ?? date('Y-m');

        // Valider le format de la période
        if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
            $this->error("Invalid period format. Use YYYY-MM format.");
            return self::FAILURE;
        }

        $this->info("=== MLM Bonus Calculation ===");
        $this->info("Period: <fg=yellow>{$period}</>");

        // Mode calcul pour un seul distributeur
        if ($matricule = $this->option('matricule')) {
            return $this->handleSingleDistributor($matricule, $period);
        }

        // Vérifier si des bonus existent déjà
        $existingBonuses = Bonus::where('period', $period)->count();
        if ($existingBonuses > 0 && !$this->option('recalculate')) {
            $this->warn("{$existingBonuses} bonuses already exist for period {$period}.");

            if (!$this->option('force') && !$this->confirm("Do you want to recalculate and replace them?")) {
                $this->comment("Operation cancelled.");
                return self::SUCCESS;
            }

            // Supprimer les bonus existants
            $this->info("Deleting existing bonuses...");
            Bonus::where('period', $period)->delete();
        }

        // Vérifier le statut du workflow si disponible
        $systemPeriod = SystemPeriod::where('period', $period)->first();
        if ($systemPeriod && !$systemPeriod->advancements_calculated) {
            $this->warn("Grade advancements have not been calculated for this period!");

            if (!$this->confirm("Continue anyway? (bonuses may be incorrect)")) {
                return self::FAILURE;
            }
        }

        if ($this->option('dry-run')) {
            $this->warn("DRY RUN MODE - No data will be saved to database");
        }

        if (!$this->option('force') && !$this->option('dry-run')) {
            if (!$this->confirm("Calculate bonuses for period {$period}?")) {
                $this->comment("Operation cancelled.");
                return self::SUCCESS;
            }
        }

        // Options de calcul
        $options = [
            'batch_size' => (int) $this->option('batch-size'),
            'dry_run' => $this->option('dry-run'),
            'debug' => $this->option('debug')
        ];

        $this->info("Starting bonus calculation...");
        $startTime = microtime(true);

        // Barre de progression
        $progressBar = null;
        if (!$this->option('debug')) {
            $progressBar = $this->output->createProgressBar();
            $progressBar->start();
        }

        // Calculer les bonus
        $result = $this->bonusService->calculateBonusesForPeriod($period, $options);

        if ($progressBar) {
            $progressBar->finish();
            $this->newLine();
        }

        $duration = round(microtime(true) - $startTime, 2);

        if (!$result['success']) {
            $this->error("Bonus calculation failed: " . $result['error']);
            return self::FAILURE;
        }

        // Afficher les statistiques
        $this->displayStatistics($result['statistics'], $duration);

        // Exporter si demandé
        if ($exportFile = $this->option('export')) {
            $this->exportResults($result['results'], $exportFile);
        }

        // Générer le rapport si demandé
        if ($this->option('report') && !$this->option('dry-run')) {
            $this->generateReport($period);
        }

        // Mettre à jour le workflow si nécessaire
        if ($systemPeriod && !$this->option('dry-run')) {
            $systemPeriod->update([
                'bonus_calculated' => true,
                'bonus_calculated_at' => now(),
                'bonus_calculated_by' => 1 // Utilisateur système pour les commandes
            ]);
            $this->info("System period updated.");
        }

        return self::SUCCESS;
    }

    /**
     * Gère le calcul pour un seul distributeur
     */
    private function handleSingleDistributor(string $matricule, string $period): int
    {
        $this->info("Calculating bonus for distributor: {$matricule}");

        $result = $this->bonusService->recalculateBonusForDistributor($matricule, $period);

        if (!$result['success']) {
            $this->error($result['error']);
            return self::FAILURE;
        }

        $bonus = $result['bonus'];

        $this->table(
            ['Field', 'Value'],
            [
                ['Matricule', $bonus['matricule']],
                ['Name', $bonus['nom'] . ' ' . $bonus['prenom']],
                ['Grade', $bonus['grade']],
                ['New Cumul', number_format($bonus['new_cumul'], 2)],
                ['Direct Bonus', number_format($bonus['bonus_direct'], 2) . ' FCFA'],
                ['Indirect Bonus', number_format($bonus['bonus_indirect'], 2) . ' FCFA'],
                ['Total Bonus', number_format($bonus['total_bonus'], 2) . ' FCFA'],
            ]
        );

        if ($this->option('debug')) {
            $this->info("\nIndirect Bonus Details:");
            foreach ($bonus['details']['indirect_branches'] as $branch) {
                $this->line("  Branch: {$branch['child_matricule']} (Grade {$branch['child_grade']}) - Status: {$branch['status']}");
                if (isset($branch['bonus'])) {
                    $this->line("    Bonus: " . number_format($branch['bonus'], 2) . " FCFA");
                }
            }
        }

        return self::SUCCESS;
    }

    /**
     * Affiche les statistiques de calcul
     */
    private function displayStatistics(array $stats, float $duration): void
    {
        $this->newLine();
        $this->info("=== Calculation Statistics ===");

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Distributors', number_format($stats['total_distributors'])],
                ['Eligible Distributors', number_format($stats['eligible_distributors'])],
                ['Total Direct Bonus', number_format($stats['total_direct_bonus'], 2) . ' FCFA'],
                ['Total Indirect Bonus', number_format($stats['total_indirect_bonus'], 2) . ' FCFA'],
                ['Total Bonus Amount', number_format($stats['total_bonus'], 2) . ' FCFA'],
                ['Errors', $stats['errors']],
                ['Duration', $duration . ' seconds'],
            ]
        );

        if ($stats['eligible_distributors'] > 0) {
            $avgBonus = $stats['total_bonus'] / $stats['eligible_distributors'];
            $this->info("Average bonus per eligible distributor: " . number_format($avgBonus, 2) . " FCFA");
        }
    }

    /**
     * Exporte les résultats vers un fichier CSV
     */
    private function exportResults(array $results, string $filename): void
    {
        $this->info("\nExporting results to {$filename}...");

        $csv = fopen($filename, 'w');

        // En-têtes
        fputcsv($csv, [
            'Matricule',
            'Nom',
            'Prénom',
            'Grade',
            'New Cumul',
            'Bonus Direct',
            'Bonus Indirect',
            'Bonus Total'
        ]);

        // Données
        foreach ($results as $result) {
            fputcsv($csv, [
                $result['matricule'],
                $result['nom'],
                $result['prenom'],
                $result['grade'],
                $result['new_cumul'],
                $result['bonus_direct'],
                $result['bonus_indirect'],
                $result['total_bonus']
            ]);
        }

        fclose($csv);
        $this->info("Export completed.");
    }

    /**
     * Génère et affiche un rapport
     */
    private function generateReport(string $period): void
    {
        $this->newLine();
        $this->info("=== Bonus Report for {$period} ===");

        $report = $this->bonusService->generateBonusReport($period);

        // Résumé général
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Distributors', number_format($report['total_distributors'])],
                ['Total Amount', number_format($report['total_amount'], 2) . ' FCFA'],
                ['Total Direct', number_format($report['total_direct'], 2) . ' FCFA'],
                ['Total Indirect', number_format($report['total_indirect'], 2) . ' FCFA'],
            ]
        );

        // Distribution par grade
        $this->newLine();
        $this->info("Distribution by Grade:");

        $gradeRows = [];
        foreach ($report['by_grade'] as $grade => $data) {
            $gradeRows[] = [
                $grade,
                number_format($data['count']),
                number_format($data['total'], 2) . ' FCFA',
                number_format($data['average'], 2) . ' FCFA'
            ];
        }

        $this->table(['Grade', 'Count', 'Total', 'Average'], $gradeRows);

        // Top earners
        $this->newLine();
        $this->info("Top 10 Earners:");

        $topRows = [];
        foreach ($report['top_earners'] as $index => $earner) {
            $topRows[] = [
                $index + 1,
                $earner['matricule'],
                $earner['nom'],
                $earner['grade'],
                number_format($earner['montant'], 2) . ' FCFA'
            ];
        }

        $this->table(['Rank', 'Matricule', 'Name', 'Grade', 'Amount'], $topRows);
    }
}
