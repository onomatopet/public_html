<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Achat;
use App\Models\AchatReturnRequest;
use App\Services\AchatReturnValidationService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class AchatReturnController extends Controller
{
    private AchatReturnValidationService $validationService;

    public function __construct(AchatReturnValidationService $validationService)
    {
        $this->validationService = $validationService;
    }

    /**
     * Liste toutes les demandes de retour/annulation
     */
    public function index(Request $request): View
    {
        $query = AchatReturnRequest::with(['achat.distributeur', 'achat.product', 'requestedBy', 'approvedBy'])
                                  ->orderBy('created_at', 'desc');

        // Filtres
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('period')) {
            $query->whereHas('achat', function($q) use ($request) {
                $q->where('period', $request->input('period'));
            });
        }

        $requests = $query->paginate(20)->withQueryString();

        // Statistiques
        $stats = [
            'pending' => AchatReturnRequest::pending()->count(),
            'approved' => AchatReturnRequest::approved()->count(),
            'total_month' => AchatReturnRequest::whereMonth('created_at', now()->month)->count()
        ];

        return view('admin.achat-returns.index', compact('requests', 'stats'));
    }

    /**
     * Affiche le formulaire de création d'une demande
     */
    public function create(Achat $achat): View|RedirectResponse
    {
        // Vérifier que l'achat peut être retourné
        if ($achat->status !== 'active') {
            return redirect()
                ->route('admin.achats.show', $achat)
                ->with('error', 'Cet achat ne peut pas être retourné/annulé.');
        }

        // Validation préliminaire
        $validationFull = $this->validationService->validateReturnRequest($achat, AchatReturnRequest::TYPE_RETURN);
        $validationPartial = $this->validationService->validateReturnRequest($achat, AchatReturnRequest::TYPE_PARTIAL_RETURN, 1);

        return view('admin.achat-returns.create', compact('achat', 'validationFull', 'validationPartial'));
    }

    /**
     * Enregistre une nouvelle demande
     */
    public function store(Request $request, Achat $achat): RedirectResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:cancellation,return,partial_return',
            'reason' => 'required|string|min:10|max:500',
            'quantity_to_return' => 'required_if:type,partial_return|nullable|integer|min:1',
            'notes' => 'nullable|string|max:1000'
        ]);

        // Validation métier
        $validation = $this->validationService->validateReturnRequest(
            $achat,
            $validated['type'],
            $validated['quantity_to_return'] ?? null
        );

        if (!$validation['can_proceed']) {
            return back()
                ->withInput()
                ->with('error', 'La demande ne peut pas être créée : ' . implode(', ', $validation['blockers']));
        }

        DB::beginTransaction();
        try {
            // Calculer le montant à rembourser
            $amountToRefund = 0;
            if ($validated['type'] === AchatReturnRequest::TYPE_PARTIAL_RETURN) {
                $amountToRefund = $achat->prix_unitaire_achat * $validated['quantity_to_return'];
            } else {
                $amountToRefund = $achat->montant_total_ligne;
            }

            // Créer la demande
            $returnRequest = AchatReturnRequest::create([
                'achat_id' => $achat->id,
                'requested_by_id' => Auth::id(),
                'type' => $validated['type'],
                'status' => AchatReturnRequest::STATUS_PENDING,
                'reason' => $validated['reason'],
                'notes' => $validated['notes'] ?? null,
                'quantity_to_return' => $validated['quantity_to_return'] ?? null,
                'amount_to_refund' => $amountToRefund,
                'validation_data' => $validation,
                'impact_analysis' => $validation['impact'] ?? []
            ]);

            // Si l'utilisateur a les permissions, approuver automatiquement
            if (Auth::user()->hasPermission('auto_approve_returns')) {
                $returnRequest->approve(Auth::id(), 'Approbation automatique');
            }

            DB::commit();

            Log::info("Demande de retour/annulation créée", [
                'request_id' => $returnRequest->id,
                'achat_id' => $achat->id,
                'type' => $validated['type'],
                'user_id' => Auth::id()
            ]);

            return redirect()
                ->route('admin.achat-returns.show', $returnRequest)
                ->with('success', 'Demande de retour/annulation créée avec succès.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erreur création demande retour", [
                'achat_id' => $achat->id,
                'error' => $e->getMessage()
            ]);

            return back()
                ->withInput()
                ->with('error', 'Erreur lors de la création : ' . $e->getMessage());
        }
    }

    /**
     * Affiche les détails d'une demande
     */
    public function show(AchatReturnRequest $returnRequest): View
    {
        $returnRequest->load(['achat.distributeur', 'achat.product', 'requestedBy', 'approvedBy']);

        return view('admin.achat-returns.show', compact('returnRequest'));
    }

    /**
     * Approuve une demande
     */
    public function approve(Request $request, AchatReturnRequest $returnRequest): RedirectResponse
    {
        if (!Auth::user()->hasPermission('approve_returns')) {
            return back()->with('error', 'Vous n\'avez pas les permissions nécessaires.');
        }

        if (!$returnRequest->canBeApproved()) {
            return back()->with('error', 'Cette demande ne peut pas être approuvée.');
        }

        $validated = $request->validate([
            'note' => 'nullable|string|max:500',
            'execute_immediately' => 'boolean'
        ]);

        DB::beginTransaction();
        try {
            // Approuver la demande
            $returnRequest->approve(Auth::id(), $validated['note'] ?? null);

            // Exécuter immédiatement si demandé
            if ($request->boolean('execute_immediately')) {
                $result = $this->validationService->executeReturn($returnRequest);
                
                if (!$result['success']) {
                    throw new \Exception($result['error']);
                }
            }

            DB::commit();

            return redirect()
                ->route('admin.achat-returns.show', $returnRequest)
                ->with('success', 'Demande approuvée' . ($request->boolean('execute_immediately') ? ' et exécutée' : '') . ' avec succès.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Erreur : ' . $e->getMessage());
        }
    }

    /**
     * Rejette une demande
     */
    public function reject(Request $request, AchatReturnRequest $returnRequest): RedirectResponse
    {
        if (!Auth::user()->hasPermission('approve_returns')) {
            return back()->with('error', 'Vous n\'avez pas les permissions nécessaires.');
        }

        if (!$returnRequest->isPending()) {
            return back()->with('error', 'Cette demande ne peut pas être rejetée.');
        }

        $validated = $request->validate([
            'rejection_reason' => 'required|string|min:10|max:500'
        ]);

        try {
            $returnRequest->reject(Auth::id(), $validated['rejection_reason']);

            return redirect()
                ->route('admin.achat-returns.index')
                ->with('success', 'Demande rejetée.');

        } catch (\Exception $e) {
            return back()->with('error', 'Erreur : ' . $e->getMessage());
        }
    }

    /**
     * Exécute une demande approuvée
     */
    public function execute(AchatReturnRequest $returnRequest): RedirectResponse
    {
        if (!Auth::user()->hasPermission('execute_returns')) {
            return back()->with('error', 'Vous n\'avez pas les permissions nécessaires.');
        }

        if (!$returnRequest->canBeExecuted()) {
            return back()->with('error', 'Cette demande ne peut pas être exécutée.');
        }

        DB::beginTransaction();
        try {
            $result = $this->validationService->executeReturn($returnRequest);
            
            if (!$result['success']) {
                throw new \Exception($result['error']);
            }

            DB::commit();

            return redirect()
                ->route('admin.achat-returns.show', $returnRequest)
                ->with('success', 'Retour/Annulation exécuté avec succès.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Erreur lors de l\'exécution : ' . $e->getMessage());
        }
    }

    /**
     * Annule une demande en attente
     */
    public function cancel(AchatReturnRequest $returnRequest): RedirectResponse
    {
        if (!$returnRequest->isPending()) {
            return back()->with('error', 'Seules les demandes en attente peuvent être annulées.');
        }

        // Seul le demandeur ou un admin peut annuler
        if ($returnRequest->requested_by_id !== Auth::id() && !Auth::user()->hasPermission('manage_all_returns')) {
            return back()->with('error', 'Vous ne pouvez pas annuler cette demande.');
        }

        try {
            $returnRequest->update([
                'status' => AchatReturnRequest::STATUS_REJECTED,
                'rejection_reason' => 'Annulée par ' . Auth::user()->name
            ]);

            return redirect()
                ->route('admin.achat-returns.index')
                ->with('success', 'Demande annulée.');

        } catch (\Exception $e) {
            return back()->with('error', 'Erreur : ' . $e->getMessage());
        }
    }

    /**
     * Rapport des retours par période
     */
    public function report(Request $request): View
    {
        $period = $request->input('period', now()->format('Y-m'));

        $stats = [
            'total_returns' => AchatReturnRequest::whereHas('achat', fn($q) => $q->where('period', $period))
                                                ->where('status', AchatReturnRequest::STATUS_COMPLETED)
                                                ->count(),
            
            'total_amount_refunded' => AchatReturnRequest::whereHas('achat', fn($q) => $q->where('period', $period))
                                                        ->where('status', AchatReturnRequest::STATUS_COMPLETED)
                                                        ->sum('amount_to_refund'),
            
            'by_type' => AchatReturnRequest::whereHas('achat', fn($q) => $q->where('period', $period))
                                          ->where('status', AchatReturnRequest::STATUS_COMPLETED)
                                          ->selectRaw('type, count(*) as count, sum(amount_to_refund) as total')
                                          ->groupBy('type')
                                          ->get(),
            
            'by_reason' => AchatReturnRequest::whereHas('achat', fn($q) => $q->where('period', $period))
                                            ->where('status', AchatReturnRequest::STATUS_COMPLETED)
                                            ->selectRaw('reason, count(*) as count')
                                            ->groupBy('reason')
                                            ->orderByDesc('count')
                                            ->limit(10)
                                            ->get()
        ];

        return view('admin.achat-returns.report', compact('stats', 'period'));
    }
}