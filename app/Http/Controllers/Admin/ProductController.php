<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Models\PointValeur;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\RedirectResponse;

class ProductController extends Controller
{
    /**
     * Display a listing of products.
     */
    public function index(Request $request): View
    {
        // Récupérer le terme de recherche
        $searchTerm = $request->query('search');

        // Construire la requête de base avec relations
        $productsQuery = Product::with(['category', 'pointValeur']);

        // Appliquer le filtre de recherche si fourni
        if ($searchTerm) {
            $productsQuery->where(function ($query) use ($searchTerm) {
                $query->where('nom_produit', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('code_product', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('description', 'LIKE', "%{$searchTerm}%");
            });
        }

        // Paginer les résultats
        $products = $productsQuery->orderBy('nom_produit', 'asc')
                                  ->paginate(20)
                                  ->withQueryString();

        return view('admin.products.index', [
            'products' => $products,
            'searchTerm' => $searchTerm
        ]);
    }

    /**
     * Show the form for creating a new product.
     */
    public function create(): View
    {
        // Récupérer les catégories et points valeurs pour les selects
        $categories = Category::orderBy('name')->pluck('name', 'id');
        $pointValeurs = PointValeur::orderBy('numbers')->pluck('numbers', 'id');

        return view('admin.products.create', compact('categories', 'pointValeurs'));
    }

    /**
     * Store a newly created product in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        // Validation
        $validatedData = $request->validate([
            'code_product' => 'required|string|max:50|unique:products,code_product',
            'nom_produit' => 'required|string|max:255',
            'prix_product' => 'required|numeric|min:0',
            'category_id' => 'nullable|integer|exists:categories,id',
            'pointvaleur_id' => 'required|integer|exists:pointvaleurs,id',
            'description' => 'nullable|string|max:1000',
        ]);

        try {
            Product::create($validatedData);

            return redirect()->route('admin.products.index')
                           ->with('success', 'Produit créé avec succès.');
        } catch (\Exception $e) {
            Log::error("Erreur lors de la création du produit: " . $e->getMessage());
            return back()->withInput()
                        ->with('error', 'Erreur lors de la création du produit.');
        }
    }

    /**
     * Display the specified product.
     */
    public function show(Product $product): View
    {
        // Charger les relations
        $product->load(['category', 'pointValeur']);

        return view('admin.products.show', compact('product'));
    }

    /**
     * Show the form for editing the specified product.
     */
    public function edit(Product $product): View
    {
        $categories = Category::orderBy('name')->pluck('name', 'id');
        $pointValeurs = PointValeur::orderBy('numbers')->pluck('numbers', 'id');

        return view('admin.products.edit', compact('product', 'categories', 'pointValeurs'));
    }

    /**
     * Update the specified product in storage.
     */
    public function update(Request $request, Product $product): RedirectResponse
    {
        // Validation (code_product peut rester unique sauf pour le produit actuel)
        $validatedData = $request->validate([
            'code_product' => 'required|string|max:50|unique:products,code_product,' . $product->id,
            'nom_produit' => 'required|string|max:255',
            'prix_product' => 'required|numeric|min:0',
            'category_id' => 'nullable|integer|exists:categories,id',
            'pointvaleur_id' => 'required|integer|exists:pointvaleurs,id',
            'description' => 'nullable|string|max:1000',
        ]);

        try {
            $product->update($validatedData);

            return redirect()->route('admin.products.show', $product)
                           ->with('success', 'Produit mis à jour avec succès.');
        } catch (\Exception $e) {
            Log::error("Erreur lors de la mise à jour du produit: " . $e->getMessage());
            return back()->withInput()
                        ->with('error', 'Erreur lors de la mise à jour du produit.');
        }
    }

    /**
     * Remove the specified product from storage.
     */
    public function destroy(Product $product): RedirectResponse
    {
        try {
            // Vérifier s'il y a des achats liés
            if ($product->achats()->exists()) {
                return back()->with('error', 'Impossible de supprimer ce produit car il est lié à des achats.');
            }

            $product->delete();

            return redirect()->route('admin.products.index')
                           ->with('success', 'Produit supprimé avec succès.');
        } catch (\Exception $e) {
            Log::error("Erreur lors de la suppression du produit: " . $e->getMessage());
            return back()->with('error', 'Erreur lors de la suppression du produit.');
        }
    }
}
