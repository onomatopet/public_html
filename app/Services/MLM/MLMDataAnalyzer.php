<?php

namespace App\Services\MLM;

use App\Models\Distributeur;
use App\Models\LevelCurrent;
use App\Models\MLMCleaningSession;
use App\Models\MLMCleaningAnomaly;
use App\Models\MLMCleaningProgress;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MLMDataAnalyzer
{
    protected MLMCleaningSession $session;
    protected array $anomalyStats = [
        'hierarchy' => 0,
        'cumuls' => 0,
        'grades' => 0,
        'periods' => 0,
        'total' => 0
    ];

    public function __construct(MLMCleaningSession $session)
    {
        $this->session = $session;
    }

    /**
     * Analyse complète des données
     */
    public function analyze(array $options = []): array
    {
        Log::info("Starting MLM data analysis for session {$this->session->session_code}");

        $this->session->updateStatus(MLMCleaningSession::STATUS_ANALYZING);

        try {
            // Créer le progress tracker
            $totalSteps = 4; // Hierarchy, Cumuls, Grades, Periods
            $progress = MLMCleaningProgress::createForStep(
                $this->session->id,
                MLMCleaningProgress::STEP_ANALYSIS,
                $totalSteps
            );

            // 1. Analyse de la hiérarchie
            $progress->updateProgress(0, 'hierarchy', 'Analyse de la hiérarchie...');
            $hierarchyAnomalies = $this->analyzeHierarchy($options);
            $progress->updateProgress(1, 'hierarchy', 'Hiérarchie analysée');

            // 2. Analyse des cumuls
            $progress->updateProgress(1, 'cumuls', 'Analyse des cumuls...');
            $cumulAnomalies = $this->analyzeCumuls($options);
            $progress->updateProgress(2, 'cumuls', 'Cumuls analysés');

            // 3. Analyse des grades
            $progress->updateProgress(2, 'grades', 'Analyse des grades...');
            $gradeAnomalies = $this->analyzeGrades($options);
            $progress->updateProgress(3, 'grades', 'Grades analysés');

            // 4. Analyse des périodes
            $progress->updateProgress(3, 'periods', 'Analyse des périodes...');
            $periodAnomalies = $this->analyzePeriods($options);
            $progress->updateProgress(4, 'periods', 'Périodes analysées');

            $progress->markAsCompleted('Analyse terminée');

            // Mettre à jour les statistiques de la session
            $this->updateSessionStats();

            // Générer le rapport d'analyse
            $report = $this->generateAnalysisReport();

            $this->session->updateStatus(MLMCleaningSession::STATUS_PREVIEW);

            Log::info("MLM data analysis completed. Found {$this->anomalyStats['total']} anomalies");

            return $report;

        } catch (\Exception $e) {
            Log::error("Error during MLM data analysis: " . $e->getMessage());
            $this->session->updateStatus(MLMCleaningSession::STATUS_FAILED, $e->getMessage());
            throw $e;
        }
    }

    /**
     * Analyse de la hiérarchie
     */
    protected function analyzeHierarchy(array $options): Collection
    {
        $anomalies = collect();

        // 1. Détecter les boucles dans la hiérarchie
        $loops = $this->detectHierarchyLoops();
        foreach ($loops as $loop) {
            $this->recordAnomaly(
                $loop['distributeur_id'],
                'N/A',
                MLMCleaningAnomaly::TYPE_HIERARCHY_LOOP,
                "Boucle détectée dans la hiérarchie: " . implode(' -> ', $loop['path']),
                [
                    'severity' => MLMCleaningAnomaly::SEVERITY_CRITICAL,
                    'metadata' => ['path' => $loop['path']],
                    'can_auto_fix' => false
                ]
            );
            $this->anomalyStats['hierarchy']++;
        }

        // 2. Détecter les parents orphelins
        $orphans = $this->detectOrphanParents();
        foreach ($orphans as $orphan) {
            $this->recordAnomaly(
                $orphan->distributeur_id,
                'N/A',
                MLMCleaningAnomaly::TYPE_ORPHAN_PARENT,
                "Parent ID {$orphan->id_distrib_parent} n'existe pas",
                [
                    'field_name' => 'id_distrib_parent',
                    'current_value' => $orphan->id_distrib_parent,
                    'expected_value' => null,
                    'severity' => MLMCleaningAnomaly::SEVERITY_HIGH,
                    'can_auto_fix' => true
                ]
            );
            $this->anomalyStats['hierarchy']++;
        }

        return $anomalies;
    }

    /**
     * Analyse des cumuls
     */
    protected function analyzeCumuls(array $options): Collection
    {
        $anomalies = collect();
        $periods = $this->getPeriodsToAnalyze($options);

        foreach ($periods as $period) {
            // 1. Cumuls individuels négatifs
            $negativeCumuls = LevelCurrent::where('period', $period)
                ->where('cumul_individuel', '<', 0)
                ->get();

            foreach ($negativeCumuls as $record) {
                $this->recordAnomaly(
                    $record->distributeur_id,
                    $period,
                    MLMCleaningAnomaly::TYPE_CUMUL_INDIVIDUAL_NEGATIVE,
                    "Cumul individuel négatif: {$record->cumul_individuel}",
                    [
                        'field_name' => 'cumul_individuel',
                        'current_value' => $record->cumul_individuel,
                        'expected_value' => 0,
                        'severity' => MLMCleaningAnomaly::SEVERITY_HIGH,
                        'can_auto_fix' => true
                    ]
                );
                $this->anomalyStats['cumuls']++;
            }

            // 2. Cumul collectif < cumul individuel
            $invalidCollectifs = LevelCurrent::where('period', $period)
                ->whereColumn('cumul_collectif', '<', 'cumul_individuel')
                ->get();

            foreach ($invalidCollectifs as $record) {
                $this->recordAnomaly(
                    $record->distributeur_id,
                    $period,
                    MLMCleaningAnomaly::TYPE_CUMUL_COLLECTIVE_LESS_THAN_INDIVIDUAL,
                    "Cumul collectif ({$record->cumul_collectif}) < cumul individuel ({$record->cumul_individuel})",
                    [
                        'field_name' => 'cumul_collectif',
                        'current_value' => $record->cumul_collectif,
                        'expected_value' => ">= {$record->cumul_individuel}",
                        'severity' => MLMCleaningAnomaly::SEVERITY_HIGH,
                        'can_auto_fix' => true
                    ]
                );
                $this->anomalyStats['cumuls']++;
            }

            // 3. Évolution des cumuls (détection des diminutions)
            if ($options['check_cumul_evolution'] ?? true) {
                $this->analyzeCumulEvolution($period);
            }
        }

        return $anomalies;
    }

    /**
     * Analyse de l'évolution des cumuls
     */
    protected function analyzeCumulEvolution(string $currentPeriod): void
    {
        $previousPeriod = $this->getPreviousPeriod($currentPeriod);
        if (!$previousPeriod) {
            return;
        }

        $query = "
            SELECT
                curr.distributeur_id,
                curr.cumul_individuel as current_cumul,
                prev.cumul_individuel as previous_cumul,
                curr.cumul_collectif as current_collectif,
                prev.cumul_collectif as previous_collectif
            FROM level_currents curr
            INNER JOIN level_currents prev ON curr.distributeur_id = prev.distributeur_id
            WHERE curr.period = ? AND prev.period = ?
            AND (curr.cumul_individuel < prev.cumul_individuel
                 OR curr.cumul_collectif < prev.cumul_collectif)
        ";

        $results = DB::select($query, [$currentPeriod, $previousPeriod]);

        foreach ($results as $result) {
            if ($result->current_cumul < $result->previous_cumul) {
                $this->recordAnomaly(
                    $result->distributeur_id,
                    $currentPeriod,
                    MLMCleaningAnomaly::TYPE_CUMUL_DECREASE,
                    "Cumul individuel diminué: {$result->previous_cumul} → {$result->current_cumul}",
                    [
                        'field_name' => 'cumul_individuel',
                        'current_value' => $result->current_cumul,
                        'expected_value' => ">= {$result->previous_cumul}",
                        'severity' => MLMCleaningAnomaly::SEVERITY_LOW,
                        'can_auto_fix' => false,
                        'metadata' => [
                            'previous_period' => $previousPeriod,
                            'decrease_amount' => $result->previous_cumul - $result->current_cumul
                        ]
                    ]
                );
                $this->anomalyStats['cumuls']++;
            }
        }
    }

    /**
     * Analyse des grades
     */
    protected function analyzeGrades(array $options): Collection
    {
        $anomalies = collect();
        $periods = $this->getPeriodsToAnalyze($options);
        $gradeCalculator = new MLMGradeCalculator();

        foreach ($periods as $period) {
            $distributeurs = LevelCurrent::where('period', $period)
                ->with('distributeur')
                ->chunk(100, function($chunk) use ($period, $gradeCalculator) {
                    foreach ($chunk as $record) {
                        // Calculer le grade attendu
                        $expectedGrade = $gradeCalculator->calculateGrade(
                            $record->distributeur_id,
                            $period
                        );

                        // Vérifier si le grade actuel correspond
                        if ($record->etoiles != $expectedGrade) {
                            $this->recordAnomaly(
                                $record->distributeur_id,
                                $period,
                                MLMCleaningAnomaly::TYPE_GRADE_CONDITIONS_NOT_MET,
                                "Grade actuel ({$record->etoiles}) ne correspond pas au grade calculé ({$expectedGrade})",
                                [
                                    'field_name' => 'etoiles',
                                    'current_value' => $record->etoiles,
                                    'expected_value' => $expectedGrade,
                                    'severity' => MLMCleaningAnomaly::SEVERITY_HIGH,
                                    'can_auto_fix' => true,
                                    'metadata' => [
                                        'conditions_met' => $gradeCalculator->getLastCheckDetails()
                                    ]
                                ]
                            );
                            $this->anomalyStats['grades']++;
                        }

                        // Vérifier les sauts de grade
                        $this->checkGradeProgression($record, $period);
                    }
                });
        }

        return $anomalies;
    }

    /**
     * Vérifier la progression des grades
     */
    protected function checkGradeProgression($currentRecord, string $period): void
    {
        $previousPeriod = $this->getPreviousPeriod($period);
        if (!$previousPeriod) {
            return;
        }

        $previousRecord = LevelCurrent::where('distributeur_id', $currentRecord->distributeur_id)
            ->where('period', $previousPeriod)
            ->first();

        if (!$previousRecord) {
            return;
        }

        // Régression de grade
        if ($currentRecord->etoiles < $previousRecord->etoiles) {
            $this->recordAnomaly(
                $currentRecord->distributeur_id,
                $period,
                MLMCleaningAnomaly::TYPE_GRADE_REGRESSION,
                "Régression de grade: {$previousRecord->etoiles} → {$currentRecord->etoiles}",
                [
                    'field_name' => 'etoiles',
                    'current_value' => $currentRecord->etoiles,
                    'expected_value' => ">= {$previousRecord->etoiles}",
                    'severity' => MLMCleaningAnomaly::SEVERITY_MEDIUM,
                    'can_auto_fix' => false,
                    'metadata' => [
                        'previous_period' => $previousPeriod,
                        'regression' => $previousRecord->etoiles - $currentRecord->etoiles
                    ]
                ]
            );
            $this->anomalyStats['grades']++;
        }

        // Saut de grade (plus de 2 niveaux)
        if ($currentRecord->etoiles > $previousRecord->etoiles + 2) {
            $this->recordAnomaly(
                $currentRecord->distributeur_id,
                $period,
                MLMCleaningAnomaly::TYPE_GRADE_SKIP,
                "Saut de grade important: {$previousRecord->etoiles} → {$currentRecord->etoiles}",
                [
                    'field_name' => 'etoiles',
                    'current_value' => $currentRecord->etoiles,
                    'severity' => MLMCleaningAnomaly::SEVERITY_MEDIUM,
                    'can_auto_fix' => false,
                    'metadata' => [
                        'previous_period' => $previousPeriod,
                        'jump' => $currentRecord->etoiles - $previousRecord->etoiles
                    ]
                ]
            );
            $this->anomalyStats['grades']++;
        }
    }

    /**
     * Analyse des périodes
     */
    protected function analyzePeriods(array $options): Collection
    {
        $anomalies = collect();

        // Récupérer tous les distributeurs actifs
        $distributeurs = Distributeur::all();

        foreach ($distributeurs as $distributeur) {
            // Vérifier les périodes manquantes
            $periods = LevelCurrent::where('distributeur_id', $distributeur->id)
                ->orderBy('period')
                ->pluck('period')
                ->toArray();

            if (empty($periods)) {
                continue;
            }

            $missingPeriods = $this->findMissingPeriods($periods);
            foreach ($missingPeriods as $missingPeriod) {
                $this->recordAnomaly(
                    $distributeur->id,
                    $missingPeriod,
                    MLMCleaningAnomaly::TYPE_MISSING_PERIOD,
                    "Période manquante dans l'historique",
                    [
                        'severity' => MLMCleaningAnomaly::SEVERITY_LOW,
                        'can_auto_fix' => false,
                        'metadata' => [
                            'previous_period' => $missingPeriod['previous'],
                            'next_period' => $missingPeriod['next']
                        ]
                    ]
                );
                $this->anomalyStats['periods']++;
            }

            // Vérifier les périodes dupliquées
            $duplicates = DB::table('level_currents')
                ->select('period', DB::raw('count(*) as count'))
                ->where('distributeur_id', $distributeur->id)
                ->groupBy('period')
                ->having('count', '>', 1)
                ->get();

            foreach ($duplicates as $duplicate) {
                $this->recordAnomaly(
                    $distributeur->id,
                    $duplicate->period,
                    MLMCleaningAnomaly::TYPE_DUPLICATE_PERIOD,
                    "Période dupliquée ({$duplicate->count} entrées)",
                    [
                        'severity' => MLMCleaningAnomaly::SEVERITY_CRITICAL,
                        'can_auto_fix' => false,
                        'metadata' => ['count' => $duplicate->count]
                    ]
                );
                $this->anomalyStats['periods']++;
            }
        }

        return $anomalies;
    }

    /**
     * Détecter les boucles dans la hiérarchie
     */
    protected function detectHierarchyLoops(): array
    {
        $loops = [];
        $distributeurs = Distributeur::all();

        foreach ($distributeurs as $distributeur) {
            $visited = [];
            $path = [];

            if ($this->hasLoop($distributeur->id, $visited, $path)) {
                $loops[] = [
                    'distributeur_id' => $distributeur->id,
                    'path' => $path
                ];
            }
        }

        return $loops;
    }

    /**
     * Vérifier récursivement s'il y a une boucle
     */
    protected function hasLoop($distributeurId, &$visited, &$path): bool
    {
        if (in_array($distributeurId, $visited)) {
            return true;
        }

        $visited[] = $distributeurId;
        $path[] = $distributeurId;

        $distributeur = Distributeur::find($distributeurId);
        if ($distributeur && $distributeur->id_distrib_parent) {
            if ($this->hasLoop($distributeur->id_distrib_parent, $visited, $path)) {
                return true;
            }
        }

        array_pop($path);
        return false;
    }

    /**
     * Détecter les parents orphelins
     */
    protected function detectOrphanParents(): Collection
    {
        return DB::table('distributeurs as d1')
            ->leftJoin('distributeurs as d2', 'd1.id_distrib_parent', '=', 'd2.id')
            ->whereNotNull('d1.id_distrib_parent')
            ->whereNull('d2.id')
            ->select('d1.id as distributeur_id', 'd1.id_distrib_parent')
            ->get();
    }

    /**
     * Trouver les périodes manquantes
     */
    protected function findMissingPeriods(array $periods): array
    {
        $missing = [];
        sort($periods);

        for ($i = 0; $i < count($periods) - 1; $i++) {
            $current = \Carbon\Carbon::createFromFormat('Y-m', $periods[$i]);
            $next = \Carbon\Carbon::createFromFormat('Y-m', $periods[$i + 1]);

            $monthsDiff = $current->diffInMonths($next);

            if ($monthsDiff > 1) {
                $missingStart = $current->copy()->addMonth();
                for ($j = 1; $j < $monthsDiff; $j++) {
                    $missing[] = [
                        'period' => $missingStart->format('Y-m'),
                        'previous' => $periods[$i],
                        'next' => $periods[$i + 1]
                    ];
                    $missingStart->addMonth();
                }
            }
        }

        return $missing;
    }

    /**
     * Obtenir les périodes à analyser
     */
    protected function getPeriodsToAnalyze(array $options): array
    {
        if ($this->session->period_start && $this->session->period_end) {
            $start = \Carbon\Carbon::createFromFormat('Y-m', $this->session->period_start);
            $end = \Carbon\Carbon::createFromFormat('Y-m', $this->session->period_end);

            $periods = [];
            while ($start <= $end) {
                $periods[] = $start->format('Y-m');
                $start->addMonth();
            }

            return $periods;
        }

        // Par défaut, analyser toutes les périodes
        return LevelCurrent::distinct()
            ->orderBy('period')
            ->pluck('period')
            ->toArray();
    }

    /**
     * Obtenir la période précédente
     */
    protected function getPreviousPeriod(string $period): ?string
    {
        $date = \Carbon\Carbon::createFromFormat('Y-m', $period);
        $previousDate = $date->copy()->subMonth();

        // Vérifier si la période existe
        $exists = LevelCurrent::where('period', $previousDate->format('Y-m'))->exists();

        return $exists ? $previousDate->format('Y-m') : null;
    }

    /**
     * Enregistrer une anomalie
     */
    protected function recordAnomaly(
        int $distributeurId,
        string $period,
        string $type,
        string $description,
        array $options = []
    ): void {
        MLMCleaningAnomaly::record(
            $this->session->id,
            $distributeurId,
            $period,
            $type,
            $description,
            $options
        );

        $this->anomalyStats['total']++;
    }

    /**
     * Mettre à jour les statistiques de la session
     */
    protected function updateSessionStats(): void
    {
        $totalRecords = DB::table('level_currents')
            ->when($this->session->period_start, function($query) {
                return $query->where('period', '>=', $this->session->period_start);
            })
            ->when($this->session->period_end, function($query) {
                return $query->where('period', '<=', $this->session->period_end);
            })
            ->count();

        $this->session->update([
            'total_records' => $totalRecords,
            'records_analyzed' => $totalRecords,
            'records_with_anomalies' => $this->anomalyStats['total'],
            'hierarchy_issues' => $this->anomalyStats['hierarchy'],
            'cumul_issues' => $this->anomalyStats['cumuls'],
            'grade_issues' => $this->anomalyStats['grades']
        ]);
    }

    /**
     * Générer le rapport d'analyse
     */
    public function generateAnalysisReport(): array
    {
        $anomaliesBySeverity = MLMCleaningAnomaly::where('session_id', $this->session->id)
            ->select('severity', DB::raw('count(*) as count'))
            ->groupBy('severity')
            ->pluck('count', 'severity')
            ->toArray();

        $anomaliesByType = MLMCleaningAnomaly::where('session_id', $this->session->id)
            ->select('type', DB::raw('count(*) as count'))
            ->groupBy('type')
            ->pluck('count', 'type')
            ->toArray();

        $topDistributeurs = MLMCleaningAnomaly::where('session_id', $this->session->id)
            ->select('distributeur_id', DB::raw('count(*) as anomaly_count'))
            ->groupBy('distributeur_id')
            ->orderByDesc('anomaly_count')
            ->limit(10)
            ->with('distributeur')
            ->get();

        return [
            'summary' => [
                'total_records' => $this->session->total_records,
                'records_analyzed' => $this->session->records_analyzed,
                'total_anomalies' => $this->anomalyStats['total'],
                'can_auto_fix' => MLMCleaningAnomaly::where('session_id', $this->session->id)
                    ->where('can_auto_fix', true)
                    ->count(),
                'critical_issues' => $anomaliesBySeverity[MLMCleaningAnomaly::SEVERITY_CRITICAL] ?? 0
            ],
            'by_category' => [
                'hierarchy' => $this->anomalyStats['hierarchy'],
                'cumuls' => $this->anomalyStats['cumuls'],
                'grades' => $this->anomalyStats['grades'],
                'periods' => $this->anomalyStats['periods']
            ],
            'by_severity' => $anomaliesBySeverity,
            'by_type' => $anomaliesByType,
            'top_distributeurs' => $topDistributeurs,
            'analysis_duration' => $this->session->started_at
                ? now()->diffInSeconds($this->session->started_at)
                : 0
        ];
    }
}
