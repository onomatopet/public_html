<?php

namespace App\Http\Controllers;

use App\Models\Bonuse;
use App\Models\Distributeur;
use Illuminate\Http\Request;

class RapportController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
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
    public function show(string $id, Request $request)
    {
        //
        if($request->period != 'all')
        {
            $bonusinfos = Distributeur::join('bonuses', 'bonuses.distributeur_id','=','distributeurs.distributeur_id')->where('bonuses.period', $request->period)->get(['distributeurs.*','bonuses.*']);
            $statistic = Bonuse::selectRaw('SUM(bonus) as total')->selectRaw('SUM(epargne) as epargn')->where('period', $request->period)->get();
            //return $bonusinfos;
            return view('layouts.bonus.rapportprinted', [
                "bonus" => $bonusinfos,
                "statistic" => $statistic
            ]);
        }
        else {
            $bonusinfos = Distributeur::join('bonuses', 'bonuses.distributeur_id','=','distributeurs.distributeur_id')->get(['distributeurs.*','bonuses.*']);
            $statistic = Bonuse::selectRaw('SUM(bonuses.bonus) as total')->selectRaw('SUM(epargne) as epargn')->get();
            //return $bonusinfos;
            return view('layouts.bonus.rapportprinted', [
                "bonus" => $bonusinfos,
                "statistic" => $statistic
            ]);
        }
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
