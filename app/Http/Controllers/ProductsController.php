<?php

namespace App\Http\Controllers;
use App\Models\Product;
use App\Models\Category;
use App\Models\Pointvaleur;

use Illuminate\Http\Request;

class ProductsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $products = Product::orderby('created_at', 'DESC')->get();
        $categories = Category::pluck('name', 'id');
        $pointvaleurs = Pointvaleur::pluck('numbers', 'id');
        //return $categories->keys();
        return view('layouts.products.index')->with([
            "products" => $products,
            "categories" => $categories,
            "pointvaleurs" => $pointvaleurs
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //return view( view:'layouts.products.create');

        $categories = Category::all();
        $pointvaleurs = Pointvaleur::all();
        return view('layouts.products.create', [
            "categories" => $categories,
            "pointvaleurs" => $pointvaleurs,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Validation
        $this->validate($request, [
            'category_id',
            'pointvaleur_id',
            'code_product' => 'required|min:2|max:50|unique:products',
            'nom_produit' => 'required|min:2|max:120|unique:products',
            'prix_product' => 'required|min:2',
            'description',
        ]);

        $products = new Product();
        $products->code_product = $request->code_product;
        $products->category_id = $request->category_id;
        $products->pointvaleur_id = $request->pointvaleur_id;
        $products->nom_produit = $request->nom_produit;
        $products->description = $request->description;
        $products->prix_product = $request->prix_product;
        $products->save();

        flash(message: 'action executer avec succes')->success();
        return back();
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $products = Product::findOrFail($id);
        $pvs = Pointvaleur::all();
        $categories = Category::all();
        return view('layouts.products.edit', [
            "products" => $products,
            "pvs" => $pvs,
            "categories" => $categories
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $this->validate($request, [
            'category_id' . $id,
            'pointvaleur_id',
            'code_product' => 'required|min:2|max:50',
            'nom_produit' => 'required|min:2|max:120',
            'prix_product' => 'required|min:2',
            'description',
        ]);

        $products = Product::findOrFail($id);

        $products->category_id = $request->category_id;
        $products->code_product = $request->code_product;
        $products->nom_produit = $request->nom_produit;
        $products->pointvaleur_id = $request->pointvaleur_id;
        $products->prix_product = $request->prix_product;
        $products->description = $request->description;
        $products->save();

        flash('Le Produit a été mise à jour')->success();
        return redirect()->route(route: 'products.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
