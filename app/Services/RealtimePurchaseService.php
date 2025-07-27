<?php
// app/Services/RealtimePurchaseService.php

namespace App\Services;

use App\Models\Level_current_test;
use App\Models\Distributeur; // Pour obtenir les infos de base si création
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class RealtimePurchaseService
{
    public function processPurchase(string $distributeurMatricule, float $totalNewAchats, string $period): array
    {
        // Validation des entrées (simple)
        if ($totalNewAchats <= 0) {
            return ['success' => false, 'message' => 'Le montant des achats doit être positif.'];
        }
        if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
            return ['success' => false, 'message' => 'Format de période invalide.'];
        }

        Log::info("[RTP] Traitement achat pour Distributeur: {$distributeurMatricule}, Montant: {$totalNewAchats}, Période: {$period}");

        DB::beginTransaction();
        try {
            // --- 1. Traiter le distributeur acheteur ---
            $acheteurLevelRecord = $this->updateOrCreateLevelRecordForBuyer(
                $distributeurMatricule,
                $totalNewAchats,
                $period
            );

            if (!$acheteurLevelRecord) {
                // L'erreur aura été loggée dans la méthode helper
                throw new \Exception("Impossible de traiter l'enregistrement de l'acheteur {$distributeurMatricule}.");
            }

            // --- 2. Propager aux parrains ---
            if (!empty($acheteurLevelRecord->id_distrib_parent)) {
                $this->propagateToUpline(
                    $acheteurLevelRecord->id_distrib_parent, // Matricule du premier parent
                    $totalNewAchats,
                    $period
                );
            }

            DB::commit();
            Log::info("[RTP] Achat traité avec succès pour Distributeur: {$distributeurMatricule}.");
            return ['success' => true, 'message' => 'Achat enregistré et cumuls mis à jour avec succès.'];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("[RTP] Erreur lors du traitement de l'achat pour {$distributeurMatricule}: " . $e->getMessage(), ['exception' => $e]);
            return ['success' => false, 'message' => "Une erreur est survenue: " . $e->getMessage()];
        }
    }

    private function updateOrCreateLevelRecordForBuyer(string $distributeurMatricule, float $totalNewAchats, string $period): ?Level_current_test
    {
        $levelRecord = Level_current_test::where('distributeur_id', $distributeurMatricule)
                                      ->where('period', $period)
                                      ->first();
        if ($levelRecord) {
            // Mise à jour
            $levelRecord->increment('new_cumul', $totalNewAchats);
            $levelRecord->increment('cumul_individuel', $totalNewAchats);
            $levelRecord->increment('cumul_total', $totalNewAchats);
            $levelRecord->increment('cumul_collectif', $totalNewAchats); // Règle 1
            // 'updated_at' est géré par Eloquent sur increment si pas désactivé
            // Si increment ne met pas à jour updated_at, il faut le faire manuellement:
            // $levelRecord->touch(); ou $levelRecord->update(['updated_at' => Carbon::now()]);
            // Après les increments, l'objet $levelRecord n'a pas les nouvelles valeurs, il faudrait le refresh() si on le retourne.
            // Pour cette fonction, on peut retourner le résultat de la recherche,
            // ou simplement un booléen de succès et récupérer l'ID parent.
            // Retournons le modèle mis à jour pour récupérer id_distrib_parent.
            return $levelRecord->fresh(); // Recharge le modèle avec les données à jour

        } else {
            // Création
            $DistributeurInfo = Distributeur::where('distributeur_id', $distributeurMatricule)
                                         ->select('id_distrib_parent', 'rang')
                                         ->first();
            if (!$DistributeurInfo) {
                Log::error("[RTP] Impossible de créer Level_current_test: Distributeur maître non trouvé pour matricule {$distributeurMatricule}.");
                return null;
            }

            return Level_current_test::create([
                'distributeur_id'   => $distributeurMatricule,
                'period'            => $period,
                'rang'              => $DistributeurInfo->rang ?? null,
                'etoiles'           => 1,
                'new_cumul'         => $totalNewAchats, // Incrément sur champ qui était à 0
                'cumul_individuel'  => $totalNewAchats,
                'cumul_total'       => $totalNewAchats,
                'cumul_collectif'   => $totalNewAchats, // Règle 1
                'id_distrib_parent' => $DistributeurInfo->id_distrib_parent ?? null,
                // created_at et updated_at gérés par Eloquent create()
            ]);
        }
    }

    private function propagateToUpline(?string $parentMatricule, float $achatsARemonter, string $period, int $depth = 0)
    {
        $maxDepth = 20; // Sécurité anti-boucle

        if (empty($parentMatricule) || $depth >= $maxDepth) {
            if ($depth >= $maxDepth) Log::error("[RTP] Profondeur maximale atteinte lors de la propagation à l'upline depuis parent {$parentMatricule}.");
            return;
        }

        $parentLevelRecord = Level_current_test::where('distributeur_id', $parentMatricule)
                                          ->where('period', $period)
                                          ->first();

        if ($parentLevelRecord) {
            // Mise à jour du parent existant
            $parentLevelRecord->increment('cumul_total', $achatsARemonter);
            $parentLevelRecord->increment('cumul_collectif', $achatsARemonter);
            // $parentLevelRecord->touch(); // Si besoin de mettre à jour updated_at

            Log::debug("[RTP] Propagation: Parent {$parentMatricule} mis à jour avec {$achatsARemonter}.");
            // Appel récursif pour le parent du parent
            $this->propagateToUpline($parentLevelRecord->id_distrib_parent, $achatsARemonter, $period, $depth + 1);

        } else {
            // Le parent n'a pas d'enregistrement pour cette période, il faut le créer
            $parentDistributeurInfo = Distributeur::where('distributeur_id', $parentMatricule)
                                                 ->select('id_distrib_parent', 'rang')
                                                 ->first();
            if ($parentDistributeurInfo) {
                Log::debug("[RTP] Propagation: Création Level_current_test pour parent {$parentMatricule} et période {$period}.");
                $nouveauParentLevelRecord = Level_current_test::create([
                    'distributeur_id'   => $parentMatricule,
                    'period'            => $period,
                    'rang'              => $parentDistributeurInfo->rang ?? null,
                    'etoiles'           => 1,
                    'new_cumul'         => 0, // Ce parent n'a pas fait l'achat direct
                    'cumul_individuel'  => 0,
                    'cumul_total'       => $achatsARemonter, // Reçoit le cumul
                    'cumul_collectif'   => $achatsARemonter, // Reçoit le cumul
                    'id_distrib_parent' => $parentDistributeurInfo->id_distrib_parent ?? null,
                ]);
                // Appel récursif pour le parent du parent nouvellement créé
                $this->propagateToUpline($nouveauParentLevelRecord->id_distrib_parent, $achatsARemonter, $period, $depth + 1);
            } else {
                Log::warning("[RTP] Propagation: Parent maître non trouvé pour matricule {$parentMatricule}. Arrêt de la propagation.");
            }
        }
    }
}
