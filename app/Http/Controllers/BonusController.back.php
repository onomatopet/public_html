<?php

namespace App\Http\Controllers;
use Carbon\Carbon;

use Illuminate\Http\Request;
use App\Models\Distributeur;
use App\Models\Level;
use App\Models\Achat;
use App\Models\Bonuse;
use App\Models\Level_current_2024_01;
use App\Models\Level_current_2024_02;
use App\Models\Level_current_test;
use App\Models\Level_History;
use Illuminate\Support\Arr;
use Barryvdh\DomPDF\Facade\Pdf;

use function PHPUnit\Framework\isEmpty;

class BonusController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        $bonusinfos = Distributeur::join('bonuses', 'bonuses.distributeur_id','=','distributeurs.distributeur_id')->get(['distributeurs.*','bonuses.*']);
        $statistic = Bonuse::selectRaw('SUM(bonus) as total')->get();
        //return $statistic;
        return view('layouts.bonus.index', [
            "bonus" => $bonusinfos,
            "statistic" => $statistic
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        //

        $distributeurs = Distributeur::all();
        $period = Level_History::selectRaw("DATE_FORMAT(created_at, '%Y-%m') AS new_date")
        ->groupBy('new_date')
        ->orderBy('new_date', 'ASC')
        ->get();
        return view('layouts.bonus.create', [
            "distributeurs" => $distributeurs,
            //"period" => $period
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {

        // ON RECUPERE LES DONNEES DU PARENT
        //
        //return $request->distributeur_id;
        $level = $request->distributeur_id;
        /** @var array $tauxDirect */
        global $tauxDirect, $total_direct, $total_indirect;
        $partTab = [];$partBonus = [];$tabfinal = [];
        $epargne = 1;
        global $numero;
        global $etoilesDiff;

        $infosparent = Distributeur::where('distributeur_id', $level)->first();

        $bonusInfos = Bonuse::orderBy('id', 'DESC')->first();

        if($bonusInfos != null)
        {
            $numero = $bonusInfos->num+1;
        }
        else {
            $numero = '77700304001';
        }

        $total_direct = (float)$request->bonusDirect;
        $total_indirect = (float)$request->bonusIndirect;

        $bonus = $total_indirect + $total_direct;
        $decimal = $bonus - floor($bonus);

        if($decimal > 0.5)
        {
            $bonus = floor($bonus);
        }
        else {
            $bonus = $bonus-1;
        }

        $distributeurs = array(
            'distributeur_id' => $infosparent->distributeur_id,
            'period' => '2024-02',
            'numero' => $numero,
            'nom_distributeur' => $infosparent->nom_distributeur,
            'pnom_distributeur' => $infosparent->pnom_distributeur,
            'bonus_direct' => $total_direct,
            'bonus_indirect' => $total_indirect,
            'bonus' => $bonus
        );


        return view('layouts.bonus.temp', [
            "distributeurs" => $distributeurs
        ]);

        //l'algoritme normale
        /*
        */
        //l'algorithme trafiqué

        /*
        //return [$distributeurs, $numero];

        //return $bonusTotal;
        /*
        $options = PDF::getDomPDF()->getOptions();
        $options->set('isFontSubsettingEnabled', true);

        $pdf = Pdf::loadView('layouts.bonus.pdf', ['distributeurs' => $results]);
        return $pdf->download();*/
        //return $results;


    }

    public function etoilesRetreiver($etoiles)
    {
        switch($etoiles)
        {
            case 1: $taux_ind = 0; $taux_dir = 0;
            break;
            case 2: $taux_ind = 6/100; $taux_dir = 6/100;
            break;
            case 3: $taux_ind = 16/100; $taux_dir = 22/100;
            break;
            case 4: $taux_ind = 4/100; $taux_dir = 26/100;
            break;
            case 5: $taux_ind = 4/100; $taux_dir = 30/100;
            break;
            case 6: $taux_ind = 4/100; $taux_dir = 34/100;
            break;
            case 7: $taux_ind = 6/100; $taux_dir = 40/100;
            break;
            case 8: $taux_ind = 3/100; $taux_dir = 43/100;
            break;
            case 9: $taux_ind = 2/100; $taux_dir = 45/100;
            break;
            case 10: $taux_ind = 2/100; $taux_dir = 45/100;
            break;
        }
        return [$taux_dir, $taux_ind];
    }
    /**
     * Display the specified resource.
     */

    public function allChildrenGet($disitributeurId)
    {
        $children = [];

        $children[] = $disitributeurId;
        $parentDistributeurs = Level_current_2024_02::where('id_distrib_parent', $disitributeurId)->get();
        //return $parentDistributeurs;
            foreach ($parentDistributeurs as $parent)
            {
                $children[] = array(
                    'distributeur_id' => $parent->distributeur_id,
                    'etoiles' => $parent->etoiles,
                );

                $children[] = $this->allChildrenGet($parent->distributeur_id, $children);
            }
        return $children;
    }

    public function show(Request $request)
    {

        // ON RECUPERE LES DONNEES DU PARENT

        //
        $level = $request->keys();
        /** @var array $tauxDirect */
        global $tauxDirect, $total_direct, $total_indirect;
        $partTab = [];
        $partBonus = [];$tabfinal = [];
        global $etoilesDiff;
        $period = '2024-02';
        $bonusTotal = 0;

        $distrib = Level_current_2024_02::where('distributeur_id', $level)->first();
        $infosparent = Distributeur::where('distributeur_id', $level)->first();

        $genealogie = $this->getChild($level, $distrib->etoiles);
        //return $genealogie;

        foreach ($genealogie as $key => $enfants) {

            $childs = Level_current_2024_02::where('distributeur_id', $enfants)->first();
            //return $this->isdirect($childs->etoiles, $childs->new_cumul);
            if($childs->cumul_total > 0)
            {
                $etoilesDiff = $infosparent->etoiles_id - $childs->etoiles;
                //return $etoilesDiff.' = '.$infosparent->etoiles.' - '.$childs->etoiles;

                $taux = $this->etoilesChecker($infosparent->etoiles_id, $etoilesDiff);

                $part = array(
                    'distributeur_id' => $childs->distributeur_id,
                    'etoiles' => $childs->etoiles,
                    'taux' => $taux,
                    'cumul_total' => $childs->cumul_total,
                    'part_parent' => ($childs->cumul_total * $taux)
                );
                $partTab[] = $part;
                //$partTab[] = $this->getChildrenNetworkBonusCalculate($childs->distributeur_id, $taux);
            }
            else {
                $partTab[] = null;
            }
        }

        for ($i=0; $i < count($partTab); $i++) {
            if($partTab[$i] !=null)
            {
                $partBonus[] = $partTab[$i] ;
            }
        }

        $partBonus = Arr::flatten($partBonus);

        for($i=0; $i < intdiv(count($partBonus), 5); $i++)
        {
            $j = $i*5;
            $k = $j+1; $m = $j+2; $n = $j+3; $p = $j+4;
            if($p == 3754)
            {
                $k = $j+1; $m = $j+2; $n = $j+3; $p = $j+4;
                $tabfinal[] = array(
                    'distributeur_id' => $partBonus[($j+1)],
                    'etoiles' => $partBonus[$k],
                    'taux' => $partBonus[$m],
                    'cumul_total' => $partBonus[$n],
                    'part_parent' => $partBonus[$p],
                );
            }
            $tabfinal[] = array(
                'distributeur_id' => $partBonus[$j],
                'etoiles' => $partBonus[$k],
                'taux' => $partBonus[$m],
                'cumul_total' => $partBonus[$n],
                'part_parent' => $partBonus[$p]
            );
        }
        foreach ($tabfinal as $key => $value) {

            if($value['part_parent'] != 0)
            {
                $bonusTotal = $bonusTotal + $value['part_parent'];
            }
        }

        $tauxDirect = $this->etoilesRetreiver($distrib->etoiles);

        $new_cumul = $distrib->new_cumul * 1;
        $bonusDirect = $new_cumul * $tauxDirect[0];
        $epargne = 1;
        global $numero;

        $bonusInfos = Bonuse::orderBy('id', 'DESC')->first();
        if($bonusInfos != null)
        {
            $numero = $bonusInfos->num+1;
        }
        else {
            $numero = '77700304001';
        }

        $total_direct = 46;
        $total_indirect = 8;

        $bonus = $total_indirect + $total_direct;
        $decimal = $bonus - floor($bonus);

        if($decimal > 0.5)
        {
            $bonus = floor($bonus);
        }
        else {
            $bonus = $bonus-1;
        }

        $distributeurs = array(
            'distributeur_id' => $distrib->distributeur_id,
            'period' => $period,
            'numero' => $numero,
            'nom_distributeur' => $infosparent->nom_distributeur,
            'pnom_distributeur' => $infosparent->pnom_distributeur,
            'bonus_direct' => $total_direct,
            'bonus_indirect' => $total_indirect,
            'bonus' => $bonus
        );
        /*
        $bonusInserted = new Bonuse();
        $bonusInserted->period = $period;
        $bonusInserted->num = $numero;
        $bonusInserted->distribiteur_id = $distrib->distributeur_id;
        $bonusInserted->bonus_direct = $total_direct;
        $bonusInserted->bonus_indirect = $total_indirect;
        $bonusInserted->bonus = $bonus;
        //$bonusInserted->bonus_leadership
        $bonusInserted->epargne = $decimal;
        $bonusInserted->save();

        //l'algoritme normale
        /*
        */
        //l'algorithme trafiqué

        /*
        //return [$distributeurs, $numero];

        //return $bonusTotal;
        /*
        $options = PDF::getDomPDF()->getOptions();
        $options->set('isFontSubsettingEnabled', true);

        $pdf = Pdf::loadView('layouts.bonus.pdf', ['distributeurs' => $results]);
        return $pdf->download();*/
        //return $results;

        return view('layouts.bonus.print', [
            "distributeurs" => $distributeurs
        ]);
    }


    public function etoilesChecker($etoiles, $diff)
    {
        switch($etoiles)
        {
            case 1 :

                    $taux = 0;
            break;
            case 2 :

                if($diff <= 0)
                    $taux = 0;
                if($diff == 1)
                $taux = 0.40;
            break;
            case 3 :

                if($diff <= 0)
                    $taux = 0;
                if($diff == 1)
                    $taux = 0.38;
                if($diff == 2)
                    $taux = 0.40;
            break;
            case 4 :

                if($diff <= 0)
                    $taux = 0;
                if($diff == 1)
                    $taux = 0.18;
                if($diff == 2)
                    $taux = 0.38;
                if($diff == 3)
                    $taux = 0.40;
            break;
            case 5 :

                if($diff <= 0)
                    $taux = 0;
                if($diff == 1)
                    $taux = 0.14;
                if($diff == 2)
                    $taux = 0.18;
                if($diff == 3)
                    $taux = 0.38;
                if($diff == 4)
                    $taux = 0.40;
            break;
            case 6 :

                if($diff <= 0)
                    $taux = 0;
                if($diff == 1)
                    $taux = 0.1;
                if($diff == 2)
                    $taux = 0.14;
                if($diff == 3)
                    $taux = 0.18;
                if($diff == 4)
                    $taux = 0.38;
                if($diff == 5)
                    $taux = 0.40;
            break;
            case 7 :

                if($diff == 0)
                    $taux = 0;
                if($diff == 1)
                    $taux = 0.06;
                if($diff == 2)
                    $taux = 0.1;
                if($diff == 3)
                    $taux = 0.14;
                if($diff == 4)
                    $taux = 0.18;
                if($diff == 5)
                    $taux = 0.38;
                if($diff == 6)
                    $taux = 0.40;
            break;
            case 8 :

                if($diff == 0)
                    $taux = 0;
                if($diff == 1)
                    $taux = 0.04;
                if($diff == 2)
                    $taux = 0.06;
                if($diff == 3)
                    $taux = 0.1;
                if($diff == 4)
                    $taux = 0.14;
                if($diff == 5)
                    $taux = 0.18;
                if($diff == 6)
                    $taux = 0.38;
                if($diff == 7)
                    $taux = 0.40;

            break;
            case 9 :

                if($diff == 0)
                    $taux = 0;
                if($diff == 1)
                    $taux = 0.02;
                if($diff == 2)
                    $taux = 0.04;
                if($diff == 3)
                    $taux = 0.06;
                if($diff == 3)
                    $taux = 0.06;
                if($diff == 4)
                    $taux = 0.1;
                if($diff == 5)
                    $taux = 0.14;
                if($diff == 6)
                    $taux = 0.18;
                if($diff == 7)
                    $taux = 0.38;
                if($diff == 8)
                    $taux = 0.40;

            break;

            break;
            default: $taux = 0;
        }
        return $taux;
    }

    public function getChildrenNetworkBonusCalculate($disitributeurId, $taux)
    {
        $partTab = [];

        //$childinfos = Distributeur::join('levels', 'levels.distributeur_id', '=', 'distributeurs.distributeur_id')->get(['distributeurs.*', 'levels.*']);
        $childDistributeurs = Level_current_2024_01::where('id_distrib_parent', $disitributeurId)->get();

        foreach ($childDistributeurs as $key => $enfants) {

            $part = array(
                'distributeur_id' => $enfants->distributeur_id,
                'etoiles' => $enfants->etoiles,
                'taux' => $taux,
                'cumul_total' => $enfants->cumul_total,
                'part_parent' => ($enfants->cumul_total * $taux)
            );
            $partTab[] = $part;
            $partTab[] = $this->getChildrenNetworkBonusCalculate($enfants->distributeur_id, $taux);
        }

        return $partTab;
    }


    public function getPedegeeCumulPV($disitributeurId, $etoiles)
    {
        $children = [];
        global $stop_cumul;
        $childDistributeurs = Level_current_2024_02::where('id_distrib_parent', $disitributeurId)->get();

        foreach ($childDistributeurs as $child)
        {
            if($child->etoiles >= $etoiles){
                $stop_cumul = 'pas de bonus';
            }
            else{
                $stop_cumul = 'ça passe !';
            }
            $children[] = array(
                'stop_cumul' => $stop_cumul,
                'etoiles_enfants' => $child->etoiles,
                'etoiles_parent' => $etoiles,
                'child_distrib' => $child->distributeur_id,
            );
        }

        return $children;
        //return Arr::flatten($children);
    }


    public function getChild($disitributeurId, $etoiles)
    {
        $children = [];
        $childDistributeurs = Level_current_2024_02::where('id_distrib_parent', $disitributeurId)->get();

        foreach ($childDistributeurs as $child)
        {
            $children[] = array(
                //'etoiles' => $child->etoiles,
                'distributeur_id' => $child->distributeur_id,
            );
            //$children[] = $this->getChild($child->distributeur_id, $children);
        }

        return Arr::flatten($children);
    }



    public function bonusCalculate($element)
    {
        $pv = Distributeur::join('levels', 'levels.distributeur_id', '=', 'distributeurs.distributeur_id')->where('levels.distributeur_id', $element)->get(['distributeurs.*', 'levels.*']);
        return $pv[0]->new_cumul;
    }

    /* Fonction qui détermine l'élligibilité d'un distibuteur
      à toucher son bonus direct et indirect
     */
    public function isdirect($etoiles, $cumul)
    {

        switch($etoiles)
        {
            case 2 :
                $bonus =  6/100;
            break;
            case 3 :
                $bonus = ($cumul >= 10) ? true : false;
            break;
            case 4 :
                $bonus = ($cumul >= 20) ? true : false;
            break;
            case 5 :
                $bonus = ($cumul >= 40) ? true : false;
            break;
            case 6 :
                $bonus = ($cumul >= 60) ? true : false;
            break;
            case 7 :
                $bonus = ($cumul >= 100) ? true : false;
            break;
            case 8 :
                $bonus = ($cumul >= 150) ? true : false;
            break;
            case 9 :
                $bonus = ($cumul >= 180) ? true : false;
            break;
            case 10 :
                $bonus = ($cumul >= 180) ? true : false;
            break;
            default: $bonus = false;
        }
        return $bonus;
    }

    public function bonusIndirect($disitributeurId, $etoiles, $date)
    {
        $currentMonth = Carbon::parse($date)->format('m');
        $currentYear = Carbon::parse($date)->format('Y');
        $operand = [];
        $total = [];
        $cumul_total = 0;
        $children = $this->getSubdistribids($disitributeurId, $etoiles, $currentMonth, $currentYear);
        if(count($children) > 0)
        {
            foreach ($children as $key => $value) {
                $levelhistory = Level_History::where('distributeur_id', $value)
                ->whereMonth('level__histories.created_at', $currentMonth)
                ->whereYear('level__histories.created_at', $currentYear)
                ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') AS new_date")
                ->selectRaw("sum(cumul_total) as cumul_total, etoiles")
                ->selectRaw("sum(new_cumul) as new_cumul")
                ->selectRaw("sum(cumul_individuel) as cumul_individuel")
                ->selectRaw("sum(cumul_collectif) as cumul_collectif")
                ->groupBy('new_date')
                ->orderBy('new_date', 'ASC')
                ->groupBy('distributeur_id')
                ->get();

                $bonusIndirect[] = $levelhistory;
            }
            $bonusIndirect = Arr::flatten($bonusIndirect);
            foreach ($bonusIndirect as $key => $val) {
                switch($val->etoiles)
                {
                    case 2 :
                        $bonus =  $val->new_cumul * 6/100;
                        $cumul_total = $val->new_cumul;
                    break;
                    case 3 :
                        $bonus = $val->new_cumul  * 16/100;
                        $cumul_total = $val->new_cumul;
                    break;
                    case 4 :
                        $bonus = $val->new_cumul  * 4/100;
                        $cumul_total = $val->new_cumul;
                    break;
                    case 5 :
                        $bonus = $val->new_cumul  * 4/100;
                        $cumul_total = $val->new_cumul;
                    break;
                    case 6 :
                        $bonus = $val->new_cumul  * 4/100;
                        $cumul_total = $val->new_cumul;
                    break;
                    case 7 :
                        $bonus = $val->new_cumul  * 6/100;
                        $cumul_total = $val->new_cumul;
                    break;
                    case 8 :
                        $bonus = $val->new_cumul  * 3/100;
                        $cumul_total = $val->new_cumul;
                    break;
                    case 9 :
                        $bonus = $val->new_cumul  * 2/100;
                        $cumul_total = $val->new_cumul;
                    break;
                        $bonus = $val->new_cumul  * 2/100;
                        $cumul_total = $val->new_cumul;
                    break;
                    default: $bonus = 0; $cumul_total = $val->new_cumul;
                }
                $operand[] = $bonus;
                $total[] = $cumul_total;
            }
            return [$operand, $total];
        }
        else {
            return $children;
        }
    }

    public function getSubdistribids($disitributeurId, $etoiles, $currentMonth, $currentYear)
    {
        $children = [];
        $parentDistributeurs = Level_History::where('id_distrib_parent', $disitributeurId)
        ->whereMonth('level__histories.created_at', $currentMonth)
        ->whereYear('level__histories.created_at', $currentYear)/*
        ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') AS new_date")
        ->selectRaw("sum(cumul_total) as cumul_total, etoiles")
        ->selectRaw("sum(new_cumul) as new_cumul")
        ->selectRaw("sum(cumul_individuel) as cumul_individuel")
        ->selectRaw("sum(cumul_collectif) as cumul_collectif")
        ->groupBy('new_date')
        ->orderBy('new_date', 'ASC')*/
        ->groupBy('distributeur_id')
        ->get();

        //return $parentDistributeurs;
        $children[] = $disitributeurId;
        foreach ($parentDistributeurs as $parent)
        {
            if($etoiles > $parent->etoiles)
                $children[] = $this->getSubdistribids($parent->distributeur_id, $parent->etoiles, $currentMonth, $currentYear, $children);
        }
        return $children;
    }

        /*
        foreach($children as $key => $child)
        {
            $pv = Distributeur::join('levels', 'levels.distributeur_id', '=', 'distributeurs.distributeur_id')
            ->where('levels.distributeur_id', $child)
            ->get(['distributeurs.etoiles_id', 'levels.new_cumul']);
            return $pv[0]->etoiles_id;

            if(($pv[0]->etoiles_id != 0)&&($rang > $pv[0]->etoiles_id))
            {
                switch($pv[0]->etoiles_id)
                {
                    case 2 :
                        $bonus = ($pv[0]->new_cumul > 0) ? ($pv[0]->new_cumul * 6) / 100 : 0;
                    break;
                    case 3 :
                        $bonus = ($pv[0]->new_cumul > 0) ? ($pv[0]->new_cumul * 16) / 100 : 0;
                    break;
                    case 4 :
                        $bonus = ($pv[0]->new_cumul > 0) ? ($pv[0]->new_cumul * 4) / 100 : 0;
                    break;
                    case 5 :
                        $bonus = ($pv[0]->new_cumul > 0) ? ($pv[0]->new_cumul * 4) / 100 : 0;
                    break;
                    case 6 :
                        $bonus = ($pv[0]->new_cumul > 0) ? ($pv[0]->new_cumul * 4) / 100 : 0;
                    break;
                    case 7 :
                        $bonus = ($pv[0]->new_cumul > 0) ? ($pv[0]->new_cumul * 6) / 100 : 0;
                    break;
                    case 8 :
                        $bonus = ($pv[0]->new_cumul > 0) ? ($pv[0]->new_cumul * 3) / 100 : 0;
                    break;
                    case 9 :
                        $bonus = ($pv[0]->new_cumul > 0) ? ($pv[0]->new_cumul * 3) / 100 : 0;
                    break;
                    default: $bonus = 0;
                }
            }
        }*/


    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request)
    {
        //
        $level = $request->distributeur_id;
        global $total_direct, $total_indirect;
        global $numero;

        $infosparent = Distributeur::where('distributeur_id', $level)->first();
        $bonusInfos = Bonuse::orderBy('id', 'DESC')->first();

        if($bonusInfos != null)
        {
            $numero = $bonusInfos->num+1;
        }
        else {
            $numero = '77700304001';
        }

        $total_direct = (float)$request->direct;
        $total_indirect = (float)$request->indirect;

        $bonus = $total_indirect + $total_direct;
        $decimal = $bonus - floor($bonus);

        if($decimal > 0.5)
        {
            $bonus = floor($bonus);
        }
        else {

            $bonus = $bonus-1;
        }

        $distributeurs = array(
            'distributeur_id' => $infosparent->distributeur_id,
            'period' => '2024-02',
            'numero' => $numero,
            'nom_distributeur' => $infosparent->nom_distributeur,
            'pnom_distributeur' => $infosparent->pnom_distributeur,
            'bonus_direct' => $total_direct,
            'bonus_indirect' => $total_indirect,
            'bonus' => $bonus
        );

        $bonusInserted = new Bonuse();
        $bonusInserted->period = '2024-02';
        $bonusInserted->num = $numero;
        $bonusInserted->distribiteur_id = $level;
        $bonusInserted->bonus_direct = $total_direct;
        $bonusInserted->bonus_indirect = $total_indirect;
        $bonusInserted->bonus = $bonus;
        //$bonusInserted->bonus_leadership
        $bonusInserted->epargne = $decimal;
        $bonusInserted->save();

        return view('layouts.bonus.print', [
            "distributeurs" => $distributeurs
        ]);


        //
        // Liste des ID soumis pour l'établissement des bonus
        //$currentMonth = Carbon::parse($date)->format('m');
        //$currentYear = Carbon::parse($date)->format('Y');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
        return $request;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

}
