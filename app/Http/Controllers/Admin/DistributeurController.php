<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Distributeur;
use App\Models\AvancementHistory;
use App\Models\DeletionRequest;
use App\Services\BackupService;
use App\Services\DeletionValidationService;
use Illuminate\Http\Request;
use App\Traits\HasPermissions;
use App\Models\LevelCurrent;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Services\CumulManagementService;
use Carbon\Carbon;

class DistributeurController extends Controller
{
    private BackupService $backupService;
    private DeletionValidationService $validationService;
    private CumulManagementService $cumulService;

    public function __construct(BackupService $backupService, DeletionValidationService $validationService, CumulManagementService $cumulService)
    {
        $this->backupService = $backupService;
        $this->validationService = $validationService;
        $this->cumulService = $cumulService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        // Récupérer le terme de recherche
        $searchTerm = $request->input('search');

        // Construire la requête de base
        $distributeurs = Distributeur::with(['parent', 'children'])
            ->orderBy('created_at', 'desc');

        // Filtres de recherche
        if ($request->filled('search')) {
            $distributeurs->where(function($query) use ($searchTerm) {
                $query->where('nom_distributeur', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('pnom_distributeur', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('distributeur_id', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('tel_distributeur', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('adress_distributeur', 'LIKE', "%{$searchTerm}%");
            });
        }

        // Filtre par grade
        if ($request->filled('grade_filter')) {
            $distributeurs->where('etoiles_id', $request->input('grade_filter'));
        }

        // Filtre par statut
        if ($request->filled('status_filter')) {
            $distributeurs->where('statut_validation_periode', $request->boolean('status_filter'));
        }

        // Paginer les résultats
        $distributeurs = $distributeurs->paginate(20)->withQueryString();

        // Statistiques pour l'interface
        $stats = $this->getDistributeursStats();

        return view('admin.distributeurs.index', compact('distributeurs', 'stats', 'searchTerm'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        // Récupérer les distributeurs potentiels comme parents
        $potentialParents = Distributeur::orderBy('nom_distributeur')
                                      ->select('id', 'distributeur_id', 'nom_distributeur', 'pnom_distributeur')
                                      ->get();

        return view('admin.distributeurs.create', compact('potentialParents'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Validation des données
        $validatedData = $request->validate([
            'distributeur_id' => 'required|unique:distributeurs,distributeur_id',
            'nom_distributeur' => 'required|string|max:255',
            'pnom_distributeur' => 'required|string|max:255',
            'tel_distributeur' => 'nullable|string|max:20',
            'adress_distributeur' => 'nullable|string|max:255',
            'id_parent' => 'nullable|exists:distributeurs,distributeur_id',
            'etoiles_id' => 'nullable|integer|min:1|max:10',
            'cumul_individuel' => 'nullable|numeric|min:0',
            'cumul_collectif' => 'nullable|numeric|min:0',
        ]);

        // Validation supplémentaire : cumul_collectif >= cumul_individuel
        if ($request->filled('cumul_individuel') && $request->filled('cumul_collectif')) {
            if ($request->cumul_collectif < $request->cumul_individuel) {
                return back()->withErrors([
                    'cumul_collectif' => 'Le cumul collectif doit être supérieur ou égal au cumul individuel'
                ])->withInput();
            }
        }

        DB::beginTransaction();
        try {
            // Récupérer l'ID du parent si fourni
            $parentId = null;
            if ($request->filled('id_parent')) {
                $parent = Distributeur::where('distributeur_id', $request->id_parent)->first();
                $parentId = $parent ? $parent->id : null;
            }

            // Créer le distributeur
            $distributeur = new Distributeur();
            $distributeur->distributeur_id = $request->distributeur_id;
            $distributeur->nom_distributeur = $request->nom_distributeur;
            $distributeur->pnom_distributeur = $request->pnom_distributeur;
            $distributeur->tel_distributeur = $request->tel_distributeur;
            $distributeur->adress_distributeur = $request->adress_distributeur;
            $distributeur->id_distrib_parent = $parentId;
            $distributeur->etoiles_id = $request->etoiles_id ?? 1;
            $distributeur->rang = 0; // Rang initial
            $distributeur->save();

            // Initialisation SYSTÉMATIQUE dans level_currents
            $currentPeriod = Carbon::now()->format('Y-m');

            // Vérifier si un enregistrement existe déjà pour cette période
            $levelCurrent = LevelCurrent::where('distributeur_id', $distributeur->id)
                                    ->where('period', $currentPeriod)
                                    ->first();

            if (!$levelCurrent) {
                $levelCurrent = new LevelCurrent();
                $levelCurrent->distributeur_id = $distributeur->id;
                $levelCurrent->period = $currentPeriod;
                $levelCurrent->rang = 0;
                $levelCurrent->etoiles = $request->etoiles_id ?? 1;
                $levelCurrent->cumul_individuel = $request->cumul_individuel ?? 0;
                $levelCurrent->new_cumul = 0; // Toujours 0 pour la période courante au début
                $levelCurrent->cumul_total = 0; // Toujours 0 pour la période courante au début
                $levelCurrent->cumul_collectif = $request->cumul_collectif ?? 0;
                $levelCurrent->id_distrib_parent = $parentId;
                $levelCurrent->save();

                Log::info("Level_current initialisé pour le distributeur", [
                    'distributeur_id' => $distributeur->id,
                    'matricule' => $distributeur->distributeur_id,
                    'period' => $currentPeriod,
                    'cumul_individuel' => $levelCurrent->cumul_individuel,
                    'cumul_collectif' => $levelCurrent->cumul_collectif
                ]);
            }

            DB::commit();

            // Message de succès avec détails
            $message = "Distributeur créé avec succès.";
            if ($request->filled('cumul_individuel') || $request->filled('cumul_collectif')) {
                $message .= " Les cumuls historiques ont été initialisés.";
            }

            return redirect()
                ->route('admin.distributeurs.show', $distributeur)
                ->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erreur création distributeur", [
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);

            return back()
                ->withErrors(['error' => 'Une erreur est survenue lors de la création du distributeur.'])
                ->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Distributeur $distributeur)
    {
        // Charger les relations nécessaires avec comptages
        $distributeur->load([
            'parent',
            'children' => function($query) {
                $query->orderBy('nom_distributeur');
            },
            'achats' => function($query) {
                $query->latest()->limit(10);
            },
            'levelCurrents' => function($query) {
                $query->latest()->limit(5);
            }
        ]);

        // Si requête AJAX, retourner JSON
        if (request()->ajax()) {
            return response()->json([
                'distributeur' => $distributeur,
                'statistics' => $this->getDistributeurStatistics($distributeur)
            ]);
        }

        // Calculer des statistiques détaillées
        $statistics = $this->getDistributeurStatistics($distributeur);

        // Récupérer l'historique des modifications récentes
        $recentChanges = $this->getRecentChanges($distributeur);

        return view('admin.distributeurs.show', compact('distributeur', 'statistics', 'recentChanges'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Distributeur $distributeur): View
    {
        // Récupérer les parents potentiels (exclure le distributeur et ses descendants)
        $excludedIds = $this->getDescendantIds($distributeur->id);
        $excludedIds[] = $distributeur->id;

        $potentialParents = Distributeur::whereNotIn('id', $excludedIds)
                                      ->orderBy('nom_distributeur')
                                      ->select('id', 'distributeur_id', 'nom_distributeur', 'pnom_distributeur')
                                      ->get();

        // Historique des modifications pour cet utilisateur
        $modificationHistory = $this->getModificationHistory($distributeur);

        return view('admin.distributeurs.edit', compact('distributeur', 'potentialParents', 'modificationHistory'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Distributeur $distributeur)
    {
        // Validation des données
        $validatedData = $request->validate([
            'nom_distributeur' => 'required|string|max:255',
            'pnom_distributeur' => 'required|string|max:255',
            'tel_distributeur' => 'nullable|string|max:20',
            'adress_distributeur' => 'nullable|string|max:255',
            'id_distrib_parent' => 'nullable|exists:distributeurs,id',
            'etoiles_id' => 'required|integer|min:1|max:10',
            'rang' => 'nullable|integer|min:0',
            'statut_validation_periode' => 'boolean',
            'cumul_individuel' => 'nullable|numeric|min:0',
            'cumul_collectif' => 'nullable|numeric|min:0',
        ]);

        // Validation supplémentaire : cumul_collectif >= cumul_individuel
        if ($request->filled('cumul_individuel') && $request->filled('cumul_collectif')) {
            if ($request->cumul_collectif < $request->cumul_individuel) {
                return back()->withErrors([
                    'cumul_collectif' => 'Le cumul collectif doit être supérieur ou égal au cumul individuel'
                ])->withInput();
            }
        }

        DB::beginTransaction();
        try {
            // Stocker les anciennes valeurs pour l'audit
            $oldGrade = $distributeur->etoiles_id;
            $oldParent = $distributeur->id_distrib_parent;

            // Mise à jour du distributeur
            $distributeur->nom_distributeur = $request->nom_distributeur;
            $distributeur->pnom_distributeur = $request->pnom_distributeur;
            $distributeur->tel_distributeur = $request->tel_distributeur;
            $distributeur->adress_distributeur = $request->adress_distributeur;
            $distributeur->etoiles_id = $request->etoiles_id;
            $distributeur->rang = $request->rang ?? 0;
            $distributeur->statut_validation_periode = $request->boolean('statut_validation_periode');

            // Gestion du changement de parent
            if ($request->filled('id_distrib_parent') && $request->id_distrib_parent != $oldParent) {
                // Vérifier que le nouveau parent n'est pas un descendant
                if ($this->isDescendant($distributeur->id, $request->id_distrib_parent)) {
                    return back()->withErrors([
                        'id_distrib_parent' => 'Le nouveau parent ne peut pas être un descendant du distributeur'
                    ])->withInput();
                }
                $distributeur->id_distrib_parent = $request->id_distrib_parent;
            }

            $distributeur->save();

            // Mise à jour des performances si fournies
            if ($request->filled('cumul_individuel') || $request->filled('cumul_collectif')) {
                $currentPeriod = Carbon::now()->format('Y-m');

                $levelCurrent = LevelCurrent::where('distributeur_id', $distributeur->id)
                                        ->where('period', $currentPeriod)
                                        ->first();

                if ($levelCurrent) {
                    // Mise à jour des cumuls existants
                    if ($request->filled('cumul_individuel')) {
                        $levelCurrent->cumul_individuel = $request->cumul_individuel;
                    }
                    if ($request->filled('cumul_collectif')) {
                        $levelCurrent->cumul_collectif = $request->cumul_collectif;
                    }

                    // Si le grade a changé, mettre à jour aussi dans level_currents
                    if ($oldGrade != $request->etoiles_id) {
                        $levelCurrent->etoiles = $request->etoiles_id;
                    }

                    $levelCurrent->save();

                    // Log de l'ajustement
                    Log::info("Ajustement des cumuls pour le distributeur", [
                        'distributeur_id' => $distributeur->id,
                        'matricule' => $distributeur->distributeur_id,
                        'period' => $currentPeriod,
                        'cumul_individuel' => $levelCurrent->cumul_individuel,
                        'cumul_collectif' => $levelCurrent->cumul_collectif,
                        'grade' => $levelCurrent->etoiles
                    ]);
                } else {
                    // Créer un nouvel enregistrement si nécessaire
                    $levelCurrent = new LevelCurrent();
                    $levelCurrent->distributeur_id = $distributeur->id;
                    $levelCurrent->period = $currentPeriod;
                    $levelCurrent->rang = $distributeur->rang;
                    $levelCurrent->etoiles = $distributeur->etoiles_id;
                    $levelCurrent->cumul_individuel = $request->cumul_individuel ?? 0;
                    $levelCurrent->new_cumul = 0;
                    $levelCurrent->cumul_total = 0;
                    $levelCurrent->cumul_collectif = $request->cumul_collectif ?? 0;
                    $levelCurrent->id_distrib_parent = $distributeur->id_distrib_parent;
                    $levelCurrent->save();
                }
            }

            // Créer un enregistrement d'audit si le grade a changé
            if ($oldGrade != $request->etoiles_id) {
                AvancementHistory::create([
                    'distributeur_id' => $distributeur->id,
                    'period' => Carbon::now()->format('Y-m'),
                    'ancien_grade' => $oldGrade,
                    'nouveau_grade' => $request->etoiles_id,
                    'type_calcul' => 'manual',
                    'details' => [
                        'user_id' => Auth::id(),
                        'reason' => 'Modification manuelle via interface admin',
                        'old_cumul_individuel' => $levelCurrent->getOriginal('cumul_individuel') ?? null,
                        'new_cumul_individuel' => $levelCurrent->cumul_individuel ?? null,
                        'old_cumul_collectif' => $levelCurrent->getOriginal('cumul_collectif') ?? null,
                        'new_cumul_collectif' => $levelCurrent->cumul_collectif ?? null,
                    ]
                ]);
            }

            DB::commit();

            // Message de succès détaillé
            $message = "Distributeur mis à jour avec succès.";
            if ($oldGrade != $request->etoiles_id) {
                $message .= " Le grade a été modifié de {$oldGrade} à {$request->etoiles_id}.";
            }
            if ($request->filled('cumul_individuel') || $request->filled('cumul_collectif')) {
                $message .= " Les cumuls ont été ajustés.";
            }

            return redirect()
                ->route('admin.distributeurs.show', $distributeur)
                ->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erreur mise à jour distributeur", [
                'error' => $e->getMessage(),
                'distributeur_id' => $distributeur->id,
                'data' => $request->all()
            ]);

            return back()
                ->withErrors(['error' => 'Une erreur est survenue lors de la mise à jour.'])
                ->withInput();
        }
    }

    /**
     * Vérifie si un distributeur est descendant d'un autre
     */
    private function isDescendant($parentId, $potentialDescendantId)
    {
        if ($parentId == $potentialDescendantId) {
            return true;
        }

        $children = Distributeur::where('id_distrib_parent', $parentId)->pluck('id');

        foreach ($children as $childId) {
            if ($this->isDescendant($childId, $potentialDescendantId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Affiche la confirmation de suppression avec analyse détaillée
     */
    public function confirmDeletion(Distributeur $distributeur): View
    {
        // Validation complète de la suppression
        $validationResult = $this->validationService->validateDistributeurDeletion($distributeur);

        // Suggestions d'actions de nettoyage
        $cleanupActions = $this->validationService->suggestCleanupActions($validationResult);

        return view('admin.distributeurs.confirm-deletion', compact(
            'distributeur',
            'validationResult',
            'cleanupActions'
        ));
    }

    /**
     * Traite la demande de suppression (avec ou sans workflow selon la criticité)
     */
    public function requestDeletion(Request $request, Distributeur $distributeur): RedirectResponse
    {
        $request->validate([
            'reason' => 'required|string|min:10|max:500',
            'force_immediate' => 'nullable|boolean'
        ]);

        try {
            // Validation de la suppression
            $validationResult = $this->validationService->validateDistributeurDeletion($distributeur);

            // Déterminer si une approbation est nécessaire
            $needsApproval = $this->needsApproval($distributeur, $validationResult);
            $forceImmediate = $request->boolean('force_immediate') && $this->userCanForceDelete();

            if ($needsApproval && !$forceImmediate) {
                // Créer une demande d'approbation
                $deletionRequest = DeletionRequest::createForDistributeur(
                    $distributeur,
                    $request->input('reason'),
                    $validationResult
                );

                Log::info("Demande de suppression créée", [
                    'distributeur_id' => $distributeur->id,
                    'deletion_request_id' => $deletionRequest->id,
                    'needs_approval' => true,
                    'user_id' => Auth::id()
                ]);

                return redirect()
                    ->route('admin.distributeurs.index')
                    ->with('warning', 'Demande de suppression soumise pour approbation. Référence: #' . $deletionRequest->id);
            }

            // Suppression immédiate (cas simples ou forcés)
            return $this->executeImmediateDeletion($distributeur, $request->input('reason'), $validationResult);

        } catch (\Exception $e) {
            Log::error("Erreur lors de la demande de suppression", [
                'distributeur_id' => $distributeur->id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return back()->with('error', 'Erreur lors de la demande de suppression: ' . $e->getMessage());
        }
    }

    /**
     * Exécute une suppression immédiate (cas simples)
     */
    private function executeImmediateDeletion(Distributeur $distributeur, string $reason, array $validationResult): RedirectResponse
    {
        // Vérifier que la suppression est possible
        if (!$validationResult['can_delete']) {
            return back()->with('error', 'Suppression impossible. Veuillez résoudre les problèmes bloquants d\'abord.');
        }

        DB::beginTransaction();
        try {
            // 1. Créer la demande de suppression (même pour exécution immédiate)
            $deletionRequest = DeletionRequest::create([
                'entity_type' => DeletionRequest::ENTITY_DISTRIBUTEUR,
                'entity_id' => $distributeur->id,
                'requested_by_id' => Auth::id(),
                'status' => DeletionRequest::STATUS_APPROVED, // Approuvée automatiquement
                'reason' => $reason,
                'validation_data' => $validationResult,
                'approved_by_id' => Auth::id(),
                'approved_at' => now(),
            ]);

            // 2. Créer un backup complet
            $backupResult = $this->backupService->createBackup(
                'distributeur',
                $distributeur->id
            );

            if (!$backupResult['success']) {
                throw new \Exception("Échec de la création du backup: " . $backupResult['error']);
            }

            // 3. Nettoyer les données liées (incluant le transfert des cumuls)
            $this->cleanupRelatedData($distributeur, $validationResult);

            // 4. Log détaillé avant suppression
            Log::info("Suppression immédiate distributeur", [
                'id' => $distributeur->id,
                'matricule' => $distributeur->distributeur_id,
                'nom' => $distributeur->full_name,
                'reason' => $reason,
                'backup_id' => $backupResult['backup_id'],
                'deletion_request_id' => $deletionRequest->id,
                'user_id' => Auth::id()
            ]);

            // 5. Supprimer le distributeur
            $distributeur->delete();

            // 6. Marquer la demande comme complétée
            $deletionRequest->markAsCompleted([
                'backup_id' => $backupResult['backup_id'],
                'executed_by' => Auth::id(),
                'execution_type' => 'immediate'
            ]);

            DB::commit();

            return redirect()
                ->route('admin.deletion-requests.show', $deletionRequest)
                ->with('success', 'Distributeur supprimé avec succès. Backup: #' . $backupResult['backup_id']);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erreur suppression immédiate distributeur", [
                'id' => $distributeur->id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);
            return back()->with('error', 'Erreur lors de la suppression: ' . $e->getMessage());
        }
    }

    /**
     * Exécute une suppression approuvée (appelée par DeletionRequestController)
     */
    public function executeDeletion(DeletionRequest $deletionRequest): RedirectResponse
    {
        if (!$deletionRequest->canBeExecuted()) {
            return back()->with('error', 'Cette demande ne peut pas être exécutée.');
        }

        $distributeur = $deletionRequest->entity();
        if (!$distributeur || !($distributeur instanceof Distributeur)) {
            return back()->with('error', 'Le distributeur à supprimer n\'existe plus.');
        }

        DB::beginTransaction();
        try {
            // Re-valider avant exécution (au cas où la situation aurait changé)
            $validationResult = $this->validationService->validateDistributeurDeletion($distributeur);

            if (!$validationResult['can_delete']) {
                throw new \Exception('La suppression n\'est plus possible. Situation changée depuis l\'approbation.');
            }

            // Créer backup
            $backupResult = $this->backupService->createBackup(
                'distributeur',
                $distributeur->id
            );

            if (!$backupResult['success']) {
                throw new \Exception("Échec backup: " . $backupResult['error']);
            }

            // Nettoyer les données liées
            $this->cleanupRelatedData($distributeur, $validationResult);

            // Supprimer
            $distributeur->delete();

            // Marquer la demande comme exécutée
            $deletionRequest->markAsCompleted([
                'backup_id' => $backupResult['backup_id'],
                'executed_by' => Auth::id()
            ]);

            DB::commit();

            return redirect()
                ->route('admin.deletion-requests.index')
                ->with('success', 'Suppression exécutée avec succès. Backup: #' . $backupResult['backup_id']);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erreur exécution suppression", [
                'deletion_request_id' => $deletionRequest->id,
                'distributeur_id' => $distributeur->id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return back()->with('error', 'Erreur lors de l\'exécution: ' . $e->getMessage());
        }
    }

    /**
     * Méthode destroy originale modifiée pour rediriger vers le nouveau workflow
     */
    public function destroy(Distributeur $distributeur): RedirectResponse
    {
        // Rediriger vers la nouvelle interface de confirmation
        return redirect()->route('admin.distributeurs.confirm-deletion', $distributeur);
    }

    /**
     * Recherche AJAX de distributeurs
     */
    public function search(Request $request): JsonResponse
    {
        $search = $request->get('q', '');

        $query = Distributeur::query();

        // Vérifier si on cherche par ID (format: "id:123")
        if (strpos($search, 'id:') === 0) {
            $id = substr($search, 3);
            $query->where('id', $id);
        } elseif (!empty($search)) {
            // Recherche normale
            $query->where(function($q) use ($search) {
                $q->where('nom_distributeur', 'LIKE', "%{$search}%")
                ->orWhere('pnom_distributeur', 'LIKE', "%{$search}%")
                ->orWhere('distributeur_id', 'LIKE', "%{$search}%")
                ->orWhere('tel_distributeur', 'LIKE', "%{$search}%");
            });
        }

        $distributeurs = $query->orderBy('nom_distributeur')
                            ->limit(20)
                            ->get(['id', 'distributeur_id', 'nom_distributeur', 'pnom_distributeur', 'tel_distributeur']);

        // Formater les résultats pour Select2/Ajax
        $results = $distributeurs->map(function($dist) {
            return [
                'id' => $dist->id,
                'text' => "#{$dist->distributeur_id} - {$dist->pnom_distributeur} {$dist->nom_distributeur}",
                'distributeur_id' => $dist->distributeur_id,
                'nom_distributeur' => $dist->nom_distributeur,
                'pnom_distributeur' => $dist->pnom_distributeur,
                'tel_distributeur' => $dist->tel_distributeur
            ];
        })->toArray();

        // Format attendu par Select2
        return response()->json([
            'results' => $results,
            'pagination' => [
                'more' => false
            ]
        ]);
    }

    // ===== MÉTHODES PRIVÉES UTILITAIRES =====

    /**
     * Détermine si une approbation est nécessaire
     */
    private function needsApproval(Distributeur $distributeur, array $validationResult): bool
    {
        // Approbation nécessaire si :

        // 1. Il y a des blockers
        if (!empty($validationResult['blockers'])) {
            return true;
        }

        // 2. Impact hiérarchique important
        if (isset($validationResult['impact_analysis']['hierarchy'])) {
            $hierarchyImpact = $validationResult['impact_analysis']['hierarchy'];
            if ($hierarchyImpact['total_descendants'] > 10) {
                return true;
            }
        }

        // 3. Distributeur avec beaucoup d'enfants directs
        if ($distributeur->children()->count() > 3) {
            return true;
        }

        // 4. Données financières importantes
        $totalAchats = $distributeur->achats()->sum('montant_total_ligne');
        if ($totalAchats > 100000) { // Seuil configurable
            return true;
        }

        return false;
    }

    //**
    // * Nettoie les données liées avant suppression (MISE À JOUR)
    // */
    private function cleanupRelatedData(Distributeur $distributeur, array $validationResult): void
    {
        $currentPeriod = date('Y-m');

        // 1. Gérer le transfert des cumuls et la réorganisation hiérarchique
        $transferResult = $this->cumulService->handleDistributeurDeletion($distributeur, $currentPeriod);

        if (!$transferResult['success']) {
            throw new \Exception("Erreur lors du transfert des cumuls: " . $transferResult['message']);
        }

        // 2. Log du transfert
        if ($transferResult['transferred_amount'] > 0) {
            Log::info("Cumuls transférés lors de la suppression", [
                'distributeur_supprime' => $distributeur->distributeur_id,
                'montant_transfere' => $transferResult['transferred_amount'],
                'parent_beneficiaire' => $transferResult['affected_parent'],
                'period' => $currentPeriod
            ]);
        }

        // 3. Supprimer les enregistrements level_currents du distributeur
        $distributeur->levelCurrents()->delete();

        // 4. Supprimer les autres données liées (achats, bonus, etc.)
        if (isset($validationResult['related_data']['achats'])) {
            $distributeur->achats()->delete();
        }

        if (isset($validationResult['related_data']['bonuses'])) {
            $distributeur->bonuses()->delete();
        }

        if (isset($validationResult['related_data']['advancement_history'])) {
            $distributeur->avancementHistory()->delete();
        }
    }

    /**
     * Vérifier si un changement de parent créerait une boucle
     */
    private function wouldCreateLoop($newParentId, $distributeurId): bool
    {
        if (!$newParentId || !$distributeurId) {
            return false;
        }

        // Parcourir la hiérarchie vers le haut depuis le nouveau parent
        $currentId = $newParentId;
        $visited = [];

        while ($currentId && !in_array($currentId, $visited)) {
            $visited[] = $currentId;

            // Si on trouve le distributeur qu'on veut assigner, c'est une boucle
            if ($currentId == $distributeurId) {
                return true;
            }

            // Monter d'un niveau
            $parent = Distributeur::find($currentId);
            $currentId = $parent ? $parent->id_distrib_parent : null;
        }

        return false;
    }

    /**
     * Calculer la profondeur dans la hiérarchie
     */
    private function calculateDepth($parentId, $depth = 0): int
    {
        if (!$parentId || $depth > 20) return $depth; // Protection contre les boucles infinies

        $parent = Distributeur::find($parentId);
        if (!$parent || !$parent->id_distrib_parent) {
            return $depth;
        }

        return $this->calculateDepth($parent->id_distrib_parent, $depth + 1);
    }

    /**
     * Obtenir tous les IDs des descendants d'un distributeur
     */
    private function getDescendantIds($distributeurId): array
    {
        $descendants = [];
        $children = Distributeur::where('id_distrib_parent', $distributeurId)->pluck('id')->toArray();

        foreach ($children as $childId) {
            $descendants[] = $childId;
            $descendants = array_merge($descendants, $this->getDescendantIds($childId));
        }

        return $descendants;
    }

    /**
     * Obtenir des statistiques globales sur les distributeurs
     */
    private function getDistributeursStats(): array
    {
        try {
            return [
                'total_distributeurs' => Distributeur::count(),
                'distributeurs_actifs' => Distributeur::where('statut_validation_periode', true)->count(),
                'nouveaux_ce_mois' => Distributeur::whereMonth('created_at', now()->month)->count(),
                'par_grade' => Distributeur::selectRaw('etoiles_id, COUNT(*) as count')
                                          ->groupBy('etoiles_id')
                                          ->orderBy('etoiles_id')
                                          ->pluck('count', 'etoiles_id')
                                          ->toArray()
            ];
        } catch (\Exception $e) {
            Log::error("Erreur calcul statistiques distributeurs", ['error' => $e->getMessage()]);
            return [
                'total_distributeurs' => 0,
                'distributeurs_actifs' => 0,
                'nouveaux_ce_mois' => 0,
                'par_grade' => []
            ];
        }
    }

    /**
     * Obtenir des statistiques détaillées pour un distributeur
     */
    private function getDistributeurStatistics(Distributeur $distributeur): array
    {
        try {
            return [
                'total_children' => $distributeur->children()->count(),
                'total_achats' => $distributeur->achats()->count(),
                'total_points' => $distributeur->achats()->sum('points_unitaire_achat'),
                'montant_total_achats' => $distributeur->achats()->sum('montant_total_ligne'),
                'last_achat' => $distributeur->achats()->latest()->first(),
                'total_bonus' => $distributeur->bonuses()->sum('montant'),
                'profondeur_hierarchie' => $this->calculateDepth($distributeur->id_distrib_parent),
                'descendants_total' => count($this->getDescendantIds($distributeur->id))
            ];
        } catch (\Exception $e) {
            Log::error("Erreur calcul statistiques distributeur", [
                'distributeur_id' => $distributeur->id,
                'error' => $e->getMessage()
            ]);
            return [
                'total_children' => 0,
                'total_achats' => 0,
                'total_points' => 0,
                'montant_total_achats' => 0,
                'last_achat' => null,
                'total_bonus' => 0,
                'profondeur_hierarchie' => 0,
                'descendants_total' => 0
            ];
        }
    }

    /**
     * Obtenir l'historique des modifications récentes
     */
    private function getRecentChanges(Distributeur $distributeur): array
    {
        // Cette méthode peut être étendue pour implémenter un vrai système d'audit
        // Pour l'instant, on retourne un tableau vide
        return [];
    }

    /**
     * Obtenir l'historique des modifications pour un distributeur
     */
    private function getModificationHistory(Distributeur $distributeur): array
    {
        // Cette méthode peut être étendue pour implémenter un vrai système d'audit
        // Pour l'instant, on retourne un tableau vide
        return [];
    }

    /**
     * Enregistrer un audit des modifications
     */
    private function logModificationAudit(Distributeur $distributeur, array $originalData, array $newData): void
    {
        $changes = [];
        foreach ($newData as $key => $value) {
            if (isset($originalData[$key]) && $originalData[$key] != $value) {
                $changes[$key] = [
                    'from' => $originalData[$key],
                    'to' => $value
                ];
            }
        }

        if (!empty($changes)) {
            Log::info("Modifications distributeur auditées", [
                'distributeur_id' => $distributeur->id,
                'matricule' => $distributeur->distributeur_id,
                'changes' => $changes,
                'user_id' => Auth::id()
            ]);
        }
    }

    /**
     * Vérifier si l'utilisateur peut forcer une suppression
     */
    private function userCanForceDelete(): bool
    {
        $user = Auth::user();

        // Si le trait HasPermissions est disponible
        if (method_exists($user, 'hasPermission')) {
            return $user->hasPermission('force_delete');
        }

        // Fallback temporaire - vérifier par rôle ou champ
        if (isset($user->role)) {
            return $user->role === 'super_admin';
        }

        // Ou si vous avez des champs booléens
        if (isset($user->is_super_admin)) {
            return $user->is_super_admin === true;
        }

        // Par défaut, pas de permission
        return false;
    }
}
