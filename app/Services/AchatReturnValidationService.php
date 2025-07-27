<?php

namespace App\Services;

use App\Models\Achat;
use App\Models\AchatReturnRequest;
use App\Models\LevelCurrent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AchatReturnValidationService
{
    /**
     * Valide si un achat peut être annulé/retourné
     */
    public function validateReturnRequest(Achat $achat, string $type, ?int $quantityToReturn = null): array
    {
        $result = [
            'can_proceed' => true,
            'warnings' => [],
            'blockers' => [],
            'impact' => []
        ];

        // 1. Vérifier le statut de l'achat
        if ($achat->status !== 'active') {
            $result['blockers'][] = "L'achat a déjà le statut: {$achat->status}";
            $result['can_proceed'] = false;
            return $result;
        }

        // 2. Vérifier la période (pas de retour sur périodes trop anciennes)
        $monthsOld = now()->diffInMonths($achat->created_at);
        if ($monthsOld > 3) {
            $result['warnings'][] = "L'achat date de plus de 3 mois ({$monthsOld} mois)";
        }

        // 3. Pour retour partiel, vérifier la quantité
        if ($type === AchatReturnRequest::TYPE_PARTIAL_RETURN) {
            if (!$quantityToReturn || $quantityToReturn <= 0) {
                $result['blockers'][] = "Quantité à retourner invalide";
                $result['can_proceed'] = false;
                return $result;
            }

            if ($quantityToReturn > ($achat->qt - $achat->qt_retournee)) {
                $result['blockers'][] = "Quantité à retourner supérieure à la quantité disponible";
                $result['can_proceed'] = false;
                return $result;
            }
        }

        // 4. Analyser l'impact sur les calculs MLM
        $impact = $this->analyzeMLMImpact($achat, $type, $quantityToReturn);
        $result['impact'] = $impact;

        if (!empty($impact['grade_changes'])) {
            $result['warnings'][] = "Ce retour pourrait affecter les grades de distributeurs";
        }

        if (!empty($impact['bonus_impacts'])) {
            $result['warnings'][] = "Des bonus déjà calculés pourraient être impactés";
        }

        // 5. Vérifier si des processus sont en cours
        if ($this->hasActiveProcesses($achat->period)) {
            $result['warnings'][] = "Des processus de calcul sont en cours pour cette période";
        }

        return $result;
    }

    /**
     * Analyse l'impact MLM d'un retour/annulation
     */
    private function analyzeMLMImpact(Achat $achat, string $type, ?int $quantityToReturn): array
    {
        $impact = [
            'points_to_remove' => 0,
            'amount_to_refund' => 0,
            'affected_distributors' => [],
            'grade_changes' => [],
            'bonus_impacts' => []
        ];

        // Calculer les points et montant à retirer
        if ($type === AchatReturnRequest::TYPE_PARTIAL_RETURN) {
            $ratio = $quantityToReturn / $achat->qt;
            $impact['points_to_remove'] = round($achat->points_unitaire_achat * $quantityToReturn);
            $impact['amount_to_refund'] = round($achat->prix_unitaire_achat * $quantityToReturn, 2);
        } else {
            $impact['points_to_remove'] = $achat->points_unitaire_achat * $achat->qt;
            $impact['amount_to_refund'] = $achat->montant_total_ligne;
        }

        // Identifier les distributeurs affectés
        $distributorId = $achat->distributeur_id;
        $impact['affected_distributors'][] = $distributorId;

        // Vérifier l'impact sur les grades
        $currentLevel = LevelCurrent::where('distributeur_id', $distributorId)
                                   ->where('period', $achat->period)
                                   ->first();

        if ($currentLevel) {
            $newCumulIndividuel = $currentLevel->cumul_individuel - $impact['points_to_remove'];

            // Vérifier si cela affecte le grade
            $currentGrade = $currentLevel->etoiles;
            $potentialNewGrade = $this->calculateGradeFromPoints($newCumulIndividuel);

            if ($potentialNewGrade < $currentGrade) {
                $impact['grade_changes'][] = [
                    'distributeur_id' => $distributorId,
                    'current_grade' => $currentGrade,
                    'potential_new_grade' => $potentialNewGrade
                ];
            }
        }

        // Vérifier l'impact sur les bonus
        $bonuses = DB::table('bonuses')
                    ->where('period', $achat->period)
                    ->where(function($query) use ($distributorId) {
                        $query->where('distributeur_id', $distributorId)
                              ->orWhere('id_distrib_parent', $distributorId);
                    })
                    ->get();

        if ($bonuses->count() > 0) {
            $impact['bonus_impacts'] = $bonuses->map(function($bonus) {
                return [
                    'bonus_id' => $bonus->id,
                    'type' => 'Bonus déjà calculé',
                    'amount' => $bonus->montant
                ];
            })->toArray();
        }

        return $impact;
    }

    /**
     * Calcule le grade basé sur les points
     */
    private function calculateGradeFromPoints(int $points): int
    {
        // TODO: Implémenter la logique réelle basée sur vos règles métier
        if ($points >= 10000) return 5;
        if ($points >= 5000) return 4;
        if ($points >= 2500) return 3;
        if ($points >= 1000) return 2;
        return 1;
    }

    /**
     * Vérifie si des processus sont actifs
     */
    private function hasActiveProcesses(string $period): bool
    {
        // TODO: Implémenter la vérification réelle
        return false;
    }

    /**
     * Exécute le retour/annulation
     */
    public function executeReturn(AchatReturnRequest $request): array
    {
        DB::beginTransaction();
        try {
            $achat = $request->achat;
            $originalData = $achat->toArray();

            // 1. Mettre à jour l'achat
            switch ($request->type) {
                case AchatReturnRequest::TYPE_CANCELLATION:
                    $achat->update([
                        'status' => 'cancelled',
                        'cancelled_at' => now(),
                        'montant_rembourse' => $achat->montant_total_ligne
                    ]);
                    break;

                case AchatReturnRequest::TYPE_RETURN:
                    $achat->update([
                        'status' => 'returned',
                        'returned_at' => now(),
                        'qt_retournee' => $achat->qt,
                        'montant_rembourse' => $achat->montant_total_ligne
                    ]);
                    break;

                case AchatReturnRequest::TYPE_PARTIAL_RETURN:
                    $newQtRetournee = $achat->qt_retournee + $request->quantity_to_return;
                    $newMontantRembourse = $achat->montant_rembourse + $request->amount_to_refund;

                    $achat->update([
                        'status' => $newQtRetournee >= $achat->qt ? 'returned' : 'partial_return',
                        'qt_retournee' => $newQtRetournee,
                        'montant_rembourse' => $newMontantRembourse,
                        'returned_at' => $newQtRetournee >= $achat->qt ? now() : null
                    ]);
                    break;
            }

            // 2. Mettre à jour les cumuls du distributeur
            $this->updateDistributorCumuls($achat, $request);

            // 3. Logger l'audit
            Log::info("Retour/Annulation exécuté", [
                'request_id' => $request->id,
                'achat_id' => $achat->id,
                'type' => $request->type,
                'original_data' => $originalData,
                'new_data' => $achat->fresh()->toArray()
            ]);

            // 4. Marquer la demande comme complétée
            $request->update([
                'status' => AchatReturnRequest::STATUS_COMPLETED,
                'completed_at' => now()
            ]);

            DB::commit();

            return [
                'success' => true,
                'message' => 'Retour/Annulation exécuté avec succès'
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erreur lors de l'exécution du retour", [
                'request_id' => $request->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Met à jour les cumuls du distributeur après retour
     */
    private function updateDistributorCumuls(Achat $achat, AchatReturnRequest $request): void
    {
        $levelCurrent = LevelCurrent::where('distributeur_id', $achat->distributeur_id)
                                   ->where('period', $achat->period)
                                   ->first();

        if (!$levelCurrent) {
            return;
        }

        $pointsToRemove = 0;
        if ($request->type === AchatReturnRequest::TYPE_PARTIAL_RETURN) {
            $pointsToRemove = $achat->points_unitaire_achat * $request->quantity_to_return;
        } else {
            $pointsToRemove = $achat->points_unitaire_achat * $achat->qt;
        }

        $levelCurrent->decrement('cumul_individuel', $pointsToRemove);
        $levelCurrent->decrement('new_cumul', $pointsToRemove);

        Log::info("Cumuls distributeur mis à jour après retour", [
            'distributeur_id' => $achat->distributeur_id,
            'period' => $achat->period,
            'points_removed' => $pointsToRemove
        ]);
    }
}
