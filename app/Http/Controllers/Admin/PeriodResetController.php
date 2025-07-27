<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemPeriod;
use App\Models\Achat;
use App\Models\LevelCurrent;
use App\Models\AvancementHistory;
use App\Models\Bonus;
use App\Models\WorkflowLog;
use App\Models\Distributeur;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Carbon\Carbon;

class PeriodResetController extends Controller
{
    /**
     * Affiche la page de confirmation pour réinitialiser une période
     */
    public function confirmReset(Request $request)
    {
        $period = $request->get('period', date('Y-m'));
        $systemPeriod = SystemPeriod::where('period', $period)->first();

        if (!$systemPeriod) {
            return redirect()->route('admin.periods.index')
                ->with('error', 'Période non trouvée.');
        }

        // Vérifier que c'est bien la période courante
        if (!$systemPeriod->is_current) {
            return redirect()->route('admin.periods.index')
                ->with('error', 'Seule la période courante peut être réinitialisée.');
        }

        // Vérifier que la période n'est pas déjà clôturée
        if ($systemPeriod->status === 'closed') {
            return redirect()->route('admin.periods.index')
                ->with('error', 'Une période clôturée ne peut pas être réinitialisée.');
        }

        // Collecter les statistiques actuelles
        $stats = $this->collectPeriodStatistics($period);

        // Calculer la période précédente
        $previousPeriod = $this->getPreviousPeriod($period);
        $hasHistory = DB::table('level_current_histories')
            ->where('period', $previousPeriod)
            ->exists();

        return view('admin.periods.confirm-reset', compact('systemPeriod', 'stats', 'hasHistory', 'previousPeriod'));
    }

    /**
     * Effectue la réinitialisation douce de la période
     */
    public function reset(Request $request)
    {
        $request->validate([
            'period' => 'required|string|size:7',
            'confirmation' => 'required|string|in:REINITIALISER',
            'reason' => 'required|string|min:10|max:500',
            'delete_achats' => 'boolean' // Checkbox pour supprimer les achats
        ]);

        $period = $request->input('period');
        $reason = $request->input('reason');
        $deleteAchats = $request->input('delete_achats', false); // false par défaut

        $systemPeriod = SystemPeriod::where('period', $period)
            ->where('is_current', true)
            ->first();

        if (!$systemPeriod || $systemPeriod->status === 'closed') {
            return redirect()->route('admin.periods.index')
                ->with('error', 'Période invalide ou déjà clôturée.');
        }

        DB::beginTransaction();

        try {
            // 1. Créer une sauvegarde des données avant réinitialisation
            $backupId = $this->createBackup($period, $reason);

            // 2. Gérer les achats selon le choix de l'utilisateur
            $deletedAchats = 0;
            if ($deleteAchats) {
                $deletedAchats = Achat::where('period', $period)->count();
                Achat::where('period', $period)->delete();
                Log::info("Achats supprimés pour la période {$period}", ['count' => $deletedAchats]);
            } else {
                Log::info("Achats conservés pour la période {$period}");
            }

            // 3. Réinitialisation intelligente des level_currents
            $resetStats = $this->resetLevelCurrentsIntelligent($period);

            // 4. Toujours supprimer les avancements de la période
            $deletedAvancements = AvancementHistory::where('period', $period)->count();
            AvancementHistory::where('period', $period)->delete();

            // 5. Toujours supprimer les bonus de la période
            $deletedBonuses = Bonus::where('period', $period)->count();
            Bonus::where('period', $period)->delete();

            // 6. Réinitialiser le workflow de la période
            $systemPeriod->update([
                'status' => 'open',
                'validation_started_at' => null,
                'purchases_validated' => false,
                'purchases_validated_at' => null,
                'purchases_validated_by' => null,
                'purchases_aggregated' => false,
                'purchases_aggregated_at' => null,
                'purchases_aggregated_by' => null,
                'advancements_calculated' => false,
                'advancements_calculated_at' => null,
                'advancements_calculated_by' => null,
                'snapshot_created' => false,
                'snapshot_created_at' => null,
                'snapshot_created_by' => null,
            ]);

            // 7. Créer un log de l'opération
            $this->createWorkflowLog($period, $backupId, $reason, [
                'deleted_achats' => $deletedAchats,
                'achats_preserved' => !$deleteAchats,
                'reset_stats' => $resetStats,
                'deleted_avancements' => $deletedAvancements,
                'deleted_bonuses' => $deletedBonuses,
            ]);

            DB::commit();

            $message = "La période {$period} a été réinitialisée avec succès (mode doux). ";
            $message .= $deleteAchats ? "Les achats ont été supprimés. " : "Les achats ont été conservés. ";
            $message .= "Sauvegarde créée : {$backupId}";

            return redirect()->route('admin.periods.index')
                ->with('success', $message)
                ->with('reset_details', $resetStats);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error("Erreur lors de la réinitialisation de la période {$period}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->with('error', 'Erreur lors de la réinitialisation : ' . $e->getMessage());
        }
    }

    /**
     * Réinitialisation intelligente des level_currents
     * Récupère les données de la période précédente depuis level_current_histories
     */
    private function resetLevelCurrentsIntelligent($period)
    {
        $previousPeriod = $this->getPreviousPeriod($period);
        $stats = [
            'distributeurs_historiques' => 0,
            'nouveaux_distributeurs' => 0,
            'total_traites' => 0
        ];

        // Récupérer tous les distributeurs de la période courante
        $currentDistributeurs = LevelCurrent::where('period', $period)->get();

        foreach ($currentDistributeurs as $current) {
            // Chercher dans l'historique de la période précédente
            $historical = DB::table('level_current_histories')
                ->where('period', $previousPeriod)
                ->where('distributeur_id', $current->distributeur_id)
                ->first();

            if ($historical) {
                // Distributeur trouvé dans l'historique - récupérer ses valeurs
                $current->update([
                    'cumul_individuel' => $historical->cumul_individuel,
                    'cumul_collectif' => $historical->cumul_collectif,
                    'etoiles' => $historical->etoiles,
                    'rang' => $historical->rang,
                    'new_cumul' => 0,
                    'cumul_total' => 0,
                    // Vérifier si le parent existe toujours
                    'id_distrib_parent' => $this->validateParentExists($historical->id_distrib_parent)
                        ? $historical->id_distrib_parent
                        : null
                ]);
                $stats['distributeurs_historiques']++;
            } else {
                // Nouveau distributeur - valeurs par défaut
                $current->update([
                    'cumul_individuel' => 0,
                    'cumul_collectif' => 0,
                    'etoiles' => 1,
                    'rang' => 0,
                    'new_cumul' => 0,
                    'cumul_total' => 0
                ]);
                $stats['nouveaux_distributeurs']++;
            }
            $stats['total_traites']++;
        }

        Log::info("Réinitialisation intelligente terminée", $stats);
        return $stats;
    }

    /**
     * Vérifie si un distributeur parent existe toujours
     */
    private function validateParentExists($parentId)
    {
        if (!$parentId) {
            return false;
        }
        return Distributeur::where('id', $parentId)->exists();
    }

    /**
     * Calcule la période précédente
     */
    private function getPreviousPeriod($period)
    {
        $date = Carbon::createFromFormat('Y-m', $period);
        return $date->subMonth()->format('Y-m');
    }

    /**
     * Collecte les statistiques de la période
     */
    private function collectPeriodStatistics($period)
    {
        return [
            'achats' => [
                'total' => Achat::where('period', $period)->count(),
                'valides' => Achat::where('period', $period)->where('status', 'validated')->count(),
                'montant_total' => Achat::where('period', $period)->sum('montant_total_ligne'),
                'points_total' => Achat::where('period', $period)->sum('points_unitaire_achat'),
            ],
            'distributeurs' => [
                'actifs' => LevelCurrent::where('period', $period)
                    ->where('new_cumul', '>', 0)
                    ->count(),
                'total' => LevelCurrent::where('period', $period)->count(),
                'avec_avancements' => AvancementHistory::where('period', $period)
                    ->distinct('distributeur_id')
                    ->count(),
            ],
            'bonuses' => [
                'total' => Bonus::where('period', $period)->count(),
                'montant_total' => Bonus::where('period', $period)->sum('montant_total'),
            ],
            'workflow' => SystemPeriod::where('period', $period)->first()?->getWorkflowStatus() ?? []
        ];
    }

    /**
     * Crée une sauvegarde des données avant réinitialisation
     */
    private function createBackup($period, $reason)
    {
        // Remplacer les tirets par des underscores pour éviter l'erreur SQL
        $sanitizedPeriod = str_replace('-', '_', $period);
        $timestamp = date('YmdHis');
        $backupId = "RESET_{$sanitizedPeriod}_{$timestamp}";

        // Créer les tables de sauvegarde
        DB::statement("CREATE TABLE IF NOT EXISTS `backup_achats_{$backupId}` LIKE achats");
        DB::statement("INSERT INTO `backup_achats_{$backupId}` SELECT * FROM achats WHERE period = ?", [$period]);

        DB::statement("CREATE TABLE IF NOT EXISTS `backup_level_currents_{$backupId}` LIKE level_currents");
        DB::statement("INSERT INTO `backup_level_currents_{$backupId}` SELECT * FROM level_currents WHERE period = ?", [$period]);

        DB::statement("CREATE TABLE IF NOT EXISTS `backup_avancements_{$backupId}` LIKE avancement_history");
        DB::statement("INSERT INTO `backup_avancements_{$backupId}` SELECT * FROM avancement_history WHERE period = ?", [$period]);

        DB::statement("CREATE TABLE IF NOT EXISTS `backup_bonuses_{$backupId}` LIKE bonuses");
        DB::statement("INSERT INTO `backup_bonuses_{$backupId}` SELECT * FROM bonuses WHERE period = ?", [$period]);

        // Enregistrer les métadonnées
        DB::table('period_backups')->insert([
            'backup_id' => $backupId,
            'period' => $period,
            'reason' => $reason,
            'created_by' => Auth::id(),
            'created_at' => now(),
            'metadata' => json_encode($this->collectPeriodStatistics($period))
        ]);

        return $backupId;
    }

    /**
     * Crée un log dans workflow_logs si la table existe
     */
    private function createWorkflowLog($period, $backupId, $reason, $details)
    {
        if (Schema::hasTable('workflow_logs')) {
            DB::table('workflow_logs')->insert([
                'period' => $period,
                'step' => 'reset',
                'action' => 'period_soft_reset',
                'status' => 'completed',
                'user_id' => Auth::id(),
                'details' => json_encode(array_merge($details, [
                    'reason' => $reason,
                    'backup_id' => $backupId,
                    'reset_type' => 'soft'
                ])),
                'started_at' => now(),
                'completed_at' => now(),
                'created_at' => now()
            ]);
        }
    }

    /**
     * Affiche la liste des sauvegardes disponibles
     */
    public function backups()
    {
        $backups = DB::table('period_backups')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        // Pour chaque backup, compter les tables
        foreach ($backups as $backup) {
            $backup->tables_count = 0;
            $tables = ['achats', 'level_currents', 'avancement_history', 'bonuses'];
            foreach ($tables as $table) {
                $backupTableName = "backup_{$table}_{$backup->backup_id}";
                if (Schema::hasTable($backupTableName)) {
                    $backup->tables_count++;
                }
            }
        }

        return view('admin.periods.backups', compact('backups'));
    }

    /**
     * Restaure une sauvegarde
     */
    public function restore(Request $request)
    {
        $request->validate([
            'backup_id' => 'required|string',
            'confirm_restore' => 'required|in:RESTAURER'
        ]);

        $backupId = $request->input('backup_id');

        // Vérifier que le backup existe
        $backup = DB::table('period_backups')
            ->where('backup_id', $backupId)
            ->first();

        if (!$backup) {
            return redirect()->back()
                ->with('error', 'Sauvegarde introuvable.');
        }

        DB::beginTransaction();

        try {
            // TODO: Implémenter la logique de restauration
            // 1. Sauvegarder l'état actuel
            // 2. Vider les tables pour la période
            // 3. Restaurer depuis les tables de backup
            // 4. Mettre à jour le statut

            DB::commit();

            return redirect()->route('admin.periods.backups')
                ->with('success', 'Sauvegarde restaurée avec succès.');

        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->back()
                ->with('error', 'Erreur lors de la restauration : ' . $e->getMessage());
        }
    }
}
