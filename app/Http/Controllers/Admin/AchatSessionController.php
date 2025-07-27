<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Distributeur;
use App\Models\Product;
use App\Models\Achat;
use App\Models\SystemPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AchatSessionController extends Controller
{
    /**
     * Démarre une nouvelle session d'achats
     */
    public function start(Request $request)
    {
        // Si une session existe déjà, rediriger vers le récapitulatif
        if (session()->has('achats_session')) {
            return redirect()->route('admin.achats.session.summary')
                ->with('info', 'Une session est déjà en cours.');
        }

        $distributeurs = Distributeur::orderBy('distributeur_id')->get();
        $currentPeriod = SystemPeriod::getCurrentPeriod();

        return view('admin.achats.session.start', compact('distributeurs', 'currentPeriod'));
    }

    /**
     * Initialise la session avec le distributeur sélectionné
     */
    public function init(Request $request)
    {
        $validated = $request->validate([
            'distributeur_id' => 'required|exists:distributeurs,id',
            'date' => 'required|date|before_or_equal:today'
        ]);

        $distributeur = Distributeur::find($validated['distributeur_id']);

        // Initialiser la session
        session([
            'achats_session' => [
                'distributeur_id' => $distributeur->id,
                'distributeur_info' => $distributeur->distributeur_id . ' - ' . $distributeur->nom_distributeur . ' ' . $distributeur->pnom_distributeur,
                'date' => $validated['date'],
                'period' => SystemPeriod::getCurrentPeriod()->period,
                'items' => [],
                'totaux' => [
                    'montant' => 0,
                    'points' => 0,
                    'nb_items' => 0
                ],
                'created_at' => now()->toDateTimeString()
            ]
        ]);

        return redirect()->route('admin.achats.session.summary')
            ->with('success', 'Session d\'achats démarrée pour ' . $distributeur->nom_distributeur . ' ' . $distributeur->pnom_distributeur);
    }

    /**
     * Affiche le récapitulatif de la session
     */
    public function summary()
    {
        if (!session()->has('achats_session')) {
            return redirect()->route('admin.achats.session.start')
                ->with('error', 'Aucune session en cours.');
        }

        $session = session('achats_session');
        $products = Product::with('pointValeur')->orderBy('nom_produit')->get(); // CORRECTION ICI : nom_produit au lieu de nom

        return view('admin.achats.session.summary', compact('session', 'products'));
    }

    /**
     * Ajoute un produit à la session
     */
    public function addItem(Request $request)
    {
        if (!session()->has('achats_session')) {
            return response()->json(['error' => 'Aucune session en cours'], 400);
        }

        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1'
        ]);

        $product = Product::with('pointValeur')->find($validated['product_id']);

        if (!$product->pointValeur) {
            return response()->json(['error' => 'Ce produit n\'a pas de valeur en points'], 400);
        }

        $session = session('achats_session');

        // Vérifier si le produit existe déjà dans la session
        $existingIndex = collect($session['items'])->search(function ($item) use ($product) {
            return $item['product_id'] == $product->id;
        });

        if ($existingIndex !== false) {
            // Mettre à jour la quantité
            $session['items'][$existingIndex]['quantity'] += $validated['quantity'];
            $session['items'][$existingIndex]['montant_total'] =
                $session['items'][$existingIndex]['quantity'] * $session['items'][$existingIndex]['prix_unitaire'];
            $session['items'][$existingIndex]['points_total'] =
                $session['items'][$existingIndex]['quantity'] * $session['items'][$existingIndex]['points_unitaire'];
        } else {
            // Ajouter un nouveau produit
            $session['items'][] = [
                'product_id' => $product->id,
                'product_name' => $product->nom_produit, // CORRECTION ICI : nom_produit au lieu de nom
                'quantity' => $validated['quantity'],
                'prix_unitaire' => $product->prix_product,
                'points_unitaire' => $product->pointValeur->numbers,
                'montant_total' => $product->prix_product * $validated['quantity'],
                'points_total' => $product->pointValeur->numbers * $validated['quantity']
            ];
        }

        // Recalculer les totaux
        $session['totaux'] = $this->calculateTotals($session['items']);

        session(['achats_session' => $session]);

        return response()->json([
            'success' => true,
            'message' => 'Produit ajouté',
            'totaux' => $session['totaux']
        ]);
    }

    /**
     * Retire un produit de la session
     */
    public function removeItem(Request $request)
    {
        if (!session()->has('achats_session')) {
            return response()->json(['error' => 'Aucune session en cours'], 400);
        }

        $validated = $request->validate([
            'index' => 'required|integer|min:0'
        ]);

        $session = session('achats_session');

        if (!isset($session['items'][$validated['index']])) {
            return response()->json(['error' => 'Produit non trouvé'], 404);
        }

        // Retirer le produit
        array_splice($session['items'], $validated['index'], 1);

        // Recalculer les totaux
        $session['totaux'] = $this->calculateTotals($session['items']);

        session(['achats_session' => $session]);

        return response()->json([
            'success' => true,
            'message' => 'Produit retiré',
            'totaux' => $session['totaux']
        ]);
    }

    /**
     * Valide tous les achats de la session
     */
    public function validate(Request $request)
    {
        if (!session()->has('achats_session')) {
            return redirect()->route('admin.achats.session.start')
                ->with('error', 'Aucune session en cours.');
        }

        $session = session('achats_session');

        if (empty($session['items'])) {
            return redirect()->back()
                ->with('error', 'Aucun produit dans la session.');
        }

        DB::beginTransaction();
        try {
            $achatsCreated = [];
            $currentPeriod = SystemPeriod::getCurrentPeriod();

            foreach ($session['items'] as $item) {
                $achat = Achat::create([
                    'distributeur_id' => $session['distributeur_id'],
                    'products_id' => $item['product_id'],
                    'qt' => $item['quantity'],
                    'prix_unitaire_achat' => $item['prix_unitaire'],
                    'points_unitaire_achat' => $item['points_unitaire'],
                    'montant_total_ligne' => $item['montant_total'],
                    'period' => $currentPeriod->period,
                    'purchase_date' => $session['date'],
                    'online' => false,
                    'status' => 'validated',
                    'validated_at' => now()
                ]);

                $achatsCreated[] = $achat->id;
            }

            DB::commit();

            // Log de la session
            Log::info('Session d\'achats validée', [
                'distributeur_id' => $session['distributeur_id'],
                'nb_achats' => count($achatsCreated),
                'montant_total' => $session['totaux']['montant'],
                'points_total' => $session['totaux']['points'],
                'user_id' => Auth::id()
            ]);

            // Effacer la session
            session()->forget('achats_session');

            return redirect()->route('admin.achats.index')
                ->with('success', count($achatsCreated) . ' achats créés avec succès. Montant total: ' .
                    number_format($session['totaux']['montant'], 0, ',', ' ') . ' FCFA');

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Erreur validation session achats', [
                'error' => $e->getMessage(),
                'session' => $session
            ]);

            return redirect()->back()
                ->with('error', 'Erreur lors de la validation: ' . $e->getMessage());
        }
    }

    /**
     * Annule la session en cours
     */
    public function cancel()
    {
        session()->forget('achats_session');

        return redirect()->route('admin.achats.index')
            ->with('info', 'Session d\'achats annulée.');
    }

    /**
     * Calcule les totaux d'une liste d'items
     */
    private function calculateTotals($items)
    {
        $montant = 0;
        $points = 0;

        foreach ($items as $item) {
            $montant += $item['montant_total'];
            $points += $item['points_total'];
        }

        return [
            'montant' => $montant,
            'points' => $points,
            'nb_items' => count($items)
        ];
    }
}
