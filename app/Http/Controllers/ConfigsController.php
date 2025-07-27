<?php

namespace App\Http\Controllers;

use App\Models\Achat;
use App\Models\Distributeur;
use App\Models\Level_current_test;
use App\Services\EternalHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Carbon\Carbon;

class ConfigsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        $grade = 1;
        return view('layouts.configs.index',[
            'grade' => $grade,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */


    public function create()
    {
        //2292536 ; 2270599 ; 2270468
        // EGALISER LES ETOILES AVEC CEUX DU PRECEDENT
        /*
        $period = '2024-03';
        $period2 = '2024-04';

        $level = Level_current_test::where('period', $period)->get();

        foreach ($level as $key => $value) {
            $level2 = Level_current_test::where('period', $period2)->where('distributeur_id', $value->distributeur_id)->first();

            $level2->etoiles = $value->etoiles;
            $level2->update();

            $tab[] = array(
                'distributeur_id' => $value->distributeur_id,
                'etoiles_23' => $value->etoiles,
                'etoiles_04' => $level2->etoiles,
            );
        }
        return $tab;
        */

        // 2292548  2292543
        //$level = Level_current_test::whereExists('distributeur_id', '2292548')->where('period', '2024-04')->first();
        $level = Level_current_test::whereNotNull('distributeur_id')->where('period', '2024-04')->get();
        $eternal = new EternalHelper();

        foreach ($level as $value) {
            $etoiles = $eternal->avancementGrade($value->distributeur_id, $value->etoiles, $value->cumul_individuel, $value->cumul_collectif);
            if($etoiles > $value->etoiles)
            {
                /*
                $level2 = Level_current_test::where('period','2024-04')->where('distributeur_id', $value->distributeur_id)->first();
                $level2->etoiles = $etoiles;
                $level2->update();
                */
                $tab[] = array(
                    'period' => $value->period,
                    'distributeur_id' => $value->distributeur_id,
                    'etoiles_03' => $value->etoiles,
                    'etoiles_04' => $etoiles,
                );
            }
        }

        return $tab;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
        $level = Level_current_test::groupBy('period')->orderBy('period', 'desc')->first('period');
        $level_last_month = Level_current_test::where('period', $level->period)->get();
        $actual_month = Carbon::parse($level->period)->addMonth()->format('Y-m');

        foreach ($level_last_month as $value) {

            $level_current = new Level_current_test();
            $level_current->period = $actual_month ?? 0;
            $level_current->distributeur_id = $value->distributeur_id;
            $level_current->etoiles = $value->etoiles ?? 1;
            $level_current->cumul_individuel = $value->cumul_individuel ?? 0;
            $level_current->new_cumul = 0;
            $level_current->cumul_total = 0;
            $level_current->cumul_collectif = $value->cumul_collectif ?? 0;
            $level_current->id_distrib_parent = ($value->id_distrib_parent);
            $level_current->created_at = Carbon::now();
            $level_current->save();

        }
        return $level_last_month;
        //return
        //return Carbon::now()->format('Y-m');

    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
        //CLOTURER LE MOIS EN COURS
        $lastAchat = Achat::orderBy('period', 'DESC')->first();

        $lastLevels = Level_current_test::where('period', $lastAchat->period)->get();
        $periodCarbon = Carbon::createFromFormat('Y-m', $lastAchat->period)->addMonth();
        $period = Carbon::parse($periodCarbon)->format('Y-m');

        foreach ($lastLevels as $level) {

            $levels = new Level_current_test();
            $levels->rang = 0;
            $levels->period = $period;
            $levels->distributeur_id = $level->distributeur_id;
            $levels->etoiles = $level->etoiles;
            $levels->cumul_individuel = $level->cumul_individuel;
            $levels->new_cumul = 0;
            $levels->cumul_total = 0;
            $levels->cumul_collectif = $level->cumul_collectif;
            $levels->id_distrib_parent = $level->id_distrib_parent;
            $levels->created_at = $periodCarbon;
            $levels->save();

        }

        flash(message: 'Le mois a bien été clôturé')->success();
        return redirect('configs');

    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
        return 'bonjour edit';
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
        return 'bonjour update';
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
        return 'bonjour destroy';
    }
}
