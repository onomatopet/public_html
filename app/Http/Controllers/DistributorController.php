<?php

namespace App\Http\Controllers;

use App\Models\Distributor;    // Modèle pour level_current
use App\Models\LevelHistory; // Modèle pour level_history
use App\Services\DistributorRankService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DistributorController extends Controller
{
    protected DistributorRankService $rankService;

    public function __construct(DistributorRankService $rankService)
    {
        $this->rankService = $rankService;
    }

    /**
     * Affiche le statut ACTUEL et le potentiel de grade ACTUEL.
     */
    public function showCurrentStatus(Distributor $distributor) // Route Model Binding sur level_current
    {
        Log::debug("Affichage statut actuel pour ID: " . $distributor->distributeur_id);
        $currentRank = $distributor->etoiles;
        // Calcul du potentiel basé sur les données actuelles
        $potentialRank = $this->rankService->calculatePotentialRank($distributor);

        return view('distributors.status_current', [ // Vue dédiée à l'état actuel
            'distributor' => $distributor,
            'currentEtoiles' => $currentRank,
            'potentialEtoiles' => $potentialRank,
        ]);
    }

    /**
     * Déclenche le recalcul et la mise à jour du grade ACTUEL dans level_current.
     */
    public function updateCurrentRank(Distributor $distributor) // Route Model Binding sur level_current
    {
        Log::info("Tentative de mise à jour du grade actuel pour ID: " . $distributor->distributeur_id);
        $potentialRank = $this->rankService->calculatePotentialRank($distributor);

        if ($potentialRank > $distributor->etoiles) {
            $oldRank = $distributor->etoiles;
            $distributor->etoiles = $potentialRank;
            // Mettre à jour aussi les cumuls si le service les a modifiés ? Probablement pas.
            $distributor->save(); // Sauvegarde dans level_current
            Log::info("Grade actuel mis à jour pour ID {$distributor->distributeur_id}: {$oldRank} -> {$potentialRank}");
            return redirect()->route('distributors.status.current', $distributor)->with('success', "Grade actuel mis à jour !");
        } else {
            Log::info("Aucune promotion actuelle pour ID {$distributor->distributeur_id}");
            return redirect()->route('distributors.status.current', $distributor)->with('info', 'Aucune promotion possible actuellement.');
        }
    }

    /**
     * Affiche le statut HISTORIQUE pour une période donnée.
     * NE PAS utiliser le Route Model Binding pour la période.
     */
    public function showHistory(Request $request, Distributor $distributor, string $period)
    {
        Log::debug("Recherche historique pour ID: {$distributor->distributeur_id}, Période: {$period}");

        // Valider le format de la période si nécessaire
        // if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
        //     abort(400, 'Format de période invalide. Utilisez YYYY-MM.');
        // }

        // Chercher l'enregistrement dans level_history
        $historyRecord = LevelHistory::where('distributeur_id', $distributor->distributeur_id)
                                     ->where('period', $period)
                                     ->first(); // Ou firstOrFail() pour erreur 404 si non trouvé

        if (!$historyRecord) {
            Log::warning("Historique non trouvé pour ID {$distributor->distributeur_id}, Période {$period}");
            // Gérer l'erreur: redirection, message, 404...
             return redirect()->route('distributors.status.current', $distributor) // Redirige vers l'état actuel
                         ->with('error', "Aucun historique trouvé pour la période {$period}.");
            // Ou : abort(404, "Aucun historique trouvé pour la période {$period}.");
        }

        Log::info("Affichage historique trouvé pour ID {$distributor->distributeur_id}, Période {$period}");
        return view('distributors.status_history', [ // Vue dédiée à l'historique
            'distributor' => $distributor, // Pour le nom, etc. (depuis level_current)
            'history' => $historyRecord, // Les données du snapshot historique
            'period' => $period,
        ]);
    }
}
