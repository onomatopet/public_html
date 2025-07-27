<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\LevelCurrent;
use App\Models\Distributeur;
use App\Services\EternalHelperLegacyMatriculeDB;
use App\Services\GradeCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RegularizeGrades extends Command
{
    protected $signature = 'app:regularize-grades {period? : The period to regularize in YYYY-MM format}
                                                           {--force : Skip confirmation}
                                                           {--dry-run : Show changes without applying}
                                                           {--batch-size=1000 : Process distributors in batches}';

    protected $description = 'Optimized grade regularization using bottom-up approach';

    private EternalHelperLegacyMatriculeDB $branchQualifier;
    private GradeCalculator $gradeCalculator;

    public function __construct(EternalHelperLegacyMatriculeDB $branchQualifier, GradeCalculator $gradeCalculator)
    {
        parent::__construct();
        $this->branchQualifier = $branchQualifier;
        $this->gradeCalculator = $gradeCalculator;
    }

    public function handle(): int
    {
        $period = $this->argument('period') ?? date('Y-m');
        if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
            $this->error("Invalid period format. Use YYYY-MM format.");
            return self::FAILURE;
        }

        $this->warn("--- OPTIMIZED GRADE REGULARIZATION FOR PERIOD: {$period} ---");

        if (!$this->option('force') && !$this->option('dry-run') &&
            !$this->confirm("BACKUP YOUR DATABASE FIRST! Continue?", false)) {
            return self::FAILURE;
        }

        try {
            // 1. Charger toutes les données nécessaires
            $this->info("Loading all data...");
            $startTime = microtime(true);

            // CORRECTION : Charger la hiérarchie complète avec les bons champs
            $allDistributors = Distributeur::select('id', 'distributeur_id', 'id_distrib_parent', 'etoiles_id')
                ->get()
                ->keyBy('distributeur_id'); // Indexer par matricule pour compatibilité

            // CORRECTION : Charger les données de niveau pour la période
            $levelData = LevelCurrent::where('period', $period)
                ->join('distributeurs', 'level_currents.distributeur_id', '=', 'distributeurs.id') // CORRECTION : Jointure par ID
                ->select(
                    'level_currents.*',
                    'distributeurs.distributeur_id as matricule' // Récupérer le matricule
                )
                ->get()
                ->keyBy('matricule'); // CORRECTION : Indexer par matricule

            if ($levelData->isEmpty()) {
                $this->warn("No data found for period {$period}");
                return self::SUCCESS;
            }

            $loadTime = round(microtime(true) - $startTime, 2);
            $this->info("Loaded {$allDistributors->count()} distributors and {$levelData->count()} level entries in {$loadTime}s");

            // 2. Construire la hiérarchie et calculer les niveaux
            $this->info("Building hierarchy and calculating depth levels...");
            $hierarchy = $this->buildHierarchyWithLevels($allDistributors, $levelData);

            // 3. Traiter par niveaux (des feuilles vers la racine)
            $this->info("Processing distributors by hierarchy level...");
            $updates = $this->processBottomUp($hierarchy, $levelData);

            // 4. Afficher les résultats
            if (empty($updates)) {
                $this->info("No changes needed. Database is already synchronized.");
                return self::SUCCESS;
            }

            $this->displayResults($updates);

            if ($this->option('dry-run')) {
                $this->info("\nDRY RUN: No changes applied.");
                return self::SUCCESS;
            }

            if (!$this->option('force') && !$this->confirm("Apply changes?", true)) {
                return self::SUCCESS;
            }

            // 5. Appliquer les changements
            $this->applyBatchUpdates($updates, $period);

            $this->info("Regularization completed successfully!");
            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            Log::error("Grade regularization failed", ['error' => $e]);
            return self::FAILURE;
        }
    }

    /**
     * Construit la hiérarchie avec les niveaux de profondeur
     * CORRECTION : Adaptation aux nouvelles structures ID/matricule
     */
    private function buildHierarchyWithLevels($allDistributors, $levelData): array
    {
        $hierarchy = [
            'nodes' => [],      // [matricule => node_data]
            'children' => [],   // [parent_matricule => [child_matricules]]
            'levels' => [],     // [level => [matricules]]
            'max_level' => 0
        ];

        // CORRECTION : Construire les relations parent-enfant avec conversion ID->matricule
        foreach ($allDistributors as $dist) {
            $matricule = $dist->distributeur_id;
            $parentId = $dist->id_distrib_parent;

            // CORRECTION : Trouver le matricule du parent à partir de son ID
            $parentMatricule = null;
            if ($parentId && $parentId != 0) {
                foreach ($allDistributors as $potentialParent) {
                    if ($potentialParent->id == $parentId) {
                        $parentMatricule = $potentialParent->distributeur_id;
                        break;
                    }
                }
            }

            // Initialiser le nœud
            $hierarchy['nodes'][$matricule] = [
                'matricule' => $matricule,
                'internal_id' => $dist->id, // AJOUT : Stocker l'ID interne
                'parent' => $parentMatricule, // CORRECTION : Utiliser le matricule du parent
                'level' => -1,  // Non calculé
                'current_grade' => $levelData->has($matricule) ? $levelData->get($matricule)->etoiles : $dist->etoiles_id,
                'cumul_individuel' => $levelData->has($matricule) ? $levelData->get($matricule)->cumul_individuel : 0,
                'cumul_collectif' => $levelData->has($matricule) ? $levelData->get($matricule)->cumul_collectif : 0,
            ];

            // Enregistrer la relation parent-enfant
            if ($parentMatricule) {
                if (!isset($hierarchy['children'][$parentMatricule])) {
                    $hierarchy['children'][$parentMatricule] = [];
                }
                $hierarchy['children'][$parentMatricule][] = $matricule;
            }
        }

        // Calculer les niveaux de profondeur (BFS depuis les racines)
        $queue = [];
        foreach ($hierarchy['nodes'] as $matricule => $node) {
            if (!$node['parent']) {
                $queue[] = [$matricule, 0];
                $hierarchy['nodes'][$matricule]['level'] = 0;
            }
        }

        while (!empty($queue)) {
            [$current, $level] = array_shift($queue);

            if (!isset($hierarchy['levels'][$level])) {
                $hierarchy['levels'][$level] = [];
            }
            $hierarchy['levels'][$level][] = $current;
            $hierarchy['max_level'] = max($hierarchy['max_level'], $level);

            // Ajouter les enfants à la queue
            if (isset($hierarchy['children'][$current])) {
                foreach ($hierarchy['children'][$current] as $child) {
                    if ($hierarchy['nodes'][$child]['level'] == -1) {
                        $hierarchy['nodes'][$child]['level'] = $level + 1;
                        $queue[] = [$child, $level + 1];
                    }
                }
            }
        }

        return $hierarchy;
    }

    /**
     * Traite les distributeurs du bas vers le haut
     */
    private function processBottomUp($hierarchy, $levelData): array
    {
        $updates = [];
        $processedGrades = []; // [matricule => calculated_grade]

        // Créer une structure pour compter les branches qualifiées efficacement
        $branchCounts = []; // [parent][grade] => count

        $progressBar = $this->output->createProgressBar(count($hierarchy['nodes']));
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');

        // Traiter niveau par niveau, des feuilles vers la racine
        for ($level = $hierarchy['max_level']; $level >= 0; $level--) {
            if (!isset($hierarchy['levels'][$level])) continue;

            $progressBar->setMessage("Processing level {$level}");

            foreach ($hierarchy['levels'][$level] as $matricule) {
                $node = $hierarchy['nodes'][$matricule];

                // Ne traiter que les distributeurs présents dans levelData
                if (!$levelData->has($matricule)) {
                    $progressBar->advance();
                    continue;
                }

                // Calculer le nouveau grade
                $calculatedGrade = $this->calculateGradeWithCounts(
                    $node,
                    $branchCounts[$matricule] ?? [],
                    $processedGrades
                );

                $processedGrades[$matricule] = $calculatedGrade;

                // Enregistrer si changement
                if ($calculatedGrade != $node['current_grade']) {
                    $updates[$matricule] = [
                        'internal_id' => $node['internal_id'], // AJOUT : Stocker l'ID interne
                        'matricule' => $matricule,
                        'from' => $node['current_grade'],
                        'to' => $calculatedGrade,
                        'cumul_individuel' => $node['cumul_individuel'],
                        'cumul_collectif' => $node['cumul_collectif'],
                    ];
                }

                // Mettre à jour les compteurs de branches pour le parent
                if ($node['parent']) {
                    if (!isset($branchCounts[$node['parent']])) {
                        $branchCounts[$node['parent']] = [];
                    }

                    // Compter cette branche pour chaque grade atteint
                    for ($grade = 1; $grade <= $calculatedGrade; $grade++) {
                        if (!isset($branchCounts[$node['parent']][$grade])) {
                            $branchCounts[$node['parent']][$grade] = 0;
                        }
                        $branchCounts[$node['parent']][$grade]++;
                    }
                }

                $progressBar->advance();
            }
        }

        $progressBar->finish();
        $this->newLine();

        return $updates;
    }

    /**
     * Calcule le grade en utilisant les compteurs pré-calculés
     */
    private function calculateGradeWithCounts($node, $branchCounts, $processedGrades): int
    {
        $cumulIndividuel = $node['cumul_individuel'];
        $cumulCollectif = $node['cumul_collectif'];

        // Grades 1-3 basés sur cumul individuel
        if ($cumulIndividuel >= 200) {
            $baseGrade = 3;
        } elseif ($cumulIndividuel >= 100) {
            $baseGrade = 2;
        } else {
            $baseGrade = 1;
        }

        // Grade 4 avec cumul individuel
        if ($baseGrade >= 3 && $cumulIndividuel >= 1000) {
            $baseGrade = 4;
        }

        // Pour les grades 4-9, vérifier les conditions avec les branches
        if ($baseGrade >= 3) {
            for ($targetGrade = max(4, $baseGrade + 1); $targetGrade <= 9; $targetGrade++) {
                if ($this->meetsGradeRequirements($targetGrade, $cumulCollectif, $branchCounts)) {
                    $baseGrade = $targetGrade;
                } else {
                    break; // Arrêter si on ne peut pas atteindre ce grade
                }
            }
        }

        // Grades 10-11 basés sur les branches de grade 9
        if ($baseGrade >= 9) {
            $grade9Branches = $branchCounts[9] ?? 0;
            if ($grade9Branches >= 3) {
                return 11;
            } elseif ($grade9Branches >= 2) {
                return 10;
            }
        }

        return $baseGrade;
    }

    /**
     * Vérifie si les conditions sont remplies pour un grade donné
     */
    private function meetsGradeRequirements($targetGrade, $cumulCollectif, $branchCounts): bool
    {
        $rules = [
            4 => ['standard' => 2200, 'strong' => 1000],
            5 => ['standard' => 7800, 'strong' => 3800],
            6 => ['standard' => 35000, 'strong' => 16000],
            7 => ['standard' => 145000, 'strong' => 73000],
            8 => ['standard' => 580000, 'strong' => 280000],
            9 => ['standard' => 780000, 'strong' => 400000],
        ];

        if (!isset($rules[$targetGrade])) return false;

        $rule = $rules[$targetGrade];
        $pass1 = $branchCounts[$targetGrade - 1] ?? 0;

        // Option 1: Standard
        if ($cumulCollectif >= $rule['standard'] && $pass1 >= 2) {
            return true;
        }

        // Options 2, 3, 4: Strong
        if ($cumulCollectif >= $rule['strong']) {
            if ($pass1 >= 3) return true;

            if ($targetGrade > 4) {
                $pass2 = $branchCounts[$targetGrade - 2] ?? 0;
                if ($pass1 >= 2 && $pass2 >= 4) return true;
                if ($pass1 >= 1 && $pass2 >= 6) return true;
            }
        }

        return false;
    }

    /**
     * Affiche les résultats
     */
    private function displayResults($updates): void
    {
        $promotions = array_filter($updates, fn($u) => $u['to'] > $u['from']);
        $demotions = array_filter($updates, fn($u) => $u['to'] < $u['from']);

        $this->warn("\n" . count($updates) . " changes detected:");
        $this->info("Promotions: " . count($promotions));
        $this->info("Demotions: " . count($demotions));

        // Demander si l'utilisateur veut voir tous les changements
        $showAll = true;
        if (count($updates) > 100) {
            $showAll = $this->confirm(
                "Do you want to see all " . count($updates) . " changes? (No = show summary only)",
                true
            );
        }

        if ($showAll) {
            // Afficher les promotions d'abord
            if (!empty($promotions)) {
                $this->info("\n=== PROMOTIONS (" . count($promotions) . ") ===");
                $this->displayChangesTable($promotions, true);
            }

            // Puis les démotions
            if (!empty($demotions)) {
                $this->warn("\n=== DEMOTIONS (" . count($demotions) . ") ===");
                $this->displayChangesTable($demotions, false);
            }
        } else {
            // Afficher un résumé détaillé
            $this->displaySummary($updates, $promotions, $demotions);
        }
    }

    /**
     * Affiche une table de changements
     */
    private function displayChangesTable($changes, $isPromotion = true): void
    {
        // Trier les changements
        uasort($changes, function($a, $b) use ($isPromotion) {
            // Pour les promotions, trier par nouveau grade décroissant
            // Pour les démotions, trier par ampleur de la chute
            if ($isPromotion) {
                return $b['to'] <=> $a['to'];
            } else {
                return ($b['from'] - $b['to']) <=> ($a['from'] - $a['to']);
            }
        });

        // Diviser en chunks pour l'affichage
        $chunks = array_chunk($changes, 50, true);

        foreach ($chunks as $index => $chunk) {
            if ($index > 0) {
                if (!$this->confirm("Continue showing next 50 results?", true)) {
                    break;
                }
            }

            $this->table(
                ['Matricule', 'From', 'To', 'Change', 'Cumul Ind.', 'Cumul Col.'],
                collect($chunk)->map(fn($u, $m) => [
                    $m,
                    $u['from'],
                    $u['to'],
                    $isPromotion ? '↑ +' . ($u['to'] - $u['from']) : '↓ -' . ($u['from'] - $u['to']),
                    number_format($u['cumul_individuel']),
                    number_format($u['cumul_collectif']),
                ])->all()
            );
        }
    }

    /**
     * Affiche un résumé détaillé des changements
     */
    private function displaySummary($updates, $promotions, $demotions): void
    {
        $this->info("\n=== SUMMARY BY GRADE CHANGE ===");

        // Analyser les changements par type
        $changesByType = [];
        foreach ($updates as $matricule => $update) {
            $key = $update['from'] . ' → ' . $update['to'];
            if (!isset($changesByType[$key])) {
                $changesByType[$key] = [
                    'count' => 0,
                    'from' => $update['from'],
                    'to' => $update['to'],
                    'examples' => []
                ];
            }
            $changesByType[$key]['count']++;
            if (count($changesByType[$key]['examples']) < 3) {
                $changesByType[$key]['examples'][] = $matricule;
            }
        }

        // Trier par nombre de changements
        uasort($changesByType, fn($a, $b) => $b['count'] <=> $a['count']);

        $this->table(
            ['Grade Change', 'Count', 'Type', 'Example Matricules'],
            collect($changesByType)->map(fn($change, $key) => [
                $key,
                $change['count'],
                $change['to'] > $change['from'] ? 'Promotion' : 'Demotion',
                implode(', ', $change['examples'])
            ])->all()
        );

        // Statistiques par grade
        $this->info("\n=== GRADE DISTRIBUTION IMPACT ===");
        $gradeImpact = [];

        foreach ($updates as $update) {
            // Diminution pour l'ancien grade
            if (!isset($gradeImpact[$update['from']])) {
                $gradeImpact[$update['from']] = 0;
            }
            $gradeImpact[$update['from']]--;

            // Augmentation pour le nouveau grade
            if (!isset($gradeImpact[$update['to']])) {
                $gradeImpact[$update['to']] = 0;
            }
            $gradeImpact[$update['to']]++;
        }

        ksort($gradeImpact);

        $this->table(
            ['Grade', 'Net Change', 'Impact'],
            collect($gradeImpact)->map(fn($change, $grade) => [
                $grade,
                sprintf("%+d", $change),
                $change > 0 ? '📈' : ($change < 0 ? '📉' : '➖')
            ])->all()
        );
    }

    /**
     * Applique les mises à jour par batch
     * CORRECTION : Adaptation aux nouvelles structures ID/matricule
     */
    private function applyBatchUpdates($updates, $period): void
    {
        $batchSize = (int) $this->option('batch-size');
        $chunks = array_chunk($updates, $batchSize, true);

        $this->info("\nApplying updates in " . count($chunks) . " batches...");
        $progressBar = $this->output->createProgressBar(count($updates));

        DB::beginTransaction();
        try {
            foreach ($chunks as $chunk) {
                // Préparer les mises à jour batch
                $distributeurUpdates = [];
                $levelUpdates = [];

                foreach ($chunk as $matricule => $update) {
                    // CORRECTION : Mise à jour distributeur par matricule
                    $distributeurUpdates[] = [
                        'distributeur_id' => $matricule,
                        'etoiles_id' => $update['to']
                    ];

                    // CORRECTION : Mise à jour level_currents par ID interne
                    $levelUpdates[] = [
                        'distributeur_id' => $update['internal_id'],
                        'period' => $period,
                        'etoiles' => $update['to']
                    ];

                    $progressBar->advance();
                }

                // CORRECTION : Mise à jour batch pour distributeurs (par matricule)
                if (!empty($distributeurUpdates)) {
                    foreach ($distributeurUpdates as $upd) {
                        Distributeur::where('distributeur_id', $upd['distributeur_id'])
                                  ->update(['etoiles_id' => $upd['etoiles_id']]);
                    }

                    // CORRECTION : Mise à jour batch pour level_currents (par ID interne)
                    DB::table('level_currents')->upsert(
                        $levelUpdates,
                        ['distributeur_id', 'period'],
                        ['etoiles']
                    );
                }
            }

            DB::commit();
            $progressBar->finish();
            $this->newLine();
            $this->info("All updates applied successfully!");

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
