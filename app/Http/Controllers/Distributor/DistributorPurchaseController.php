<?php

namespace App\Http\Controllers\Distributor;

use App\Http\Controllers\Controller;
use App\Models\Achat;
use App\Models\Product;
use App\Models\Category;
use App\Models\SystemPeriod;
use App\Services\CartService;
use App\Services\PurchaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DistributorPurchaseController extends Controller
{
    protected CartService $cartService;
    protected PurchaseService $purchaseService;

    public function __construct(CartService $cartService, PurchaseService $purchaseService)
    {
        $this->cartService = $cartService;
        $this->purchaseService = $purchaseService;
    }

    /**
     * Affiche l'historique des achats
     */
    public function index(Request $request)
    {
        $distributeur = Auth::user()->distributeur;

        if (!$distributeur) {
            return redirect()->route('distributor.dashboard')
                ->with('error', 'Profil distributeur non trouvé.');
        }

        // Paramètres de filtrage
        $period = $request->get('period');
        $status = $request->get('status');
        $search = $request->get('search');

        // Construire la requête
        $query = Achat::where('distributeur_id', $distributeur->id)
            ->with(['product', 'session']);

        // Appliquer les filtres
        if ($period) {
            $query->where('period', $period);
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($search) {
            $query->whereHas('product', function($q) use ($search) {
                $q->where('nom_produit', 'like', "%{$search}%")
                  ->orWhere('code_product', 'like', "%{$search}%");
            });
        }

        // Ordre et pagination
        $purchases = $query->orderBy('purchase_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        // Statistiques
        $stats = $this->getPurchaseStats($distributeur);

        // Périodes disponibles
        $availablePeriods = Achat::where('distributeur_id', $distributeur->id)
            ->distinct('period')
            ->orderBy('period', 'desc')
            ->pluck('period');

        return view('distributor.purchases.index', compact(
            'purchases',
            'stats',
            'availablePeriods',
            'period',
            'status',
            'search'
        ));
    }

    /**
     * Affiche les détails d'un achat
     */
    public function show($id)
    {
        $distributeur = Auth::user()->distributeur;
        $purchase = Achat::where('distributeur_id', $distributeur->id)
            ->with(['product', 'session', 'returns'])
            ->findOrFail($id);

        // Historique de statut si disponible
        $statusHistory = $this->getStatusHistory($purchase);

        // Achats similaires
        $similarPurchases = Achat::where('distributeur_id', $distributeur->id)
            ->where('products_id', $purchase->products_id)
            ->where('id', '!=', $purchase->id)
            ->limit(5)
            ->get();

        return view('distributor.purchases.show', compact(
            'purchase',
            'statusHistory',
            'similarPurchases'
        ));
    }

    /**
     * Affiche le catalogue de produits
     */
    public function catalog(Request $request)
    {
        $distributeur = Auth::user()->distributeur;

        // Paramètres de recherche et filtrage
        $search = $request->get('search');
        $categoryId = $request->get('category');
        $sortBy = $request->get('sort', 'name');

        // Construire la requête
        $query = Product::where('is_active', true)
            ->with(['category']);

        // Appliquer les filtres
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('nom_produit', 'like', "%{$search}%")
                  ->orWhere('code_product', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        // Tri
        switch ($sortBy) {
            case 'price_asc':
                $query->orderBy('prix_product', 'asc');
                break;
            case 'price_desc':
                $query->orderBy('prix_product', 'desc');
                break;
            case 'points':
                $query->orderBy('point_product', 'desc');
                break;
            case 'newest':
                $query->orderBy('created_at', 'desc');
                break;
            default:
                $query->orderBy('nom_produit', 'asc');
        }

        $products = $query->paginate(12)->withQueryString();

        // Catégories pour le filtre
        $categories = Category::whereHas('products', function($q) {
            $q->where('is_active', true);
        })->get();

        // Panier actuel
        $cart = $this->cartService->getCart($distributeur->id);

        // Promotions actuelles
        $promotions = $this->getActivePromotions();

        return view('distributor.purchases.catalog', compact(
            'products',
            'categories',
            'cart',
            'promotions',
            'search',
            'categoryId',
            'sortBy'
        ));
    }

    /**
     * Ajoute un produit au panier
     */
    public function addToCart(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1'
        ]);

        $distributeur = Auth::user()->distributeur;
        $product = Product::findOrFail($validated['product_id']);

        // Vérifier la disponibilité
        if (!$product->is_active) {
            return back()->with('error', 'Ce produit n\'est plus disponible.');
        }

        // Ajouter au panier
        $this->cartService->addItem(
            $distributeur->id,
            $product->id,
            $validated['quantity']
        );

        return back()->with('success', 'Produit ajouté au panier.');
    }

    /**
     * Affiche le panier
     */
    public function cart()
    {
        $distributeur = Auth::user()->distributeur;
        $cart = $this->cartService->getCart($distributeur->id);

        // Calculer les totaux
        $totals = $this->cartService->calculateTotals($cart);

        // Vérifier les promotions applicables
        $applicablePromotions = $this->checkApplicablePromotions($cart);

        return view('distributor.purchases.cart', compact(
            'cart',
            'totals',
            'applicablePromotions'
        ));
    }

    /**
     * Met à jour la quantité d'un article du panier
     */
    public function updateCart(Request $request, $itemId)
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:0'
        ]);

        $distributeur = Auth::user()->distributeur;

        if ($validated['quantity'] == 0) {
            $this->cartService->removeItem($distributeur->id, $itemId);
            return back()->with('success', 'Article retiré du panier.');
        }

        $this->cartService->updateQuantity(
            $distributeur->id,
            $itemId,
            $validated['quantity']
        );

        return back()->with('success', 'Panier mis à jour.');
    }

    /**
     * Vide le panier
     */
    public function clearCart()
    {
        $distributeur = Auth::user()->distributeur;
        $this->cartService->clearCart($distributeur->id);

        return redirect()->route('distributor.purchases.catalog')
            ->with('success', 'Panier vidé.');
    }

    /**
     * Affiche la page de validation de commande
     */
    public function checkout()
    {
        $distributeur = Auth::user()->distributeur;
        $cart = $this->cartService->getCart($distributeur->id);

        if ($cart->isEmpty()) {
            return redirect()->route('distributor.purchases.catalog')
                ->with('error', 'Votre panier est vide.');
        }

        // Calculer les totaux
        $totals = $this->cartService->calculateTotals($cart);

        // Informations de livraison
        $shippingInfo = [
            'name' => $distributeur->nom_distributeur . ' ' . $distributeur->pnom_distributeur,
            'address' => $distributeur->adress_distributeur,
            'phone' => $distributeur->tel_distributeur,
            'email' => $distributeur->mail_distributeur
        ];

        // Modes de paiement disponibles
        $paymentMethods = $this->getAvailablePaymentMethods();

        return view('distributor.purchases.checkout', compact(
            'cart',
            'totals',
            'shippingInfo',
            'paymentMethods'
        ));
    }

    /**
     * Valide et enregistre la commande
     */
    public function placeOrder(Request $request)
    {
        $validated = $request->validate([
            'payment_method' => 'required|string',
            'shipping_address' => 'required|string',
            'shipping_phone' => 'required|string',
            'notes' => 'nullable|string|max:500'
        ]);

        $distributeur = Auth::user()->distributeur;
        $cart = $this->cartService->getCart($distributeur->id);

        if ($cart->isEmpty()) {
            return redirect()->route('distributor.purchases.catalog')
                ->with('error', 'Votre panier est vide.');
        }

        DB::beginTransaction();
        try {
            // Créer la commande
            $order = $this->purchaseService->createOrder(
                $distributeur,
                $cart,
                $validated
            );

            // Vider le panier
            $this->cartService->clearCart($distributeur->id);

            DB::commit();

            // Envoyer les notifications
            $this->purchaseService->sendOrderConfirmation($order);

            return redirect()->route('distributor.purchases.show', $order->id)
                ->with('success', 'Commande validée avec succès !');

        } catch (\Exception $e) {
            DB::rollback();

            return back()->with('error', 'Erreur lors de la validation de la commande.')
                ->withInput();
        }
    }

    /**
     * Affiche le récapitulatif mensuel
     */
    public function monthly(Request $request)
    {
        $distributeur = Auth::user()->distributeur;
        $period = $request->get('period', SystemPeriod::getCurrentPeriod()->period);

        // Achats du mois
        $monthlyPurchases = Achat::where('distributeur_id', $distributeur->id)
            ->where('period', $period)
            ->with('product')
            ->get();

        // Statistiques du mois
        $monthlyStats = [
            'total_amount' => $monthlyPurchases->sum('montant_total_ligne'),
            'total_points' => $monthlyPurchases->sum('points_total_ligne'),
            'total_items' => $monthlyPurchases->sum('qt'),
            'order_count' => $monthlyPurchases->count()
        ];

        // Top produits du mois
        $topProducts = $monthlyPurchases->groupBy('products_id')
            ->map(function ($group) {
                return [
                    'product' => $group->first()->product,
                    'quantity' => $group->sum('qt'),
                    'amount' => $group->sum('montant_total_ligne'),
                    'points' => $group->sum('points_total_ligne')
                ];
            })
            ->sortByDesc('amount')
            ->take(10);

        // Évolution journalière
        $dailyEvolution = $this->getDailyPurchaseEvolution($distributeur, $period);

        return view('distributor.purchases.monthly', compact(
            'period',
            'monthlyPurchases',
            'monthlyStats',
            'topProducts',
            'dailyEvolution'
        ));
    }

    /**
     * Télécharge une facture
     */
    public function invoice($id)
    {
        $distributeur = Auth::user()->distributeur;
        $purchase = Achat::where('distributeur_id', $distributeur->id)
            ->findOrFail($id);

        // Générer la facture PDF
        $pdf = $this->purchaseService->generateInvoice($purchase);

        return $pdf->download('facture_' . $purchase->id . '.pdf');
    }

    /**
     * Demande de retour
     */
    public function returnRequest(Request $request, $id)
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
            'items' => 'required|array'
        ]);

        $distributeur = Auth::user()->distributeur;
        $purchase = Achat::where('distributeur_id', $distributeur->id)
            ->findOrFail($id);

        // Vérifier si le retour est possible
        if (!$this->purchaseService->canReturn($purchase)) {
            return back()->with('error', 'Cette commande ne peut plus être retournée.');
        }

        // Créer la demande de retour
        $return = $this->purchaseService->createReturnRequest(
            $purchase,
            $validated
        );

        return redirect()->route('distributor.purchases.show', $purchase->id)
            ->with('success', 'Demande de retour créée avec succès.');
    }

    /**
     * Obtient les statistiques d'achat
     */
    protected function getPurchaseStats($distributeur): array
    {
        $currentPeriod = SystemPeriod::getCurrentPeriod();

        return [
            'total_all_time' => Achat::where('distributeur_id', $distributeur->id)
                ->sum('montant_total_ligne'),
            'total_this_month' => Achat::where('distributeur_id', $distributeur->id)
                ->where('period', $currentPeriod->period)
                ->sum('montant_total_ligne'),
            'points_this_month' => Achat::where('distributeur_id', $distributeur->id)
                ->where('period', $currentPeriod->period)
                ->sum('points_total_ligne'),
            'orders_this_month' => Achat::where('distributeur_id', $distributeur->id)
                ->where('period', $currentPeriod->period)
                ->count(),
            'average_order' => Achat::where('distributeur_id', $distributeur->id)
                ->where('period', $currentPeriod->period)
                ->avg('montant_total_ligne') ?? 0
        ];
    }

    /**
     * Obtient l'historique de statut d'un achat
     */
    protected function getStatusHistory($purchase): array
    {
        // Si un système de tracking existe
        return [
            [
                'status' => 'pending',
                'date' => $purchase->created_at,
                'note' => 'Commande créée'
            ],
            [
                'status' => 'validated',
                'date' => $purchase->validated_at ?? $purchase->created_at->addHours(1),
                'note' => 'Commande validée'
            ]
        ];
    }

    /**
     * Obtient les promotions actives
     */
    protected function getActivePromotions(): array
    {
        // Implémenter selon votre logique de promotions
        return [];
    }

    /**
     * Vérifie les promotions applicables au panier
     */
    protected function checkApplicablePromotions($cart): array
    {
        // Implémenter selon votre logique de promotions
        return [];
    }

    /**
     * Obtient les modes de paiement disponibles
     */
    protected function getAvailablePaymentMethods(): array
    {
        return [
            'bank_transfer' => 'Virement bancaire',
            'cash' => 'Espèces',
            'check' => 'Chèque'
        ];
    }

    /**
     * Obtient l'évolution journalière des achats
     */
    protected function getDailyPurchaseEvolution($distributeur, $period): array
    {
        $startDate = Carbon::parse($period . '-01');
        $endDate = $startDate->copy()->endOfMonth();

        $purchases = Achat::where('distributeur_id', $distributeur->id)
            ->where('period', $period)
            ->get();

        $dailyData = [];
        $currentDate = $startDate->copy();

        while ($currentDate <= $endDate) {
            $dayPurchases = $purchases->filter(function ($purchase) use ($currentDate) {
                return Carbon::parse($purchase->purchase_date)->format('Y-m-d') == $currentDate->format('Y-m-d');
            });

            $dailyData[] = [
                'date' => $currentDate->format('Y-m-d'),
                'day' => $currentDate->day,
                'amount' => $dayPurchases->sum('montant_total_ligne'),
                'points' => $dayPurchases->sum('points_total_ligne'),
                'count' => $dayPurchases->count()
            ];

            $currentDate->addDay();
        }

        return $dailyData;
    }
}
