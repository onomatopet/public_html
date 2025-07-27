<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DataCorrectionService; // Importe le service
use Illuminate\Http\Request;

class CorrectionController extends Controller
{
    protected DataCorrectionService $correctionService;

    // Injection via constructeur
    public function __construct(DataCorrectionService $correctionService)
    {
        $this->correctionService = $correctionService;
        // Appliquer les middlewares nécessaires (auth, admin...) ici ou sur la route
        // $this->middleware(['auth', 'admin']);
    }

    /**
     * Fonction interne ou méthode de contrôleur pour lancer la correction.
     * Renommée pour éviter la confusion avec un nom trop générique.
     */
    public function fonctionCorrigeProbleme() // Gardé ton nom d'appel
    {
        // Appelle la méthode du service
        $result = $this->correctionService->supprimerDoublonsPeriodZeroPv();

        // Retourne le résultat (peut-être à une vue, ou juste un message)
        return $result; // Retourne le tableau ['doublons_zero_supprimes' => ..., 'groupes_verifies' => ...]
    }

    /**
     * Exemple d'action de contrôleur appelée par une route (ex: via un bouton admin)
     */
    public function lancerCorrectionViaWeb(Request $request)
    {
        // Appelle la fonction interne/méthode
        $result = $this->fonctionCorrigeProbleme();

        // Rediriger l'administrateur avec un message de succès ou d'erreur
        if (isset($result['error'])) {
            return redirect()->back()->with('error', $result['error']);
        } else {
            return redirect()->back()->with('success', $result['message'] ?? 'Correction effectuée.');
        }
    }
}
