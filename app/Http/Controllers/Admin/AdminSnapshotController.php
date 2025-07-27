<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\SnapshotService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class AdminSnapshotController extends Controller
{
    /**
     * Instance du service de snapshot.
     * @var SnapshotService
     */
    protected SnapshotService $snapshotService;

    /**
     * Constructeur pour injecter les dépendances.
     *
     * @param SnapshotService $snapshotService Le service pour créer les snapshots.
     */
    public function __construct(SnapshotService $snapshotService)
    {
        $this->snapshotService = $snapshotService;
    }

    /**
     * Affiche le formulaire permettant à l'admin de lancer la création d'un snapshot.
     *
     * @return \Illuminate\Contracts\View\View|\Illuminate\Contracts\View\Factory
     */
    public function create()
    {
        // Suggérer la période du mois précédent comme valeur par défaut
        $suggestedPeriod = now()->subMonth()->format('Y-m');

        Log::debug("Affichage du formulaire de création de snapshot.", ['suggested_period' => $suggestedPeriod]);

        // Retourne la vue Blade
        return view('admin.snapshots.create', compact('suggestedPeriod'));
    }

    /**
     * Traite la soumission du formulaire et lance la création du snapshot.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        Log::info("Requête Administrateur reçue pour stocker un nouveau snapshot.");

        // Validation des données du formulaire
        $validator = Validator::make($request->all(), [
            'period' => [
                'required',
                'string',
                'regex:/^\d{4}-\d{2}$/'
            ],
            'force' => [
                'nullable',
                'boolean'
            ],
        ]);

        // Si la validation échoue...
        if ($validator->fails()) {
            Log::warning("Validation échouée pour la création de snapshot.", $validator->errors()->toArray());
            return redirect()->back()
                        ->withErrors($validator)
                        ->withInput();
        }

        // Récupérer les données validées
        $validated = $validator->validated();
        $period = $validated['period'];
        $force = $validated['force'] ?? false;

        Log::info("Validation réussie. Lancement du processus de snapshot.", ['period' => $period, 'force' => $force]);

        try {
            // Appelle la méthode du service qui fait le travail lourd
            $result = $this->snapshotService->createSnapshot($period, $force);

            // Redirection avec message de résultat
            if ($result['success']) {
                Log::info("Snapshot créé avec succès via le service.", ['result' => $result]);
                return redirect()->route('admin.snapshots.create')
                            ->with('success', $result['message']);
            } else {
                Log::warning("Échec de la création du snapshot via le service.", ['result' => $result]);
                return redirect()->back()
                            ->with('error', $result['message'])
                            ->withInput();
            }
        } catch (\Exception $e) {
            Log::error("Erreur inattendue lors de l'exécution du SnapshotService.", [
                'period' => $period,
                'force' => $force,
                'error_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()->back()
                       ->with('error', 'Une erreur serveur inattendue est survenue. Veuillez consulter les logs.')
                       ->withInput();
        }
    }
}