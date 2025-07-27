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
        $period = Bonuse::groupBy('period')->orderBy('period', 'desc')->get('period');

        //return $statistic;
        return view('layouts.bonus.index', [
            "bonus" => $bonusinfos,
            "statistic" => $statistic,
            "period" => $period
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        //
        $distributeurs = Distributeur::all();
        $period = Achat::groupBy('period')->get('period');

        return view('layouts.bonus.create', [
            "distributeurs" => $distributeurs,
            "period" => $period
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function CalculBonusCHildren($disitributeurId, $rang, $etoiles, $period)
    {
        $children = [];
        $bonus_calcul = [];
        $childDistributeurs = Level_current_test::where('id_distrib_parent', $disitributeurId)->where('period', $period)->get();

        $ischildDistributeurs = count($childDistributeurs);

        if($ischildDistributeurs > 0)
        {
            foreach ($childDistributeurs as $child)
            {

                $diff = $etoiles - $child->etoiles;
                $taux = $this->etoilesChecker($etoiles, $diff);
                $bonus[] = $child->cumul_total * $taux;

                if($etoiles > $child->etoiles)
                {

                    $bonus_calcul[] = $child->cumul_total * $taux;
                    $bonus_calcul[] = $this->CalculBonusCHildren($child->distributeur_id, $taux, $rang, $child->etoiles, $period);
                }
                else {

                    if($rang > $child->etoiles)
                    {
                        $diff = $etoiles - $child->etoiles;
                        $nvtaux = $this->etoilesChecker($etoiles, $diff);
                        $bonus_calcul[] = $child->cumul_total * $taux;
                        $bonus_calcul[] = $this->CalculBonusCHildren($child->distributeur_id, $nvtaux, $rang, $child->etoiles, $period);
                    }
                    else{
                        $bonus_calcul[] = 0;
                    }
                }
            }
        }
        /*
        $children[] = array(
            'distributeur_id' => $disitributeurId,
            'etoiles' => $etoiles,
            'bonus_calcul' => $bonus_calcul,
        );
        */
        return array_sum($bonus_calcul);
    }

    public function store(Request $request)
    {

        // ON RECUPERE LES DONNEES DU PARENT
        // ON CALCUL LES BONUS AUTOMATIQUEMENT

        $level = $request->distributeur_id;
        /** @var array $tauxDirect */
        global $tauxDirect, $bonusFinal, $total_direct, $total_indirect;
        $partTab = [];
        $partBonus = []; $tabfinal = []; $bonus = [];
        global $etoilesDiff;
        $period = $request->period;//
        $bonusTotal = 0;

        $distrib = Level_current_test::join('distributeurs', 'distributeurs.distributeur_id','=','level_current_tests.distributeur_id')
            ->where('level_current_tests.distributeur_id', $level)->where('level_current_tests.period', $period)->first(['level_current_tests.*','distributeurs.*']);

        $achatIsset = Achat::where('distributeur_id', $level)->where('period', $period)->first();
        if($achatIsset == null)
        {
            flash(message: 'Le Distributeur n\'a pas effectué d\'achats')->success();
            return back();
        }

        //return $distrib;etoilesChecker($etoiles, $diff)
        //$cumul_total_reste = $distrib->cumul_total;
        //return [$distrib->etoiles, $distrib->new_cumul];
        $eligible = $this->isBonusEligible($distrib->etoiles, $distrib->new_cumul);

        if(!$eligible[0])
        {
            $distributeurs = array(
                'duplicata' => false,
                'distributeur_id' => $distrib->distributeur_id,
                'nom_distributeur' => $distrib->nom_distributeur,
                'pnom_distributeur' => $distrib->pnom_distributeur,
                'new_cumul' => $distrib->new_cumul,
                'period' => $period,
                'numero' => 'non élligible',
                'etoiles' => $distrib->etoiles,
                'bonus_direct' => 0,
                'bonus_indirect' => 0,
                'bonus' => 0,
                'quota' => $eligible[1],
                'bonusFinal' => '0'
            );

            return view('layouts.bonus.printnon', [
                "distributeurs" => $distributeurs
            ]);
        }
        else
        {
            if($distrib->etoiles <= 1)
            {
                flash(message: 'Le Distributeur de rang 1 ne sont pas eligible')->success();
                return back();
            }
            else {

                $firstGenealogie = Level_current_test::where('id_distrib_parent', $level)->where('period', $period)->get();
                $tauxDirect = $this->tauxDirectCalculator($distrib->etoiles);
                $bonusDirect = $distrib->new_cumul * $tauxDirect;
                $isfirstGenealogie = count($firstGenealogie);

                if($isfirstGenealogie > 0)
                {
                    foreach ($firstGenealogie as $value) {

                        if($distrib->etoiles > $value->etoiles)
                        {

                            $diff = $distrib->etoiles - $value->etoiles;
                            $taux = $this->etoilesChecker($distrib->etoiles, $diff);
                            $bonus[] = $value->cumul_total * $taux;
                            //$result[] = $this->CalculBonusCHildren($distrib->distributeur_id, $distrib->etoiles, $distrib->etoiles, $period);
                        }
                    }
                    /*
                    foreach ($firstGenealogie as $value) {

                            $rang = $distrib->etoiles;

                            $bonus[] = $value->cumul_total * $taux;
                            $apercu[] = array(
                                'distributeur_id' => $value->distributeur_id,
                                'id_distrib_parent' => $distrib->distributeur_id,
                                'period' => $period,
                                'etoilesP vs etoilesF' => $distrib->etoiles.' vs '.$value->etoiles,
                                'bonus_indirect' => $value->cumul_total.' * '.$taux,
                                'child' => $this->getChildEtoiles($value->distributeur_id, $value->cumul_total, $rang, $value->etoiles, $taux, $bonus, $period)
                            );
                        }
                        else {
                            $child[] = 0;
                            $bonus[] = 0;
                        }
                    }
                    return $apercu;
                    //$bonusIndirect = array_sum($child);
                    $bonusIndirect = array_sum($bonus);
                    */
                }
                else {
                    $bonus[] = 0;
                    $result[] = 0;
                }
            }
            //$bonusIndirect = (array_sum($bonus) + array_sum($result));
            //return $bonus;
            $bonusIndirect = array_sum($bonus);
            //return [$bonusIndirect, $bonus];
            $bonusInfos = Bonuse::latest()->first();
            $bonusIsset = Bonuse::where('distributeur_id', $distrib->distributeur_id)->where('period', $period)->first();

            if($bonusIsset)
            {
                flash(message: 'Le Distributeur a déjà touché son bonus')->success();
                return back();
            }

            if($bonusInfos != null)
            {
                $numero = $bonusInfos->num+1;
            }
            else {
                $numero = '77700304001';
            }

            $bonus = $bonusDirect + $bonusIndirect;
            $decimal = $bonus - floor($bonus);

            if($decimal > 0.5)
            {
                $bonusFinal = floor($bonus);
                $epargne = $decimal;
            }
            else {
                $bonusFinal = $bonus;

                if($bonusFinal > 1)
                {
                    $bonusFinal = $bonusFinal-1;
                    $epargne = 1;
                }
                else {
                    $bonusFinal = $bonusFinal;
                    $epargne = 0;
                }
            }

            $distributeurs = array(
                'duplicata' => false,
                'distributeur_id' => $distrib->distributeur_id,
                'nom_distributeur' => $distrib->nom_distributeur,
                'pnom_distributeur' => $distrib->pnom_distributeur,
                'period' => $period,
                'numero' => $numero,
                'etoiles' => $distrib->etoiles,
                'bonus_direct' => $bonusDirect,
                'bonus_indirect' => $bonusIndirect,
                'bonus' => $bonus,
                'bonusFinal' => $bonusFinal,
                'epargne' => $epargne
            );

            //return $distributeurs;
            $bonusInserted = new Bonuse();
            $bonusInserted->period = $period;
            $bonusInserted->num = $numero;
            $bonusInserted->distributeur_id = $distrib->distributeur_id;
            $bonusInserted->bonus_direct = $bonusDirect;
            $bonusInserted->bonus_indirect = $bonusIndirect;
            $bonusInserted->bonus = $bonusFinal;
            //$bonusInserted->bonus_leadership
            $bonusInserted->epargne = $epargne;
            $bonusInserted->save();

            return view('layouts.bonus.print', [
                "distributeurs" => $distributeurs
            ]);

        }

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

    public function tauxDirectCalculator($etoiles)
    {
        switch($etoiles)
        {
            case 1: $taux_dir = 0;
            break;
            case 2: $taux_dir = 6/100;
            break;
            case 3: $taux_dir = 22/100;
            break;
            case 4: $taux_dir = 26/100;
            break;
            case 5: $taux_dir = 30/100;
            break;
            case 6: $taux_dir = 34/100;
            break;
            case 7: $taux_dir = 40/100;
            break;
            case 8: $taux_dir = 43/100;
            break;
            case 9: $taux_dir = 45/100;
            break;
            case 10: $taux_dir = 45/100;
            break;
        }
        return $taux_dir;
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

    public function show(String $id, Request $request)
    {
        $distrib = Distributeur::where('distributeur_id', $id)->first();
        $bonus = Bonuse::where('distributeur_id', $id)->where('period', $request->period)->first();

        $distributeurs = array(
            'duplicata' => true,
            'distributeur_id' => $distrib->distributeur_id,
            'nom_distributeur' => $distrib->nom_distributeur,
            'pnom_distributeur' => $distrib->pnom_distributeur,
            'period' => $request->period,
            'numero' => $bonus->num,
            'etoiles' => $distrib->etoiles_id,
            'bonus_direct' => $bonus->bonus_direct,
            'bonus_indirect' => $bonus->bonus_indirect,
            'bonus' => $bonus->bonus,
            'bonusFinal' => $bonus->bonus,
            'epargne' => $bonus->epargne
        );

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
                $taux = 0.06;
            break;
            case 3 :

                if($diff <= 0)
                    $taux = 0;
                if($diff == 1)
                    $taux = 0.16;
                if($diff == 2)
                    $taux = 0.22;
            break;
            case 4 :

                if($diff <= 0)
                    $taux = 0;
                if($diff == 1)
                    $taux = 0.04;
                if($diff == 2)
                    $taux = 0.20;
                if($diff == 3)
                    $taux = 0.26;
            break;
            case 5 :

                if($diff <= 0)
                    $taux = 0;
                if($diff == 1)
                    $taux = 0.04;
                if($diff == 2)
                    $taux = 0.08;
                if($diff == 3)
                    $taux = 0.24;
                if($diff == 4)
                    $taux = 0.30;
            break;
            case 6 :

                if($diff <= 0)
                    $taux = 0;
                if($diff == 1)
                    $taux = 0.04;
                if($diff == 2)
                    $taux = 0.08;
                if($diff == 3)
                    $taux = 0.12;
                if($diff == 4)
                    $taux = 0.28;
                if($diff == 5)
                    $taux = 0.34;
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
                    $taux = 0.34;
                if($diff == 6)
                    $taux = 0.40;
            break;
            case 8 :

                if($diff == 0)
                    $taux = 0;
                if($diff == 1)
                    $taux = 0.03;
                if($diff == 2)
                    $taux = 0.09;
                if($diff == 3)
                    $taux = 0.13;
                if($diff == 4)
                    $taux = 0.17;
                if($diff == 5)
                    $taux = 0.21;
                if($diff == 6)
                    $taux = 0.37;
                if($diff == 7)
                    $taux = 0.43;

            break;
            case 9 :

                if($diff == 0)
                    $taux = 0;
                if($diff == 1)
                    $taux = 0.02;
                if($diff == 2)
                    $taux = 0.05;
                if($diff == 3)
                    $taux = 0.11;
                if($diff == 4)
                    $taux = 0.15;
                if($diff == 5)
                    $taux = 0.19;
                if($diff == 6)
                    $taux = 0.23;
                if($diff == 7)
                    $taux = 0.39;
                if($diff == 8)
                    $taux = 0.45;
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

    public function getChildEtoiles($disitributeurId, $cumul_total, $rang, $etoiles, $taux, $bonus_child, $period)
    {
        $children = [];
        $bonus_cumul = [];
        $children[] = $disitributeurId;
        $parentDistributeurs = Level_current_test::where('id_distrib_parent', $disitributeurId)->where('period', $period)->get();

        foreach ($parentDistributeurs as $child)
        {
            $bonus_calcul = $child->cumul_total * $taux;
            $children[] = array(
                'distributeur_id' => $child->distributeur_id,
                'etoiles_parent' => $etoiles,
                'etoiles' => $child->etoiles,
                'cumul_total' => $child->cumul_total,
                'bonus_calcul' => $bonus_calcul,
                'child' => $this->getChildEtoiles($child->distributeur_id, $cumul_total, $rang, $etoiles, $period, $taux, $bonus_calcul, $children)
            );
            /*
            if($etoiles >= $child->etoiles)
            {
                $bonus_calcul = $child->cumul_total * $taux;
                $bonus_cumul[] = $bonus_calcul;

            }
            else {
                if($rang >= $child->etoiles)
                {
                    $diff = $rang - $child->etoiles;
                    $taux = $this->etoilesChecker($child->etoiles, $diff);
                    $bonus_cumul[] = $child->cumul_total * $taux;
                }
                else {
                    $bonus_cumul[] = 0;
                }
            }
            */
        }
        //return array_sum($bonus_cumul);
        return $children;
    }

    /*
    public function getChildEtoiles($disitributeurId, $cumul_reste, $etoiles)
    {
        $children = [];
        $childDistributeurs = Level_current_2024_02::where('id_distrib_parent', $disitributeurId)->get();

        foreach ($childDistributeurs as $child)
        {
            if($etoiles > $child->etoiles)
            {
                $children[] = array(
                    'distributeur_id' => $child->distributeur_id,
                    'etoiles_parain' => $etoiles,
                    'etoiles' => $child->etoiles,
                    'cumul_total_fieuil' => $child->cumul_total
                );

                $children[] = $this->getChildEtoiles($child->distributeur_id, $child->etoiles, $children);
            }
            else {
                $cumul_reste = $cumul_reste - $child->cumul_total;
                $children[] = array(
                    'PROGESSION BLOQUEE' => 'TRUE',
                    'distributeur_id' => $child->distributeur_id,
                    'etoiles_parain' => $etoiles,
                    'etoiles' => $child->etoiles,
                    'cumul_total_fieuil' => $child->cumul_total
                );
            }
        }

        return $children; //Arr::flatten($children);
    }
    */


    public function bonusCalculate($element)
    {
        $pv = Distributeur::join('levels', 'levels.distributeur_id', '=', 'distributeurs.distributeur_id')->where('levels.distributeur_id', $element)->get(['distributeurs.*', 'levels.*']);
        return $pv[0]->new_cumul;
    }

    /* Fonction qui détermine l'élligibilité d'un distibuteur
      à toucher son bonus direct et indirect
     */
    public function isBonusEligible($etoiles, $cumul)
    {
        switch($etoiles)
        {
            case 1 :
                $bonus = false;
                $quota = 0;
            case 2 :
                $bonus = true;
                $quota = 0;
            break;
            case 3 :
                $bonus = ($cumul >= 10) ? true : false;
                $quota = 10;
            break;
            case 4 :
                $bonus = ($cumul >= 15) ? true : false;
                $quota = 15;
            break;
            case 5 :
                $bonus = ($cumul >= 30) ? true : false;
                $quota = 30;
            break;
            case 6 :
                $bonus = ($cumul >= 50) ? true : false;
                $quota = 50;
            break;
            case 7 :
                $bonus = ($cumul >= 100) ? true : false;
                $quota = 100;
            break;
            case 8 :
                $bonus = ($cumul >= 150) ? true : false;
                $quota = 150;
            break;
            case 9 :
                $bonus = ($cumul >= 180) ? true : false;
                $quota = 180;
            break;
            case 10 :
                $bonus = ($cumul >= 180) ? true : false;
                $quota = 180;
            break;
            default: $bonus = false; $quota = 0;
        }
        return [$bonus, $quota];
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

        if($decimal > 0.50)
        {
            $bonusFinal = floor($bonus);
        }
        else {
            $bonusFinal = $bonus-1;
        }

        $distributeurs = array(
            'duplicata' => false,
            'distributeur_id' => $infosparent->distributeur_id,
            'period' => '2024-02',
            'numero' => $numero,
            'nom_distributeur' => $infosparent->nom_distributeur,
            'pnom_distributeur' => $infosparent->pnom_distributeur,
            'bonus_direct' => $total_direct,
            'bonus_indirect' => $total_indirect,
            'bonus' => $bonus,
            'bonusFinal' => $bonusFinal
        );

        $bonusInserted = new Bonuse();
        $bonusInserted->period = '2024-02';
        $bonusInserted->num = $numero;
        $bonusInserted->distributeur_id = $level;
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
