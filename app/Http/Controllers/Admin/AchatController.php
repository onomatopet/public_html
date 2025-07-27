<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Achat;
use App\Models\Distributeur;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Models\DeletionRequest;
use Illuminate\Support\Facades\Auth;

class AchatController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        // 1. Périodes pour le filtre
        $availablePeriods = Achat::select('period')
                                ->distinct()
                                ->orderBy('period', 'desc')
                                ->pluck('period');

        // 2. Récupérer les filtres depuis la requête
        $selectedPeriod = $request->query('period_filter');
        $searchTerm = $request->query('search');

        // 3. Construire la requête de base avec Eager Loading
        $achatsQuery = Achat::with(['distributeur', 'product.pointValeur'])
                           ->orderBy('created_at', 'desc');

        // 4. Appliquer le filtre par période
        if ($selectedPeriod && $availablePeriods->contains($selectedPeriod)) {
            $achatsQuery->where('achats.period', $selectedPeriod);
            Log::info("Filtrage achats par période: {$selectedPeriod}");
        }

        // 5. Appliquer le filtre de recherche par mot-clé
        if ($searchTerm) {
            Log::info("Recherche d'achats avec le terme: {$searchTerm}");
            $achatsQuery->where(function ($query) use ($searchTerm) {
                // Recherche sur les champs directs
                $query->where('achats.id', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('achats.period', 'LIKE', "%{$searchTerm}%");

                // Recherche sur les tables liées
                $query->orWhereHas('distributeur', function ($subQuery) use ($searchTerm) {
                    $subQuery->where('nom_distributeur', 'LIKE', "%{$searchTerm}%")
                             ->orWhere('pnom_distributeur', 'LIKE', "%{$searchTerm}%")
                             ->orWhere('distributeur_id', 'LIKE', "%{$searchTerm}%");
                });

                $query->orWhereHas('product', function ($subQuery) use ($searchTerm) {
                    $subQuery->where('nom_produit', 'LIKE', "%{$searchTerm}%")
                             ->orWhere('code_product', 'LIKE', "%{$searchTerm}%");
                });
            });
        }

        // 6. Paginer avec statistiques
        $achats = $achatsQuery->paginate(20)->withQueryString();

        // 7. Calculer les statistiques si période sélectionnée
        $statistics = null;
        if ($selectedPeriod) {
            $statistics = [
                'total_achats' => Achat::where('period', $selectedPeriod)->count(),
                'total_montant' => Achat::where('period', $selectedPeriod)->sum('montant_total_ligne'),
                'total_points' => Achat::where('period', $selectedPeriod)->sum(DB::raw('points_unitaire_achat * qt')),
            ];
        }

        return view('admin.achats.index', compact(
            'achats',
            'availablePeriods',
            'selectedPeriod',
            'searchTerm',
            'statistics'
        ));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        // Générer les périodes (12 derniers mois)
        $periods = $this->generatePeriods();

        // Récupérer tous les produits avec leur valeur en points
        $products = Product::with('pointValeur')
                           ->orderBy('nom_produit')
                           ->get()
                           ->map(function ($product) {
                               return [
                                   'id' => $product->id,
                                   'name' => "{$product->nom_produit} ({$product->code_product})",
                                   'price' => $product->prix_product,
                                   'points' => optional($product->pointValeur)->numbers ?? 0
                               ];
                           });

        return view('admin.achats.create', compact('products', 'periods'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        // Validation
        $validatedData = $request->validate([
            'period' => [
                'required',
                'string',
                'regex:/^\d{4}-\d{2}$/',
                function ($attribute, $value, $fail) {
                    if ($value > date('Y-m')) {
                        $fail('La période ne peut pas être dans le futur.');
                    }
                },
            ],
            'distributeur_id' => 'required|integer|exists:distributeurs,id',
            'products_id' => 'required|integer|exists:products,id',
            'qt' => 'required|integer|min:1',
            'online' => 'boolean',
            'purchase_date' => 'required|date|before_or_equal:today', // NOUVEAU CHAMP
        ]);

        DB::beginTransaction();
        try {
            // CORRECTION : Vérification de l'existence du distributeur
            $distributeur = Distributeur::find($validatedData['distributeur_id']);
            if (!$distributeur) {
                throw new \Exception("Le distributeur sélectionné n'existe pas.");
            }

            // CORRECTION : Vérification de l'existence du produit
            $product = Product::with('pointValeur')->find($validatedData['products_id']);
            if (!$product) {
                throw new \Exception("Le produit sélectionné n'existe pas.");
            }

            // Vérifier la valeur en points
            if (!$product->pointValeur) {
                throw new \Exception("Le produit n'a pas de valeur en points définie.");
            }

            // Calculer les valeurs dérivées
            $prix_unitaire = $product->prix_product;
            $points_unitaire = $product->pointValeur->numbers;
            $montant_total = $prix_unitaire * $validatedData['qt'];

            // Ajouter les valeurs calculées
            $validatedData['prix_unitaire_achat'] = $prix_unitaire;
            $validatedData['points_unitaire_achat'] = $points_unitaire;
            $validatedData['montant_total_ligne'] = $montant_total;
            $validatedData['online'] = $validatedData['online'] ?? false;

            // Créer l'achat
            $achat = Achat::create($validatedData);

            DB::commit();
            Log::info("Nouvel achat créé", [
                'id' => $achat->id,
                'distributeur' => $distributeur->distributeur_id,
                'montant' => $montant_total
            ]);

            return redirect()
                ->route('admin.achats.show', $achat)
                ->with('success', "Achat enregistré avec succès. Montant: " . number_format($montant_total, 0, ',', ' ') . " XAF");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erreur création achat", [
                'error' => $e->getMessage(),
                'data' => $validatedData
            ]);
            return back()
                ->withInput()
                ->with('error', 'Une erreur est survenue lors de l\'enregistrement de l\'achat: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Achat $achat): View
    {
        $achat->load(['distributeur', 'product.category', 'product.pointValeur']);

        // Calculer les totaux du distributeur pour cette période
        $distributeurStats = null;
        if ($achat->distributeur) {
            $distributeurStats = Achat::where('distributeur_id', $achat->distributeur_id)
                                     ->where('period', $achat->period)
                                     ->selectRaw('COUNT(*) as total_achats, SUM(montant_total_ligne) as total_montant, SUM(points_unitaire_achat * qt) as total_points')
                                     ->first();
        }

        return view('admin.achats.show', compact('achat', 'distributeurStats'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Achat $achat): View
    {
        $periods = $this->generatePeriods();

        // Récupérer les produits avec leurs infos
        $products = Product::with('pointValeur')
                           ->orderBy('nom_produit')
                           ->get()
                           ->map(function ($product) {
                               return [
                                   'id' => $product->id,
                                   'name' => "{$product->nom_produit} ({$product->code_product})",
                                   'price' => $product->prix_product,
                                   'points' => optional($product->pointValeur)->numbers ?? 0
                               ];
                           });

        // Précharger le distributeur actuel
        $achat->load('distributeur');

        return view('admin.achats.edit', compact('achat', 'products', 'periods'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Achat $achat): RedirectResponse
    {
        // Validation
        $validatedData = $request->validate([
            'period' => [
                'required',
                'string',
                'regex:/^\d{4}-\d{2}$/',
                function ($attribute, $value, $fail) {
                    if ($value > date('Y-m')) {
                        $fail('La période ne peut pas être dans le futur.');
                    }
                },
            ],
            'distributeur_id' => 'required|integer|exists:distributeurs,id',
            'products_id' => 'required|integer|exists:products,id',
            'qt' => 'required|integer|min:1',
            'online' => 'boolean',
            'purchase_date' => 'required|date|before_or_equal:today', // NOUVEAU CHAMP
        ]);

        DB::beginTransaction();
        try {
            // CORRECTION : Vérification de l'existence du distributeur
            $distributeur = Distributeur::find($validatedData['distributeur_id']);
            if (!$distributeur) {
                throw new \Exception("Le distributeur sélectionné n'existe pas.");
            }

            // CORRECTION : Vérification de l'existence du produit
            $product = Product::with('pointValeur')->find($validatedData['products_id']);
            if (!$product) {
                throw new \Exception("Le produit sélectionné n'existe pas.");
            }

            // Vérifier la valeur en points
            if (!$product->pointValeur) {
                throw new \Exception("Le produit n'a pas de valeur en points définie.");
            }

            // Calculer les nouvelles valeurs
            $prix_unitaire = $product->prix_product;
            $points_unitaire = $product->pointValeur->numbers;
            $montant_total = $prix_unitaire * $validatedData['qt'];

            // Ajouter les valeurs calculées
            $validatedData['prix_unitaire_achat'] = $prix_unitaire;
            $validatedData['points_unitaire_achat'] = $points_unitaire;
            $validatedData['montant_total_ligne'] = $montant_total;
            $validatedData['online'] = $validatedData['online'] ?? false;

            // Log changements importants
            if ($achat->products_id != $validatedData['products_id'] ||
                $achat->qt != $validatedData['qt']) {
                Log::info("Modification achat", [
                    'id' => $achat->id,
                    'ancien_produit' => $achat->products_id,
                    'nouveau_produit' => $validatedData['products_id'],
                    'ancienne_qt' => $achat->qt,
                    'nouvelle_qt' => $validatedData['qt'],
                ]);
            }

            // Mettre à jour
            $achat->update($validatedData);

            DB::commit();
            return redirect()
                ->route('admin.achats.show', $achat)
                ->with('success', 'Achat mis à jour avec succès.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erreur mise à jour achat", [
                'id' => $achat->id,
                'error' => $e->getMessage()
            ]);
            return back()
                ->withInput()
                ->with('error', 'Erreur lors de la mise à jour: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Achat $achat): RedirectResponse
    {
        DB::beginTransaction();
        try {
            Log::info("Suppression achat", [
                'id' => $achat->id,
                'distributeur' => optional($achat->distributeur)->distributeur_id,
                'montant' => $achat->montant_total_ligne
            ]);

            $achat->delete();

            DB::commit();
            return redirect()
                ->route('admin.achats.index')
                ->with('success', 'Achat supprimé avec succès.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erreur suppression achat", [
                'id' => $achat->id,
                'error' => $e->getMessage()
            ]);
            return back()->with('error', 'Erreur lors de la suppression.');
        }
    }

    /**
     * Générer les périodes pour les formulaires
     */
    private function generatePeriods(): array
    {
        $periods = [];
        $currentDate = now();

        // Générer les 12 derniers mois
        for ($i = 0; $i < 12; $i++) {
            $date = $currentDate->copy()->subMonths($i);
            $periods[$date->format('Y-m')] = $date->format('F Y');
        }

        return $periods;
    }

    /**
     * Obtenir les produits en JSON pour AJAX
     */
    public function getProductInfo(Request $request): \Illuminate\Http\JsonResponse
    {
        $productId = $request->get('product_id');

        $product = Product::with('pointValeur')->find($productId);

        if (!$product) {
            return response()->json(['error' => 'Produit non trouvé'], 404);
        }

        return response()->json([
            'id' => $product->id,
            'name' => $product->nom_produit,
            'code' => $product->code_product,
            'price' => $product->prix_product,
            'points' => optional($product->pointValeur)->numbers ?? 0,
        ]);
    }

    /**
     * Exécute une suppression approuvée (appelée par DeletionRequestController)
     */
    public function executeDeletion(DeletionRequest $deletionRequest): RedirectResponse
    {
        if (!$deletionRequest->canBeExecuted()) {
            return back()->with('error', 'Cette demande ne peut pas être exécutée.');
        }

        $achat = $deletionRequest->entity();
        if (!$achat || !($achat instanceof Achat)) {
            return back()->with('error', 'L\'achat à supprimer n\'existe plus.');
        }

        DB::beginTransaction();
        try {
            // Créer un backup
            $backupData = [
                'achat' => $achat->toArray(),
                'distributeur_id' => $achat->distributeur_id,
                'produit_id' => $achat->produit_id,
                'quantite' => $achat->quantite,
                'montant_total' => $achat->montant_total_ligne,
                'periode' => $achat->periode,
                'deleted_at' => now()->toISOString(),
                'deleted_by' => Auth::id()
            ];

            // Supprimer l'achat
            $achat->delete();

            // Marquer la demande comme complétée
            $deletionRequest->markAsCompleted([
                'backup_data' => $backupData,
                'executed_by' => Auth::id()
            ]);

            DB::commit();

            return redirect()
                ->route('admin.deletion-requests.index')
                ->with('success', 'Achat supprimé avec succès.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erreur exécution suppression achat", [
                'deletion_request_id' => $deletionRequest->id,
                'achat_id' => $achat->id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return back()->with('error', 'Erreur lors de l\'exécution: ' . $e->getMessage());
        }
    }
}
