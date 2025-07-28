<?php

namespace App\Services\MLM;

use App\Models\MLMCleaningSession;
use App\Models\MLMCleaningAnomaly;
use App\Models\MLMCleaningLog;
use App\Models\MLMCleaningSnapshot;
use App\Models\MLMCleaningProgress;
use App\Models\LevelCurrent;
use App\Models\Distributeur;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class MLMDataCleaningService
{
    protected MLMDataAnalyzer $analyzer;
    protected MLMGradeCalculator $gradeCalculator;
    protected MLMCumulCalculator $cumulCalculator;
    protected MLMCleaningReportService $reportService;
    protected MLMCleaningSession $session;

    /**
     * Démarrer une nouvelle session de nettoyage
     */
    public function startCleaningSession(array $options = []): MLMCleaningSession
    {
        // Créer la session
        $this->session = MLMCleaningSession::create([
            'type' => $options['type'] ?? MLMCleaningSession::TYPE_FULL,
            'period_start' => $options['period_start'] ?? null,
            'period_end' => $options['period_end'] ?? null,
            'created_by' => $options['user_id'] ?? auth()->id(),
            'configuration' => $options,
            'status' => MLMCleaningSession::STATUS_PENDING
        ]);

        // Initialiser les services
        $this->analyzer = new MLMDataAnalyzer($this->session);
        $this->gradeCalculator = new MLMGradeCalculator();
        $this->cumulCalculator = new MLMCumulCalculator();
        $this->reportService = new MLMCleaningReportService($this->session);

        Log::info("Started MLM cleaning session: {$this->session->session_code}");

        return $this->session;
    }

    /**
     * Exécuter le processus complet de nettoyage
     */
    public function execute(MLMCleaningSession $session, array $options = []): array
    {
        $this->session = $session;
        $this->initializeServices();

        try {
            // 1. Créer un snapshot si activé
            if (config('mlm-cleaning.snapshot.enabled')) {
                $this->createSnapshot();
            }

            // 2. Analyser les données
            $analysisReport = $this->analyzer->analyze($options);

            // 3. Si mode preview uniquement
            if ($options['preview_only'] ?? false) {
                return [
                    'success' => true,
                    'session' => $this->session,
                    'analysis' => $analysisReport,
                    'preview' => $this->generatePreview()
                ];
            }

            // 4. Appliquer les corrections
            $this->session->updateStatus(MLMCleaningSession::STATUS_PROCESSING);
            $corrections = $this->applyCorrections($options);

            // 5. Finaliser
            $this->finalize();

            return [
                'success' => true,
                'session' => $this->session,
                'analysis' => $analysisReport,
                'corrections' => $corrections,
                'report' => $this->reportService->generateFinalReport()
            ];

        } catch (\Exception $e) {
            Log::error("MLM cleaning failed: " . $e->getMessage());
            $this->session->updateStatus(MLMCleaningSession::STATUS_FAILED, $e->getMessage());

            throw $e;
        }
    }

    /**
     * Créer un snapshot avant modification
     */
    protected function createSnapshot(): MLMCleaningSnapshot
    {
        $progress = MLMCleaningProgress::createForStep(
            $this->session->id,
            MLMCleaningProgress::STEP_SNAPSHOT,
            1
        );

        try {
            $snapshot = MLMCleaningSnapshot::create([
                'session_id' => $this->session->id,
                'type' => MLMCleaningSnapshot::TYPE_FULL,
                'status' => MLMCleaningSnapshot::STATUS_CREATING,
                'tables_included' => ['level_currents', 'distributeurs'],
                'expires_at' => now()->addDays(config('mlm-cleaning.snapshot.retention_days'))
            ]);

            // Créer le fichier de backup
            $backupData = $this->generateBackupData();
            $filename = "mlm_snapshot_{$this->session->session_code}_" . now()->format('YmdHis') . ".json";
            $path = config('mlm-cleaning.snapshot.path') . '/' . $filename;

            // Compresser si activé
            if (config('mlm-cleaning.snapshot.compression')) {
                $backupData = gzencode(json_encode($backupData));
                $filename .= '.gz';
                $path .= '.gz';
            } else {
                $backupData = json_encode($backupData);
            }

            Storage::disk(config('mlm-cleaning.snapshot.storage_disk'))->put($path, $backupData);

            $snapshot->update([
                'status' => MLMCleaningSnapshot::STATUS_COMPLETED,
                'storage_path' => $path,
                'file_size' => strlen($backupData),
                'records_count' => $this->countBackupRecords(),
                'metadata' => $snapshot->generateMetadata()
            ]);

            $progress->markAsCompleted('Snapshot créé avec succès');

            return $snapshot;

        } catch (\Exception $e) {
            $progress->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    /**
     * Générer les données de backup
     */
    protected function generateBackupData(): array
    {
        $data = [
            'session' => $this->session->toArray(),
            'timestamp' => now()->toIso8601String(),
            'tables' => []
        ];

        // Sauvegarder level_currents
        $query = LevelCurrent::query();
        if ($this->session->period_start) {
            $query->where('period', '>=', $this->session->period_start);
        }
        if ($this->session->period_end) {
            $query->where('period', '<=', $this->session->period_end);
        }
        $data['tables']['level_currents'] = $query->get()->toArray();

        // Sauvegarder distributeurs (structure uniquement)
        $data['tables']['distributeurs'] = Distributeur::select(
            'id', 'distributeur_id', 'id_distrib_parent', 'etoiles_id'
        )->get()->toArray();

        return $data;
    }

    /**
     * Compter les enregistrements du backup
     */
    protected function countBackupRecords(): int
    {
        $count = 0;

        $query = LevelCurrent::query();
        if ($this->session->period_start) {
            $query->where('period', '>=', $this->session->period_start);
        }
        if ($this->session->period_end) {
            $query->where('period', '<=', $this->session->period_end);
        }
        $count += $query->count();

        $count += Distributeur::count();

        return $count;
    }

    /**
     * Appliquer les corrections
     */
    protected function applyCorrections(array $options): array
    {
        $stats = [
            'total_corrections' => 0,
            'hierarchy_fixed' => 0,
            'cumuls_fixed' => 0,
            'grades_fixed' => 0,
            'skipped' => 0,
            'errors' => 0
        ];

        $anomalies = MLMCleaningAnomaly::where('session_id', $this->session->id)
            ->where('is_fixed', false)
            ->orderBy('severity', 'desc')
            ->get();

        $totalAnomalies = $anomalies->count();
        $progress = MLMCleaningProgress::createForStep(
            $this->session->id,
            MLMCleaningProgress::STEP_CORRECTION,
            $totalAnomalies
        );

        $processed = 0;

        foreach ($anomalies as $anomaly) {
            try {
                if ($this->shouldFixAnomaly($anomaly, $options)) {
                    $fixed = $this->fixAnomaly($anomaly);

                    if ($fixed) {
                        $stats['total_corrections']++;

                        switch ($anomaly->type) {
                            case MLMCleaningAnomaly::TYPE_HIERARCHY_LOOP:
                            case MLMCleaningAnomaly::TYPE_ORPHAN_PARENT:
                                $stats['hierarchy_fixed']++;
                                break;
                            case MLMCleaningAnomaly::TYPE_CUMUL_INDIVIDUAL_NEGATIVE:
                            case MLMCleaningAnomaly::TYPE_CUMUL_COLLECTIVE_LESS_THAN_INDIVIDUAL:
                                $stats['cumuls_fixed']++;
                                break;
                            case MLMCleaningAnomaly::TYPE_GRADE_CONDITIONS_NOT_MET:
                            case MLMCleaningAnomaly::TYPE_GRADE_REGRESSION:
                                $stats['grades_fixed']++;
                                break;
                        }
                    }
                } else {
                    $stats['skipped']++;
                }

                $processed++;
                $progress->updateProgress(
                    $processed,
                    "Anomalie {$processed}/{$totalAnomalies}",
                    "Traitement: " . $anomaly->getTypeLabel()
                );

            } catch (\Exception $e) {
                Log::error("Error fixing anomaly {$anomaly->id}: " . $e->getMessage());
                $stats['errors']++;
            }
        }

        $progress->markAsCompleted('Corrections appliquées');

        // Mettre à jour la session
        $this->session->update([
            'records_corrected' => $stats['total_corrections']
        ]);

        return $stats;
    }

    /**
     * Déterminer si une anomalie doit être corrigée
     */
    protected function shouldFixAnomaly(MLMCleaningAnomaly $anomaly, array $options): bool
    {
        // Si correction manuelle uniquement
        if ($options['manual_only'] ?? false) {
            return false;
        }

        // Si auto-fix désactivé pour ce type
        if (!$anomaly->can_auto_fix) {
            return false;
        }

        // Vérifier les options spécifiques
        if (isset($options['fix_types'])) {
            return in_array($anomaly->type, $options['fix_types']);
        }

        return true;
    }

    /**
     * Corriger une anomalie spécifique (méthode publique pour le contrôleur)
     */
    public function fixSingleAnomaly(MLMCleaningAnomaly $anomaly): bool
    {
        // Initialiser la session si nécessaire
        if (!$this->session) {
            $this->session = $anomaly->session;
            $this->initializeServices();
        }

        return $this->fixAnomaly($anomaly);
    }

    /**
     * Définir la session (pour les jobs)
     */
    public function setSession(MLMCleaningSession $session): void
    {
        $this->session = $session;
        $this->initializeServices();
    }

    /**
     * Corriger une anomalie
     */
    protected function fixAnomaly(MLMCleaningAnomaly $anomaly): bool
    {
        DB::beginTransaction();

        try {
            $fixed = false;

            switch ($anomaly->type) {
                case MLMCleaningAnomaly::TYPE_ORPHAN_PARENT:
                    $fixed = $this->fixOrphanParent($anomaly);
                    break;

                case MLMCleaningAnomaly::TYPE_CUMUL_INDIVIDUAL_NEGATIVE:
                    $fixed = $this->fixNegativeIndividualCumul($anomaly);
                    break;

                case MLMCleaningAnomaly::TYPE_CUMUL_COLLECTIVE_LESS_THAN_INDIVIDUAL:
                    $fixed = $this->fixCollectiveCumul($anomaly);
                    break;

                case MLMCleaningAnomaly::TYPE_GRADE_CONDITIONS_NOT_MET:
                    $fixed = $this->fixGrade($anomaly);
                    break;
            }

            if ($fixed) {
                $anomaly->markAsFixed();
                DB::commit();
                return true;
            }

            DB::rollBack();
            return false;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Corriger un parent orphelin
     */
    protected function fixOrphanParent(MLMCleaningAnomaly $anomaly): bool
    {
        $distributeur = Distributeur::find($anomaly->distributeur_id);
        if (!$distributeur) {
            return false;
        }

        $oldValue = $distributeur->id_distrib_parent;
        $distributeur->id_distrib_parent = null;
        $distributeur->save();

        MLMCleaningLog::logChange(
            $this->session->id,
            $anomaly->distributeur_id,
            $anomaly->period,
            'distributeurs',
            'id_distrib_parent',
            $oldValue,
            null,
            MLMCleaningLog::ACTION_UPDATE,
            'Parent orphelin supprimé'
        );

        return true;
    }

    /**
     * Corriger un cumul individuel négatif
     */
    protected function fixNegativeIndividualCumul(MLMCleaningAnomaly $anomaly): bool
    {
        $record = LevelCurrent::where('distributeur_id', $anomaly->distributeur_id)
            ->where('period', $anomaly->period)
            ->first();

        if (!$record) {
            return false;
        }

        $oldValue = $record->cumul_individuel;
        $record->cumul_individuel = 0;
        $record->save();

        MLMCleaningLog::logChange(
            $this->session->id,
            $anomaly->distributeur_id,
            $anomaly->period,
            'level_currents',
            'cumul_individuel',
            $oldValue,
            0,
            MLMCleaningLog::ACTION_UPDATE,
            'Cumul individuel négatif corrigé à 0'
        );

        // Propager le changement dans la hiérarchie
        $this->cumulCalculator->propagateCumuls($anomaly->distributeur_id, $anomaly->period);

        return true;
    }

    /**
     * Corriger un cumul collectif inférieur au cumul individuel
     */
    protected function fixCollectiveCumul(MLMCleaningAnomaly $anomaly): bool
    {
        $record = LevelCurrent::where('distributeur_id', $anomaly->distributeur_id)
            ->where('period', $anomaly->period)
            ->first();

        if (!$record) {
            return false;
        }

        $oldValue = $record->cumul_collectif;
        $newValue = $this->cumulCalculator->calculateCollectiveCumul(
            $anomaly->distributeur_id,
            $anomaly->period
        );

        if ($newValue < $record->cumul_individuel) {
            $newValue = $record->cumul_individuel;
        }

        $record->cumul_collectif = $newValue;
        $record->save();

        MLMCleaningLog::logChange(
            $this->session->id,
            $anomaly->distributeur_id,
            $anomaly->period,
            'level_currents',
            'cumul_collectif',
            $oldValue,
            $newValue,
            MLMCleaningLog::ACTION_UPDATE,
            'Cumul collectif recalculé'
        );

        return true;
    }

    /**
     * Corriger un grade incorrect
     */
    protected function fixGrade(MLMCleaningAnomaly $anomaly): bool
    {
        $record = LevelCurrent::where('distributeur_id', $anomaly->distributeur_id)
            ->where('period', $anomaly->period)
            ->first();

        if (!$record) {
            return false;
        }

        $oldValue = $record->etoiles;
        $newValue = $this->gradeCalculator->calculateGrade(
            $anomaly->distributeur_id,
            $anomaly->period
        );

        $record->etoiles = $newValue;
        $record->save();

        // Mettre à jour aussi dans distributeurs
        $distributeur = Distributeur::find($anomaly->distributeur_id);
        if ($distributeur) {
            $distributeur->etoiles_id = $newValue;
            $distributeur->save();
        }

        MLMCleaningLog::logChange(
            $this->session->id,
            $anomaly->distributeur_id,
            $anomaly->period,
            'level_currents',
            'etoiles',
            $oldValue,
            $newValue,
            MLMCleaningLog::ACTION_UPDATE,
            'Grade recalculé selon les conditions'
        );

        return true;
    }

    /**
     * Générer un preview des corrections
     */
    public function generatePreview(): array
    {
        $anomalies = MLMCleaningAnomaly::where('session_id', $this->session->id)
            ->where('is_fixed', false)
            ->with('distributeur')
            ->limit(config('mlm-cleaning.reports.max_anomalies_in_preview', 100))
            ->get();

        $preview = [
            'total_anomalies' => MLMCleaningAnomaly::where('session_id', $this->session->id)->count(),
            'auto_fixable' => MLMCleaningAnomaly::where('session_id', $this->session->id)
                ->where('can_auto_fix', true)
                ->count(),
            'manual_required' => MLMCleaningAnomaly::where('session_id', $this->session->id)
                ->where('can_auto_fix', false)
                ->count(),
            'by_type' => [],
            'sample_corrections' => []
        ];

        // Grouper par type
        $byType = MLMCleaningAnomaly::where('session_id', $this->session->id)
            ->select('type', DB::raw('count(*) as count'))
            ->groupBy('type')
            ->get();

        foreach ($byType as $type) {
            $preview['by_type'][$type->type] = [
                'count' => $type->count,
                'label' => $this->getTypeLabel($type->type)
            ];
        }

        // Échantillon de corrections
        foreach ($anomalies as $anomaly) {
            $correction = [
                'id' => $anomaly->id,
                'distributeur' => [
                    'id' => $anomaly->distributeur_id,
                    'matricule' => $anomaly->distributeur->distributeur_id ?? 'N/A',
                    'nom' => $anomaly->distributeur->nom_distributeur ?? 'N/A'
                ],
                'period' => $anomaly->period,
                'type' => $anomaly->getTypeLabel(),
                'severity' => $anomaly->getSeverityLabel(),
                'description' => $anomaly->description,
                'current_value' => $anomaly->current_value,
                'expected_value' => $anomaly->expected_value,
                'can_auto_fix' => $anomaly->can_auto_fix,
                'suggested_fix' => $anomaly->getSuggestedFix()
            ];

            $preview['sample_corrections'][] = $correction;
        }

        return $preview;
    }

    /**
     * Finaliser le processus
     */
    protected function finalize(): void
    {
        $progress = MLMCleaningProgress::createForStep(
            $this->session->id,
            MLMCleaningProgress::STEP_FINALIZATION,
            1
        );

        // Recalculer les statistiques finales
        $this->updateFinalStats();

        // Marquer la session comme terminée
        $this->session->updateStatus(MLMCleaningSession::STATUS_COMPLETED);

        $progress->markAsCompleted('Processus finalisé');

        Log::info("MLM cleaning session completed: {$this->session->session_code}");
    }

    /**
     * Mettre à jour les statistiques finales
     */
    protected function updateFinalStats(): void
    {
        $fixedAnomalies = MLMCleaningAnomaly::where('session_id', $this->session->id)
            ->where('is_fixed', true)
            ->count();

        $totalLogs = MLMCleaningLog::where('session_id', $this->session->id)->count();

        $this->session->update([
            'records_corrected' => $fixedAnomalies
        ]);
    }

    /**
     * Rollback d'une session
     */
    public function rollbackSession(MLMCleaningSession $session): bool
    {
        if (!$session->canBeRolledBack()) {
            throw new \Exception("Cette session ne peut pas être annulée");
        }

        DB::beginTransaction();

        try {
            // Récupérer tous les logs de modification
            $logs = MLMCleaningLog::where('session_id', $session->id)
                ->where('action', '!=', MLMCleaningLog::ACTION_SKIP)
                ->orderBy('id', 'desc')
                ->get();

            foreach ($logs as $log) {
                $this->rollbackChange($log);
            }

            // Marquer la session comme annulée
            $session->updateStatus(MLMCleaningSession::STATUS_ROLLED_BACK);

            DB::commit();

            Log::info("MLM cleaning session rolled back: {$session->session_code}");

            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Rollback failed for session {$session->session_code}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Annuler une modification
     */
    protected function rollbackChange(MLMCleaningLog $log): void
    {
        $rollbackData = $log->getRollbackData();

        switch ($rollbackData['table']) {
            case 'level_currents':
                $record = LevelCurrent::where('distributeur_id', $rollbackData['distributeur_id'])
                    ->where('period', $rollbackData['period'])
                    ->first();

                if ($record) {
                    $record->{$rollbackData['field']} = $rollbackData['value'];
                    $record->save();
                }
                break;

            case 'distributeurs':
                $record = Distributeur::find($rollbackData['distributeur_id']);
                if ($record) {
                    $record->{$rollbackData['field']} = $rollbackData['value'];
                    $record->save();
                }
                break;
        }
    }

    /**
     * Obtenir le label d'un type d'anomalie
     */
    protected function getTypeLabel(string $type): string
    {
        return match($type) {
            MLMCleaningAnomaly::TYPE_HIERARCHY_LOOP => 'Boucle hiérarchique',
            MLMCleaningAnomaly::TYPE_ORPHAN_PARENT => 'Parent orphelin',
            MLMCleaningAnomaly::TYPE_CUMUL_INDIVIDUAL_NEGATIVE => 'Cumul individuel négatif',
            MLMCleaningAnomaly::TYPE_CUMUL_COLLECTIVE_LESS_THAN_INDIVIDUAL => 'Cumul collectif invalide',
            MLMCleaningAnomaly::TYPE_CUMUL_DECREASE => 'Diminution de cumul',
            MLMCleaningAnomaly::TYPE_GRADE_REGRESSION => 'Régression de grade',
            MLMCleaningAnomaly::TYPE_GRADE_SKIP => 'Saut de grade',
            MLMCleaningAnomaly::TYPE_GRADE_CONDITIONS_NOT_MET => 'Conditions de grade non remplies',
            MLMCleaningAnomaly::TYPE_MISSING_PERIOD => 'Période manquante',
            MLMCleaningAnomaly::TYPE_DUPLICATE_PERIOD => 'Période dupliquée',
            default => 'Autre'
        };
    }

    /**
     * Initialiser les services
     */
    protected function initializeServices(): void
    {
        $this->analyzer = new MLMDataAnalyzer($this->session);
        $this->gradeCalculator = new MLMGradeCalculator();
        $this->cumulCalculator = new MLMCumulCalculator();
        $this->reportService = new MLMCleaningReportService($this->session);
    }

    /**
     * Nettoyer une période spécifique
     */
    public function cleanPeriod(string $period, array $options = []): array
    {
        $options['period_start'] = $period;
        $options['period_end'] = $period;
        $options['type'] = MLMCleaningSession::TYPE_PERIOD;

        $session = $this->startCleaningSession($options);
        return $this->execute($session, $options);
    }

    /**
     * Nettoyer un distributeur spécifique
     */
    public function cleanDistributor(int $distributeurId, array $options = []): array
    {
        $options['distributeur_id'] = $distributeurId;
        $options['type'] = MLMCleaningSession::TYPE_DISTRIBUTOR;

        $session = $this->startCleaningSession($options);

        // Adapter l'analyse pour un seul distributeur
        $this->analyzer = new MLMDataAnalyzer($session);

        return $this->execute($session, $options);
    }
}
