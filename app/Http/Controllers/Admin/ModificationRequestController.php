<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ModificationRequest;
use App\Models\Distributeur;
use App\Models\LevelCurrent;
use App\Services\ModificationValidationService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class ModificationRequestController extends Controller
{
    private ModificationValidationService $validationService;

    public function __construct(ModificationValidationService $validationService)
    {
        $this->validationService = $validationService;
    }

    /**
     * Liste toutes les demandes de modification
     */
    public function index(Request $request): View
    {
        $query = ModificationRequest::with(['requestedBy', 'approvedBy'])
                                   ->orderBy('created_at', 'desc');

        // Filtres
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('type')) {
            $query->where('modification_type', $request->input('type'));
        }

        if ($request->filled('risk_level')) {
            $query->where('risk_level', $request->input('risk_level'));
        }

        $modifications = $query->paginate(20)->withQueryString();

        // Statistiques
        $stats = [
            'pending' => ModificationRequest::pending()->count(),
            'high_risk' => ModificationRequest::pending()->highRisk()->count(),
            'expiring_soon' => ModificationRequest::expiringSoon()->count()
        ];

        return view('admin.modification-requests.index', compact('modifications', 'stats'));
    }

    /**
     * Affiche les détails d'une demande
     */
    public function show(ModificationRequest $modificationRequest): View
    {
        $modificationRequest->load(['requestedBy', 'approvedBy']);
        
        // Charger l'entité concernée
        $entity = $modificationRequest->getEntity();

        return view('admin.modification-requests.show', compact('modificationRequest', 'entity'));
    }

    /**
     * Formulaire de changement de parent
     */
    public function createParentChange(Distributeur $distributeur): View
    {
        $potentialParents = Distributeur::where('id', '!=', $distributeur->id)
                                       ->orderBy('nom_distributeur')
                                       ->get();

        return view('admin.modification-requests.create-parent-change', compact('distributeur', 'potentialParents'));
    }

    /**
     * Enregistre une demande de changement de parent
     */
    public function storeParentChange(Request $request, Distributeur $distributeur): RedirectResponse
    {
        $validated = $request->validate([
            'new_parent_id' => 'required|exists:distributeurs,id|different:' . $distributeur->id,
            'reason' => 'required|string|min:10|max:500'
        ]);

        $newParent = Distributeur::find($validated['new_parent_id']);

        // Validation métier
        $validation = $this->validationService->validateParentChange($distributeur, $newParent);

        if (!$validation['is_valid']) {
            return back()
                ->withInput()
                ->with('error', 'Changement impossible : ' . implode(', ', $validation['blockers']));
        }

        DB::beginTransaction();
        try {
            $modificationRequest = ModificationRequest::createParentChangeRequest(
                $distributeur,
                $newParent,
                $validated['reason'],
                Auth::id()
            );

            // Ajouter les données de validation
            $modificationRequest->update([
                'validation_data' => $validation,
                'impact_analysis' => $validation['impact'] ?? []
            ]);

            DB::commit();

            // Si l'utilisateur a les permissions, approuver automatiquement
            if (Auth::user()->hasPermission('auto_approve_modifications')) {
                $modificationRequest->approve(Auth::id(), 'Approbation automatique');
            }

            return redirect()
                ->route('admin.modification-requests.show', $modificationRequest)
                ->with('success', 'Demande de changement de parent créée avec succès.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()
                ->withInput()
                ->with('error', 'Erreur : ' . $e->getMessage());
        }
    }

    /**
     * Formulaire de changement de grade
     */
    public function createGradeChange(Distributeur $distributeur): View
    {
        $grades = range(1, 10); // Grades disponibles

        return view('admin.modification-requests.create-grade-change', compact('distributeur', 'grades'));
    }

    /**
     * Enregistre une demande de changement de grade
     */
    public function storeGradeChange(Request $request, Distributeur $distributeur): RedirectResponse
    {
        $validated = $request->validate([
            'new_grade' => 'required|integer|min:1|max:10',
            'reason' => 'required|string|min:10|max:500',
            'justification' => 'nullable|string|max:1000'
        ]);

        // Validation métier
        $validation = $this->validationService->validateGradeChange($distributeur, $validated['new_grade']);

        if (!$validation['is_valid']) {
            return back()
                ->withInput()
                ->with('error', 'Changement impossible : ' . implode(', ', $validation['blockers']));
        }

        // Si justification requise mais non fournie
        if ($validation['justification_required'] && empty($validated['justification'])) {
            return back()
                ->withInput()
                ->with('error', 'Une justification détaillée est requise pour ce changement de grade.');
        }

        DB::beginTransaction();
        try {
            $justificationData = $validation['justification_required'] 
                ? ['justification' => $validated['justification']] 
                : [];

            $modificationRequest = ModificationRequest::createGradeChangeRequest(
                $distributeur,
                $validated['new_grade'],
                $validated['reason'],
                Auth::id(),
                $justificationData
            );

            $modificationRequest->update([
                'validation_data' => $validation,
                'impact_analysis' => $validation['impact'] ?? []
            ]);

            DB::commit();

            // Approbation automatique si permissions
            if (Auth::user()->hasPermission('auto_approve_grade_changes') && !$validation['justification_required']) {
                $modificationRequest->approve(Auth::id(), 'Approbation automatique');
            }

            return redirect()
                ->route('admin.modification-requests.show', $modificationRequest)
                ->with('success', 'Demande de changement de grade créée avec succès.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()
                ->withInput()
                ->with('error', 'Erreur : ' . $e->getMessage());
        }
    }

    /**
     * Approuve une demande
     */
    public function approve(Request $request, ModificationRequest $modificationRequest): RedirectResponse
    {
        if (!Auth::user()->hasPermission('approve_modifications')) {
            return back()->with('error', 'Permissions insuffisantes.');
        }

        if ($modificationRequest->requiresHighLevelApproval() && !Auth::user()->hasPermission('approve_critical_modifications')) {
            return back()->with('error', 'Cette modification critique nécessite une approbation de niveau supérieur.');
        }

        if (!$modificationRequest->canBeApproved()) {
            return back()->with('error', 'Cette demande ne peut pas être approuvée.');
        }

        $validated = $request->validate([
            'note' => 'nullable|string|max:500',
            'execute_immediately' => 'boolean'
        ]);

        DB::beginTransaction();
        try {
            $modificationRequest->approve(Auth::id(), $validated['note'] ?? null);

            // Exécuter immédiatement si demandé
            if ($request->boolean('execute_immediately')) {
                $result = $this->validationService->executeModification($modificationRequest);
                
                if (!$result['success']) {
                    throw new \Exception($result['error'] ?? 'Erreur lors de l\'exécution');
                }
            }

            DB::commit();

            return redirect()
                ->route('admin.modification-requests.show', $modificationRequest)
                ->with('success', 'Demande approuvée' . ($request->boolean('execute_immediately') ? ' et exécutée' : '') . '.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Erreur : ' . $e->getMessage());
        }
    }

    /**
     * Rejette une demande
     */
    public function reject(Request $request, ModificationRequest $modificationRequest): RedirectResponse
    {
        if (!Auth::user()->hasPermission('approve_modifications')) {
            return back()->with('error', 'Permissions insuffisantes.');
        }

        if (!$modificationRequest->isPending()) {
            return back()->with('error', 'Cette demande ne peut pas être rejetée.');
        }

        $validated = $request->validate([
            'rejection_reason' => 'required|string|min:10|max:500'
        ]);

        try {
            $modificationRequest->reject(Auth::id(), $validated['rejection_reason']);

            return redirect()
                ->route('admin.modification-requests.index')
                ->with('success', 'Demande rejetée.');

        } catch (\Exception $e) {
            return back()->with('error', 'Erreur : ' . $e->getMessage());
        }
    }

    /**
     * Exécute une demande approuvée
     */
    public function execute(ModificationRequest $modificationRequest): RedirectResponse
    {
        if (!Auth::user()->hasPermission('execute_modifications')) {
            return back()->with('error', 'Permissions insuffisantes pour exécuter les modifications.');
        }

        if (!$modificationRequest->canBeExecuted()) {
            return back()->with('error', 'Cette demande ne peut pas être exécutée.');
        }

        DB::beginTransaction();
        try {
            $result = $this->validationService->executeModification($modificationRequest);
            
            if (!$result['success']) {
                throw new \Exception($result['error'] ?? 'Erreur lors de l\'exécution');
            }

            DB::commit();

            return redirect()
                ->route('admin.modification-requests.show', $modificationRequest)
                ->with('success', 'Modification exécutée avec succès.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Erreur lors de l\'exécution : ' . $e->getMessage());
        }
    }

    /**
     * Annule une demande en attente
     */
    public function cancel(ModificationRequest $modificationRequest): RedirectResponse
    {
        if (!$modificationRequest->isPending()) {
            return back()->with('error', 'Seules les demandes en attente peuvent être annulées.');
        }

        // Seul le demandeur ou un admin peut annuler
        if ($modificationRequest->requested_by_id !== Auth::id() && !Auth::user()->hasPermission('manage_all_modifications')) {
            return back()->with('error', 'Vous ne pouvez pas annuler cette demande.');
        }

        try {
            $modificationRequest->update([
                'status' => ModificationRequest::STATUS_CANCELLED
            ]);

            return redirect()
                ->route('admin.modification-requests.index')
                ->with('success', 'Demande annulée.');

        } catch (\Exception $e) {
            return back()->with('error', 'Erreur : ' . $e->getMessage());
        }
    }

    /**
     * API pour valider un changement en temps réel
     */
    public function validateChange(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|string',
            'entity_id' => 'required|integer',
            'new_value' => 'required'
        ]);

        try {
            $result = match($validated['type']) {
                'parent_change' => $this->validateParentChangeAjax($validated),
                'grade_change' => $this->validateGradeChangeAjax($validated),
                'cumul_adjustment' => $this->validateCumulAdjustmentAjax($validated),
                default => ['is_valid' => false, 'error' => 'Type non supporté']
            };

            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json([
                'is_valid' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Validation AJAX pour changement de parent
     */
    private function validateParentChangeAjax(array $data): array
    {
        $distributeur = Distributeur::find($data['entity_id']);
        $newParent = Distributeur::find($data['new_value']);

        if (!$distributeur || !$newParent) {
            return ['is_valid' => false, 'error' => 'Entité non trouvée'];
        }

        return $this->validationService->validateParentChange($distributeur, $newParent);
    }

    /**
     * Validation AJAX pour changement de grade
     */
    private function validateGradeChangeAjax(array $data): array
    {
        $distributeur = Distributeur::find($data['entity_id']);
        
        if (!$distributeur) {
            return ['is_valid' => false, 'error' => 'Distributeur non trouvé'];
        }

        return $this->validationService->validateGradeChange($distributeur, (int)$data['new_value']);
    }

    /**
     * Validation AJAX pour ajustement de cumuls
     */
    private function validateCumulAdjustmentAjax(array $data): array
    {
        $levelCurrent = LevelCurrent::find($data['entity_id']);
        
        if (!$levelCurrent) {
            return ['is_valid' => false, 'error' => 'LevelCurrent non trouvé'];
        }

        return $this->validationService->validateCumulAdjustment($levelCurrent, $data['new_value']);
    }
}

// Routes à ajouter dans routes/web.php dans la section admin
/*
Route::prefix('modification-requests')->name('modification-requests.')->group(function () {
    Route::get('/', [ModificationRequestController::class, 'index'])->name('index');
    Route::get('/{modificationRequest}', [ModificationRequestController::class, 'show'])->name('show');
    
    // Création de demandes spécifiques
    Route::get('/create/parent-change/{distributeur}', [ModificationRequestController::class, 'createParentChange'])->name('create.parent-change');
    Route::post('/store/parent-change/{distributeur}', [ModificationRequestController::class, 'storeParentChange'])->name('store.parent-change');
    
    Route::get('/create/grade-change/{distributeur}', [ModificationRequestController::class, 'createGradeChange'])->name('create.grade-change');
    Route::post('/store/grade-change/{distributeur}', [ModificationRequestController::class, 'storeGradeChange'])->name('store.grade-change');
    
    // Actions sur les demandes
    Route::post('/{modificationRequest}/approve', [ModificationRequestController::class, 'approve'])->name('approve');
    Route::post('/{modificationRequest}/reject', [ModificationRequestController::class, 'reject'])->name('reject');
    Route::post('/{modificationRequest}/execute', [ModificationRequestController::class, 'execute'])->name('execute');
    Route::delete('/{modificationRequest}/cancel', [ModificationRequestController::class, 'cancel'])->name('cancel');
    
    // Validation AJAX
    Route::post('/validate', [ModificationRequestController::class, 'validateChange'])->name('validate');
});
*/