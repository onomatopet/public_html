<?php
// Dans un service, ex: app/Services/UplineUpdateService.php
namespace App\Services;
use App\Models\Level_current_test;
use Illuminate\Support\Facades\DB; // Si besoin de transactions plus globales
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class UplineUpdateService
{
    /**
     * @param string $id_distrib_fils_matricule Matricule du distributeur dont les achats remontent.
     * @param float $achatsDuFils Montant des achats (new_cumul) du fils à remonter.
     * @param string $period
     * @return array Trace des mises à jour (optionnel).
     */
    public function propagatePurchasesToUpline(string $id_distrib_fils_matricule, float $achatsDuFils, string $period): array
    {
        Log::debug("[UPU] Propagation des achats {$achatsDuFils} du fils {$id_distrib_fils_matricule} pour période {$period}.");
        $trace = [];
        $currentParentMatricule = null;

        // Récupérer le parent direct du fils pour cette période
        $filsRecord = Level_current_test::where('distributeur_id', $id_distrib_fils_matricule)
                                    ->where('period', $period)
                                    ->select('id_distrib_parent')
                                    ->first();

        if ($filsRecord && !empty($filsRecord->id_distrib_parent)) {
            $currentParentMatricule = $filsRecord->id_distrib_parent;
        } else {
            Log::debug("[UPU] Fils {$id_distrib_fils_matricule} n'a pas de parent pour période {$period}, ou fils non trouvé.");
            return $trace; // Pas de parent, pas de propagation
        }

        $depth = 0; // Pour éviter les boucles infinies
        $maxDepth = 20; // Limite de sécurité

        while ($currentParentMatricule && $achatsDuFils > 0 && $depth < $maxDepth) {
            $parentRecord = Level_current_test::where('distributeur_id', $currentParentMatricule)
                                          ->where('period', $period)
                                          ->first(); // select('distributeur_id', 'id_distrib_parent', 'cumul_total', 'cumul_collectif')

            if ($parentRecord) {
                $oldTotal = $parentRecord->cumul_total;
                $oldCollectif = $parentRecord->cumul_collectif;

                // Utiliser une requête UPDATE directe pour l'atomicité
                $updatedRows = Level_current_test::where($parentRecord->getKeyName(), $parentRecord->getKey()) // Cibler par PK
                    ->update([
                        'cumul_total' => DB::raw("cumul_total + " . $achatsDuFils),
                        'cumul_collectif' => DB::raw("cumul_collectif + " . $achatsDuFils),
                        'updated_at' => Carbon::now() // Mettre à jour manuellement
                    ]);

                if ($updatedRows > 0) {
                    $traceEntry = [
                        'parent_matricule' => $currentParentMatricule,
                        'achats_reçus' => $achatsDuFils,
                        'cumul_total_avant' => $oldTotal,
                        'cumul_total_apres' => $oldTotal + $achatsDuFils, // Approximation car on n'a pas refresh
                        'cumul_collectif_avant' => $oldCollectif,
                        'cumul_collectif_apres' => $oldCollectif + $achatsDuFils,
                    ];
                    $trace[] = $traceEntry;
                    Log::debug("[UPU] Parent {$currentParentMatricule} mis à jour avec {$achatsDuFils}. Période {$period}.");
                } else {
                     Log::warning("[UPU] Parent {$currentParentMatricule} trouvé mais non mis à jour (peut-être pas de changement ou erreur). Période {$period}.");
                }
                $currentParentMatricule = $parentRecord->id_distrib_parent; // Passer au parent suivant
            } else {
                Log::warning("[UPU] Parent {$currentParentMatricule} non trouvé dans Level_current_test pour période {$period}. Arrêt propagation.");
                $trace[] = ['parent_matricule' => $currentParentMatricule, 'status' => 'Non trouvé, arrêt.'];
                break; // Arrêter si un parent n'est pas trouvé pour la période
            }
            $depth++;
        }
        if ($depth >= $maxDepth) {
             Log::error("[UPU] Profondeur maximale atteinte pour la propagation depuis fils {$id_distrib_fils_matricule}. Vérifier les boucles de parenté.");
        }
        return $trace;
    }
}
