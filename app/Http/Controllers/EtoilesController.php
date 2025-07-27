<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Etoile;
use XMLReader;

class EtoilesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $etoiles = Etoile::orderby('id', 'ASC')->get();
        return view('layouts.etoiles.index', compact('etoiles'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('layouts.etoiles.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Validation
        $this->validate($request, [
            'cumul_individuel' => 'required|min:1|max:50',
            'cumul_collectif_1' => 'required|min:1|max:50',
            'cumul_collectif_2' => 'required|min:1|max:50',
            'cumul_collectif_3' => 'required|min:1|max:50',
            'cumul_collectif_4' => 'required|min:1|max:50',
        ]);

        $etoiles = new Etoile();
        $etoiles->etoile_level = $request->etoile_level;
        $etoiles->cumul_individuel = $request->cumul_individuel;
        $etoiles->cumul_collectif_1 = $request->cumul_collectif_1;
        $etoiles->cumul_collectif_2 = $request->cumul_collectif_2;
        $etoiles->cumul_collectif_3 = $request->cumul_collectif_3;
        $etoiles->cumul_collectif_4 = $request->cumul_collectif_4;
        $etoiles->save();

        flash(message: 'action executer avec succes')->success();
        return back();
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $req)
    {

    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $etoiles = Etoile::findOrFail($id);
        return view('layouts.etoiles.edit', [
            "etoiles" => $etoiles,
        ]);
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
