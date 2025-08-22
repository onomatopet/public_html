<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use Illuminate\Console\ConfirmableTrait;

class AvancementGradeCommand extends Command
{
    use ConfirmableTrait;

    /**
     * Signature de la commande avec les options de débogage.
     */
    protected $signature = 'app:avancement-grade
                            {period : La période de calcul au format YYYY-MM}
                            {--validate-data=true : Valider la cohérence des données}
                            {--batch-size=100 : Taille des batches}
                            {--matricule= : Exécuter le calcul pour un seul distributeur par son ID (matricule)}
                            {--dry-run : Exécuter la commande sans modifier la base de données}
                            {--limit= : Limiter le nombre de distributeurs à traiter (pour test)}
                            {--show-sql : Afficher les requêtes SQL exécutées}
                            {--force : Forcer l\'exécution sans demande de confirmation}';

    protected $description = 'Calcul automatique des grades des distributeurs pour une période donnée';

    private $period;
    private $validateData;
    private $batchSize;
    private $matricule;
    private $dryRun;
    private $limit;
    private $showSql;

    private $promotions = [];
    private $dataErrors = [];
    private $gradeRules;

    public function __construct()
    {
        parent::__construct();
        $this->initializeGradeRules();
    }

    public function handle()
    {
        $this->period = $this->argument('period');
        $this->validateData = $this->option('validate-data') === 'true';
        $this->batchSize = (int) $this->option('batch-size');
        $this->matricule = $this->option('matricule');
        $this->dryRun = $this->option('dry-run');
        $this->limit = $this->option('limit');
        $this->showSql = $this->option('show-sql');

        $this->info("🚀 Début du calcul des grades pour la période: {$this->period}");
        $this->info("----------------------------------------------------");
        $this->info("Mode d'exécution:");
        $this->line("  - Validation des données: " . ($this->validateData ? 'Activée' : 'Désactivée'));
        if ($this->dryRun) $this->warn("  - Mode Dry Run: Aucune modification ne sera appliquée à la base de données.");
        if ($this->matricule) $this->info("  - Cible unique: Matricule {$this->matricule}");
        if ($this->limit) $this->info("  - Limite: {$this->limit} distributeurs");
        if (!$this->matricule && !$this->limit) $this->info("  - Taille des batches: {$this->batchSize}");
        $this->info("----------------------------------------------------");

        if ($this->showSql) {
            DB::listen(fn(QueryExecuted $query) => $this->logQuery($query));
        }

        try {
            if (!$this->validatePeriod()) return 1;

            if ($this->validateData && !$this->matricule && !$this->validateDataConsistency()) {
                return 1;
            }

            if ($this->matricule) {
                $this->processSingleDistributor();
            } else {
                $this->processMultiplePasses();
            }

            $this->displayResults();

        } catch (Exception $e) {
            $this->error("❌ Erreur: " . $e->getMessage());
            Log::error('Erreur calcul grades', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return 1;
        }

        return 0;
    }

    private function initializeGradeRules()
    {
        $this->gradeRules = [
            2 => [['type' => 'individual', 'cumul' => 100]],
            3 => [['type' => 'individual', 'cumul' => 200]],
            4 => [
                ['type' => 'individual', 'cumul' => 1000],
                ['type' => 'network', 'grade_3_children' => 2, 'different_legs' => 2, 'collective_cumul' => 2200],
                ['type' => 'network', 'grade_3_children' => 3, 'different_legs' => 3, 'collective_cumul' => 1000]
            ],
            5 => [
                ['type' => 'network', 'grade_4_children' => 2, 'different_legs' => 2, 'collective_cumul' => 7800],
                ['type' => 'network', 'grade_4_children' => 3, 'different_legs' => 3, 'collective_cumul' => 3800],
                ['type' => 'network', 'grade_4_children' => 2, 'grade_3_children' => 4, 'different_legs' => 6, 'collective_cumul' => 3800],
                ['type' => 'network', 'grade_4_children' => 1, 'grade_3_children' => 6, 'different_legs' => 7, 'collective_cumul' => 3800]
            ],
            6 => [
                ['type' => 'network', 'grade_5_children' => 2, 'different_legs' => 2, 'collective_cumul' => 35000],
                ['type' => 'network', 'grade_5_children' => 3, 'different_legs' => 3, 'collective_cumul' => 16000],
                ['type' => 'network', 'grade_5_children' => 2, 'grade_4_children' => 4, 'different_legs' => 6, 'collective_cumul' => 16000],
                ['type' => 'network', 'grade_5_children' => 1, 'grade_4_children' => 6, 'different_legs' => 7, 'collective_cumul' => 16000]
            ],
            7 => [
                ['type' => 'network', 'grade_6_children' => 2, 'different_legs' => 2, 'collective_cumul' => 145000],
                ['type' => 'network', 'grade_6_children' => 3, 'different_legs' => 3, 'collective_cumul' => 73000],
                ['type' => 'network', 'grade_6_children' => 2, 'grade_5_children' => 4, 'different_legs' => 6, 'collective_cumul' => 73000],
                ['type' => 'network', 'grade_6_children' => 1, 'grade_5_children' => 6, 'different_legs' => 7, 'collective_cumul' => 73000]
            ],
            8 => [
                ['type' => 'network', 'grade_7_children' => 2, 'different_legs' => 2, 'collective_cumul' => 580000],
                ['type' => 'network', 'grade_7_children' => 3, 'different_legs' => 3, 'collective_cumul' => 280000],
                ['type' => 'network', 'grade_7_children' => 2, 'grade_6_children' => 4, 'different_legs' => 6, 'collective_cumul' => 280000],
                ['type' => 'network', 'grade_7_children' => 1, 'grade_6_children' => 6, 'different_legs' => 7, 'collective_cumul' => 280000]
            ],
            9 => [
                ['type' => 'network', 'grade_8_children' => 2, 'different_legs' => 2, 'collective_cumul' => 780000],
                ['type' => 'network', 'grade_8_children' => 3, 'different_legs' => 3, 'collective_cumul' => 400000],
                ['type' => 'network', 'grade_8_children' => 2, 'grade_7_children' => 4, 'different_legs' => 6, 'collective_cumul' => 400000],
                ['type' => 'network', 'grade_8_children' => 1, 'grade_7_children' => 6, 'different_legs' => 7, 'collective_cumul' => 400000]
            ],
            10 => [['type' => 'network', 'grade_9_children' => 2, 'different_legs' => 2]],
            11 => [['type' => 'network', 'grade_9_children' => 3, 'different_legs' => 3]]
        ];
    }

    private function validatePeriod(): bool
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $this->period)) {
            $this->error("❌ Format de période invalide. Utilisez le format YYYY-MM (ex: 2025-07)");
            return false;
        }

        $count = DB::table('level_currents')->where('period', $this->period)->count();

        if ($count === 0) {
            $this->error("❌ Aucune donnée trouvée pour la période {$this->period}");
            return false;
        }

        $this->info("✅ Période validée: {$count} distributeurs trouvés");
        return true;
    }

    private function validateDataConsistency(): bool
    {
        $this->info("🔍 Validation de la cohérence des données...");

        $inconsistentData = DB::select("
            SELECT
                lc.distributeur_id,
                d.nom_distributeur,
                d.pnom_distributeur,
                lc.cumul_collectif
            FROM level_currents lc
            JOIN distributeurs d ON lc.distributeur_id = d.distributeur_id
            LEFT JOIN distributeurs children ON children.id_distrib_parent = d.id
            WHERE lc.period = ?
                AND lc.cumul_collectif > lc.new_cumul
            GROUP BY lc.distributeur_id, d.nom_distributeur, d.pnom_distributeur, lc.cumul_collectif
            HAVING COUNT(children.id) = 0
               AND lc.cumul_collectif > (MAX(lc.new_cumul) * 2)
        ", [$this->period]);

        if (count($inconsistentData) > 0) {
            $this->warn("⚠️  Données incohérentes détectées:");
            foreach ($inconsistentData as $data) {
                $this->line("   - {$data->nom_distributeur} {$data->pnom_distributeur} (Matricule: {$data->distributeur_id}) - Cumul collectif: {$data->cumul_collectif} sans enfants directs.");
                $this->dataErrors[] = $data;
            }

            if (!$this->confirmToProceed("Continuer malgré les incohérences?")) {
                $this->info("💾 Liste des erreurs sauvegardée dans les logs");
                Log::warning('Données incohérentes détectées', ['errors' => $this->dataErrors, 'period' => $this->period]);
                return false;
            }
        } else {
            $this->info("✅ Cohérence des données validée");
        }

        return true;
    }

    private function processSingleDistributor(): void
    {
        $this->info("🔄 Traitement du distributeur avec le matricule: {$this->matricule}");

        $distributor = DB::table('level_currents as lc')
            ->join('distributeurs as d', 'lc.distributeur_id', '=', 'd.distributeur_id')
            ->select(
                'lc.distributeur_id', 'lc.etoiles as current_grade', 'lc.new_cumul as individual_cumul',
                'lc.cumul_collectif as collective_cumul', 'd.nom_distributeur', 'd.pnom_distributeur'
            )
            ->where('lc.period', $this->period)
            ->where('lc.distributeur_id', $this->matricule)
            ->first();

        if (!$distributor) {
            $this->error("❌ Distributeur avec le matricule {$this->matricule} non trouvé pour la période {$this->period}.");
            return;
        }

        if (!$this->calculateAndPromote($distributor)) {
            $this->info("Le distributeur ne se qualifie pour aucune promotion.");
        }
    }

    private function processMultiplePasses(): void
    {
        $pass = 1;
        $totalPromotions = 0;

        $this->info("🔄 Début du traitement par passes multiples...");

        do {
            $this->line("   Passe #{$pass}...");
            $passPromotions = $this->processGradesForAllDistributors();
            $totalPromotions += $passPromotions;

            $this->info("   ✅ Passe #{$pass} terminée: {$passPromotions} promotions");
            $pass++;

            if ($pass > 10) {
                $this->warn("⚠️  Limite de passes atteinte (10). Arrêt du traitement.");
                break;
            }

        } while ($passPromotions > 0);

        $this->info("🎉 Traitement terminé après " . ($pass - 1) . " passes. Total promotions: {$totalPromotions}");
    }

    private function processGradesForAllDistributors(): int
    {
        $promotionsCount = 0;

        $query = DB::table('level_currents as lc')
            ->join('distributeurs as d', 'lc.distributeur_id', '=', 'd.distributeur_id')
            ->select(
                'lc.distributeur_id', 'lc.etoiles as current_grade', 'lc.new_cumul as individual_cumul',
                'lc.cumul_collectif as collective_cumul', 'd.nom_distributeur', 'd.pnom_distributeur'
            )
            ->where('lc.period', $this->period)
            ->orderBy('lc.distributeur_id');

        if ($this->limit) {
            $distributors = $query->limit($this->limit)->get();
        } else {
             $distributors = $query->cursor();
        }

        foreach ($distributors as $distributor) {
            if ($this->calculateAndPromote($distributor)) {
                $promotionsCount++;
            }
        }

        return $promotionsCount;
    }

    private function calculateAndPromote($distributor): bool
    {
        $newGrade = $this->calculateNewGrade($distributor);
        if ($newGrade > $distributor->current_grade) {
            $this->promoteDistributor($distributor, $newGrade);
            return true;
        }
        return false;
    }

    private function calculateNewGrade($distributor): int
    {
        $currentGrade = $distributor->current_grade;
        $maxGrade = $currentGrade;

        for ($targetGrade = $currentGrade + 1; $targetGrade <= 11; $targetGrade++) {
            if ($this->canAdvanceToGrade($distributor, $targetGrade)) {
                $maxGrade = $targetGrade;
            } else {
                break;
            }
        }

        return $maxGrade;
    }

    private function canAdvanceToGrade($distributor, int $targetGrade): bool
    {
        if (!isset($this->gradeRules[$targetGrade])) {
            return false;
        }

        $rules = $this->gradeRules[$targetGrade];

        foreach ($rules as $rule) {
            if ($this->checkRule($distributor, $rule)) {
                return true;
            }
        }

        return false;
    }

    private function checkRule($distributor, array $rule): bool
    {
        if ($rule['type'] === 'individual') {
            return $distributor->individual_cumul >= $rule['cumul'];
        }

        if ($rule['type'] === 'network') {
            return $this->checkNetworkRule($distributor, $rule);
        }

        return false;
    }

    private function checkNetworkRule($distributor, array $rule): bool
    {
        $networkStructure = $this->getNetworkStructureWithMaxGrades($distributor->distributeur_id);
        $gradeCounts = [];

        foreach ($networkStructure as $leg) {
            $maxGrade = $leg->max_grade;
            if (!isset($gradeCounts[$maxGrade])) {
                $gradeCounts[$maxGrade] = 0;
            }
            $gradeCounts[$maxGrade]++;
        }

        $allConditionsMet = true;

        if (isset($rule['different_legs'])) {
            $actualLegs = 0;
            $requiredLegs = $rule['different_legs'];
            foreach($rule as $key => $value) {
                if (strpos($key, 'grade_') === 0) {
                     $grade = (int) str_replace(['grade_', '_children'], '', $key);
                     $requiredCount = $value;
                     $availableCount = 0;
                     foreach($gradeCounts as $g => $count) {
                        if ($g >= $grade) $availableCount += $count;
                     }
                     $actualLegs += min($requiredCount, $availableCount);
                }
            }
            if ($actualLegs < $requiredLegs) {
                 if ($this->getOutput()->isVerbose()) $this->line("      <fg=red>✗</> Pieds qualifiés: Requis {$requiredLegs}, Actuel {$actualLegs}");
                 $allConditionsMet = false;
            }
        }

        foreach ($rule as $key => $required) {
            if (strpos($key, 'grade_') === 0) {
                $grade = (int) str_replace(['grade_', '_children'], '', $key);
                $available = 0;
                foreach($gradeCounts as $g => $count) {
                    if ($g >= $grade) $available += $count;
                }
                if ($available < $required) {
                    if ($this->getOutput()->isVerbose()) $this->line("      <fg=red>✗</> Enfants G{$grade}+: Requis {$required}, Actuel {$available}");
                    $allConditionsMet = false;
                }
            }
        }

        if (isset($rule['collective_cumul'])) {
            if ($distributor->collective_cumul < $rule['collective_cumul']) {
                if ($this->getOutput()->isVerbose()) $this->line("      <fg=red>✗</> Cumul collectif: Requis {$rule['collective_cumul']}, Actuel {$distributor->collective_cumul}");
                $allConditionsMet = false;
            }
        }

        return $allConditionsMet;
    }

    private function getNetworkStructureWithMaxGrades($distributorMatricule): array
    {
        return DB::select("
            WITH RECURSIVE network_tree AS (
                SELECT
                    d.id AS child_id,
                    d.distributeur_id AS child_matricule,
                    lc.etoiles,
                    d.id_distrib_parent,
                    d.distributeur_id AS leg_root_matricule,
                    1 AS level
                FROM distributeurs d
                JOIN level_currents lc ON d.distributeur_id = lc.distributeur_id AND lc.period = ?
                WHERE d.id_distrib_parent = (SELECT id FROM distributeurs WHERE distributeur_id = ?)

                UNION ALL

                SELECT
                    d.id,
                    d.distributeur_id,
                    lc.etoiles,
                    d.id_distrib_parent,
                    nt.leg_root_matricule,
                    nt.level + 1
                FROM distributeurs d
                JOIN level_currents lc ON d.distributeur_id = lc.distributeur_id AND lc.period = ?
                JOIN network_tree nt ON d.id_distrib_parent = nt.child_id
                WHERE nt.level < 20
            )
            SELECT
                leg_root_matricule,
                MAX(etoiles) as max_grade
            FROM network_tree
            GROUP BY leg_root_matricule
        ", [$this->period, $distributorMatricule, $this->period]);
    }

    private function promoteDistributor($distributor, int $newGrade): void
    {
        $promotion = [
            'distributeur_id' => $distributor->distributeur_id,
            'nom_complet' => $distributor->nom_distributeur . ' ' . $distributor->pnom_distributeur,
            'ancien_grade' => $distributor->current_grade,
            'nouveau_grade' => $newGrade,
            'period' => $this->period,
            'promoted_at' => now()
        ];

        if ($this->dryRun) {
            $this->warn("[DRY RUN] Promotion détectée: {$promotion['nom_complet']} de G{$promotion['ancien_grade']} à G{$promotion['nouveau_grade']}");
            $this->promotions[] = $promotion;
            return;
        }

        DB::beginTransaction();
        try {
            DB::table('level_currents')->where('distributeur_id', $distributor->distributeur_id)->where('period', $this->period)
                ->update(['etoiles' => $newGrade, 'updated_at' => now()]);
            DB::table('distributeurs')->where('distributeur_id', $distributor->distributeur_id)
                ->update(['etoiles_id' => $newGrade, 'updated_at' => now()]);

            $this->promotions[] = $promotion;
            Log::info('Promotion distributeur', $promotion);
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception("Erreur lors de la promotion du distributeur {$distributor->distributeur_id}: " . $e->getMessage());
        }
    }

    private function displayResults(): void
    {
        $this->info("\n📋 RÉSULTATS DU TRAITEMENT:");
        $this->info("================================");
        $this->info("Période: {$this->period}");
        if ($this->dryRun) $this->warn("Mode: Dry Run (aucune modification effectuée)");
        $this->info("Total promotions " . ($this->dryRun ? "potentielles: " : ": ") . count($this->promotions));

        if (count($this->promotions) > 0) {
            $this->info("\n🎉 PROMOTIONS:");

            $gradeGroups = [];
            foreach ($this->promotions as $promotion) {
                $key = $promotion['ancien_grade'] . ' → ' . $promotion['nouveau_grade'];
                if (!isset($gradeGroups[$key])) $gradeGroups[$key] = [];
                $gradeGroups[$key][] = $promotion;
            }

            foreach ($gradeGroups as $transition => $promotionsInGroup) {
                $this->info("\n  Grade {$transition}: " . count($promotionsInGroup) . " promotions");
                foreach (array_slice($promotionsInGroup, 0, 5) as $promo) {
                    $this->line("    - {$promo['nom_complet']} (Matricule: {$promo['distributeur_id']})");
                }
                if (count($promotionsInGroup) > 5) {
                    $this->line("    ... et " . (count($promotionsInGroup) - 5) . " autres");
                }
            }
        }

        if (count($this->dataErrors) > 0) {
            $this->warn("\n⚠️  ERREURS DE DONNÉES: " . count($this->dataErrors));
            $this->info("Consultez les logs pour plus de détails.");
        }

        $this->info("\n✅ Traitement terminé avec succès!");
    }

    private function logQuery(QueryExecuted $query): void
    {
        $sql = $query->sql;
        $bindings = $query->bindings;
        $time = $query->time;

        foreach ($bindings as $binding) {
            $value = is_numeric($binding) ? $binding : "'".$binding."'";
            $sql = preg_replace('/\?/', $value, $sql, 1);
        }

        $this->line("<fg=yellow>SQL ({$time}ms):</> <fg=cyan>{$sql}</>");
    }
}
