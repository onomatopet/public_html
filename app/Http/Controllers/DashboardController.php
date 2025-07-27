<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Distributeur;
use App\Models\Level;
use App\Models\Achat;
use Illuminate\Support\Arr;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //set_time_limit(240);
        //$distribparents = Distributeur::select('nom_distributeur', 'distributeur_id', 'id', 'id_parent','pnom_distributeur')->get();
        $latestachat = Distributeur::join('achats', 'achats.distributeur_id', '=', 'distributeurs.distributeur_id')
        ->join('products', 'products.id', '=', 'achats.products_id')
        ->limit(5)
        ->get(['distributeurs.*', 'achats.*', 'products.*']);
        $achat = Achat::whereMonth('created_at', '=', Carbon::now()->subMonth()->month);
        $nouvadherant = Distributeur::whereMonth('created_at', '=', Carbon::now()->subMonth()->month);
        $nbadherant = $nouvadherant->count();
        $nbachats = $achat->count();
        return view('dashboard', [
            "nbachats" => $nbachats,
            "nbadherant" => $nbadherant,
            "achat" => $achat,
            "latestachat" => $latestachat
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
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
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
