<?php

namespace App\Http\Controllers;

use App\Models\Achat;
use App\Models\Distributeur;
use App\Models\Level;
use App\Models\Level_current;
use App\Models\level_current_11;
use App\Models\Level_current_12;
use App\Models\Level_current_2023_11;
use App\Models\Level_current_2023_12;
use App\Models\Level_current_2024_01;
use App\Models\Level_current_2024_02;
use App\Models\Level_current_test;
use App\Models\Level_History;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use DB;
use Locale;

class InsertDistributeurs extends Controller
{
    /**
     * Display a listing of the resource.
     */
    function array_flatten($array = null) {
        $result = array();

        if (!is_array($array)) {
            $array = func_get_args();
        }

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $result = array_merge($result, $this->array_flatten($value));
            } else {
                $result = array_merge($result, array($key => $value));
            }
        }

        return $result;
    }

    public function etoilesChecker($etoiles, $diff)
    {
        switch($etoiles)
        {
            case 1 :
            break;
            case 3 :

            break;
            case 4 :

            break;
            case 5 :

            break;
            case 6 :

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

            break;
            case 9 :

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

    public function getChild($disitributeurId)
    {
        $childDistributeurs = Level::where('id_distrib_parent', $disitributeurId)->get();
        foreach ($childDistributeurs as $child)
        {
            $children[] = array(
                'distributeur_id' => $child->distributeur_id,
            );
        }
        return Arr::flatten($children);
    }

    public function getChildrenNetwork($disitributeurId, $level)
    {
        $children = [];
        //$childinfos = Distributeur::join('levels', 'levels.distributeur_id', '=', 'distributeurs.distributeur_id')->get(['distributeurs.*', 'levels.*']);
        $childDistributeurs = Level::where('id_distrib_parent', $disitributeurId)->get();

        foreach ($childDistributeurs as $child)
        {
            $temp[] = array(
                'tour' => $level,
                'id' => $child->distributeur_id,
            );
            $children[] = $this->array_flatten($temp);
            $children[] = $this->getChildrenNetwork($child->distributeur_id, $level, $children);
        }
        return $children; //Arr::flatten($);
    }

    public function getParentNetwork($parent, $cumul)
    {
         $children = [];

        //$childinfos = Distributeur::join('levels', 'levels.distributeur_id', '=', 'distributeurs.distributeur_id')->get(['distributeurs.*', 'levels.*']);
        $childDistributeurs = Level_current_2024_02::where('distributeur_id', $parent)->get();

        foreach ($childDistributeurs as $child)
        {

            $achatsInsert = Level_current_2024_02::where('distributeur_id', $child->distributeur_id)->first();
            $achatsInsert->cumul_total = $child->cumul_total + $cumul;
            $achatsInsert->cumul_collectif = $child->cumul_collectif + $cumul;
            $achatsInsert->update();

            $children[] = array(
                'id' => $child->distributeur_id,
                'cumul' => $cumul,
                'cumul_total' => $child->cumul_total + $cumul,
                'cumul_collectif' =>  $child->cumul_collectif + $cumul
            );

            $children[] = $this->getParentNetwork($child->id_distrib_parent, $cumul);
        }
        return Arr::flatten($children);
    }

    public function comparatifTab($db, $dbRecup, $period)
    {
        $tab = [];
        $comparatif = $db::where('period', $period)->get();

        foreach ($comparatif as $key => $value) {
            $tab[] = $value->distributeur_id;
        }

        $level_comp = $dbRecup::whereNotIn('distributeur_id', $tab)->get();

        return $level_comp;
        foreach ($level_comp as $key => $value) {

            try {

                $db::insert([
                    'distributeur_id' => $value->distributeur_id,
                    'rang' => $value->rang,
                    'period' => $period,
                    'etoiles' => $value->etoiles_id,
                    'cumul_individuel' => 0,
                    'new_cumul' => 0,
                    'cumul_total' => 0,
                    'cumul_collectif' => 0,
                    'id_distrib_parent' => $value->id_distrib_parent,
                    'created_at' => $value->created_at,
                    'updated_at' => $value->updated_at
                ]);
            }
            catch(\Illuminate\Database\QueryException $exept){
                dd($exept->getMessage());
            }
        }

        return 'Insetion réussi !';
    }

    //FONCTION QUI SE CHARGE DE PRENDRE LES ACHATS DE LA TABLE "Achats"
    //ET LES INSERER DANS LA TABLE "Level_current_tests"

    public function insertAchatInLevelCurrent($achatsAll, $period){

        $achatsAll = Arr::flatten($achatsAll);

        //return $achatsAll;
        foreach ($achatsAll as $key => $achat) {

            $levelCurrent = Level_current_2024_02::where('distributeur_id', $achat->distributeur_id)->first();
            $levelCurrentTab = array(
                'id' => $levelCurrent->id,
                'rang' => $levelCurrent->rang,
                'period' => $period,
                'distributeur_id' => $levelCurrent->distributeur_id,
                'etoiles' => $levelCurrent->etoiles,
                'cumul_individuel' => $achat->cumul_individuel,
                'new_cumul' => $levelCurrent->new_cumul + $achat->new_cumul,
                'cumul_total' => $levelCurrent->cumul_total + $achat->new_cumul,
                'cumul_collectif' => $levelCurrent->cumul_collectif + $achat->new_cumul,
                'id_distrib_parent' => $levelCurrent->id_distrib_parent,
                'created_at' => $levelCurrent->created_at,
                'updated_at' => $levelCurrent->updated_at,
            );

            $reportTab[] = $levelCurrentTab;

            $levelCurrent->period = $period;
            $levelCurrent->new_cumul = $achat->new_cumul;
            $levelCurrent->cumul_total = $achat->new_cumul;
            $levelCurrent->cumul_collectif = $levelCurrent->cumul_collectif + $achat->new_cumul;
            $levelCurrent->update();

            echo 'Achats insérés avec succès réussie<br/>';

        }
        //return $reportTab;
    }

    public function addCumulToParains($id)
    {
        $achatsAll = Achat::selectRaw("distributeur_id, id_distrib_parent")
            //->where('distributeur_id', $id)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') AS new_date")
            ->selectRaw("sum(pointvaleur) as new_cumul")
            ->groupBy('distributeur_id')
            //->limit(10)
            ->get();

        return $achatsAll;
        //return $levelCurrent;
        foreach ($achatsAll as $key => $achats) {

            if( $achats != null)
            {
                $pedigre[] = $this->getParentNetwork($achats->id_distrib_parent, $achats->new_cumul);
            }
        }
        return $pedigre;

        if($pedigre < 0)
        {
            return 'pas de parrain';
        }

        $pedigreResult[0] = Arr::flatten($pedigre);

        if (in_array(0, $pedigreResult)) {

            $element = 0;
            unset($pedigreResult[array_search($element, $pedigreResult)]);
        }
        foreach ($pedigreResult as $value) {

            $levelCurrentTest = Level_current_2024_02::where('distributeur_id', $value)->first();

            if( $levelCurrentTest != null)
            {
                $cumul_total = $levelCurrentTest->cumul_total + $achatsAll[0]->new_cumul;
                $cumul_collectif = $levelCurrentTest->cumul_collectif + $achatsAll[0]->new_cumul;

                $pedigreTab[] = array(
                    'distributeur_id' => $levelCurrentTest->distributeur_id,
                    'cumul_total' => $cumul_total,
                    'cumul_total' => $cumul_collectif
                );
            }
        }
    }

    public function index()
    {

        $distribCurrent = [];
        $pedigre = [];
        $currents = [];
        $id = '2217001';
        $mois = '%2024-02%';
        $period = '2024-02';
        $db = 'App\Models\Level_current_2024_02';

        $achatsAll = Achat::selectRaw("distributeur_id, id_distrib_parent")
            //->where('distributeur_id', $id)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') AS new_date")
            ->selectRaw("sum(pointvaleur) as new_cumul")
            ->groupBy('distributeur_id')
            //->limit(10)
            ->get();

        //return $achatsAll; //[0]->distributeur_id;
        //return $levelCurrent;
        //on récupère les données dans la base Achats

        //on récupère les achats effectués à une periode donnés
        //et on l'insere dans la base : Level_current_tests
/*
        $insertAchats = $this->insertAchatInLevelCurrent($achatsAll, $period);
        return $insertAchats;
/*
        $addCumul = $this->addCumulToParains($id);
        return $addCumul;

/*
        $db = 'App\Models\Level_current_2024_01';
        $dbRecup = 'App\Models\Distributeur';
        $period = '2024-01';
        $result = $this->comparatifTab($db, $dbRecup, $period);
        return $result;
/*
        foreach ($result as $key => $line) {
            $period =  Carbon::parse($line->created_at)->format('Y-m');
            Level::updateOrInsert(
                ['distributeur_id' => $line->distributeur_id],
                [
                    'etoiles' => $line->etoiles_id,
                    'rang' => $line->rang,
                    'period' => $period,
                    'new_cumul' => 0,
                    'cumul_total' => 0,
                    'cumul_collectif' => 0,
                    'id_distrib_parent' => $line->id_distrib_parent,
                    'created_at' => Carbon::parse($line->created_at),
                    'updated_at' => Carbon::parse($line->updated_at)
                ]
            );
            Level_current::updateOrInsert(
                ['distributeur_id' => $line->distributeur_id],
                [
                    'etoiles' => $line->etoiles_id,
                    'rang' => $line->rang,
                    'period' => $period,
                    'new_cumul' => 0,
                    'cumul_total' => 0,
                    'cumul_collectif' => 0,
                    'id_distrib_parent' => $line->id_distrib_parent,
                    'created_at' => Carbon::parse($line->created_at),
                    'updated_at' => Carbon::parse($line->updated_at)
                ]
            );
            Level_History::updateOrInsert(
                ['distributeur_id' => $line->distributeur_id],
                [
                    'etoiles' => $line->etoiles_id,
                    'rang' => $line->rang,
                    'period' => $period,
                    'new_cumul' => 0,
                    'cumul_total' => 0,
                    'cumul_collectif' => 0,
                    'id_distrib_parent' => $line->id_distrib_parent,
                    'created_at' => Carbon::parse($line->created_at),
                    'updated_at' => Carbon::parse($line->updated_at)
                ]
            );
        }*/


                //->where('period', $achat->period)
                //->selectRaw("rang, distributeur_id, etoiles, cumul_individuel, new_cumul, cumul_total, cumul_collectif, id_distrib_parent")
                //->where('created_at', 'LIKE', "%$achat->new_date%")
                //->where('distributeur_id', $achat->distributeur_id)
                //->where('created_at', 'LIKE' ,$achat->new_date)

    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {

        //$total = $achats;
        //return $equals;
        //FICHIER DEJA CONTENU DANS LA BASE DE DONNE
        //1) 6686147.txt
        //1) 6687744.txt

        //Lister tout
/**/

                $scandir = scandir("./assets/dossier/2024-01");
                $period = '2024-01';
                $debut = '2024-01';
                foreach($scandir as $fichier){

            if ($fichier !== '.' && $fichier !== '..') {

                $res = fopen('./assets/dossier/2024-01/'.$fichier, 'rb');
                $final = $this->UpdateData($res);
                //return $final;

                //$date =  Carbon::createFromFormat('Y-m', $debut)->format('d-m-Y');
                //if(isset($request->created_at)) $levels->created_at = Carbon::parse($RequestDate)->month;

                foreach ($final as $value) {

/*
                    $levelCurrent = Level_current_2023_11::where('distributeur_id', $value['distributeur_id'])->first();

                    $levelCurrent->distributeur_id = $value['distributeur_id'];
                    $levelCurrent->etoiles = $value['etoiles_id'];
                    $levelCurrent->rang = $value['rang'];
                    $levelCurrent->period = $period;
                    $levelCurrent->new_cumul = $value['new_cumul'];
                    $levelCurrent->cumul_total = $value['cumul_total'];
                    $levelCurrent->cumul_collectif = $value['cumul_collectif'];
                    $levelCurrent->id_distrib_parent = $value['id_distrib_parent'];
                    $levelCurrent->created_at = Carbon::parse($debut);
                    $levelCurrent->update();

                    //SELECT * FROM `level_current_tests` WHERE period='2024-01' ORDER BY `level_current_tests`.`period` DESC;

                    //Level_current_2023_11::firstOrCreate(
/*/
                    Level_current_2024_01::updateOrInsert(
                        ['distributeur_id' => $value['distributeur_id']],
                        [
                            'etoiles' => $value['etoiles'],
                            'rang' => $value['rang'],
                            'period' => $period,
                            'new_cumul' => $value['new_cumul'],
                            'cumul_total' => $value['cumul_total'],
                            'cumul_collectif' => $value['cumul_collectif'],
                            'id_distrib_parent' => $value['id_distrib_parent'],
                            'created_at' => Carbon::parse($debut)
                        ]
                    );

                    /**M
                        $level_history = new Level_current_test();
                        $level_history->distributeur_id = $value['distributeur_id'];
                        $level_history->rang = $value['rang'];
                        $level_history->period = $period;
                        $level_history->etoiles = $value['etoiles'];
                        $level_history->new_cumul = $value['new_cumul'];
                        $level_history->cumul_total = $value['cumul_total'];
                        $level_history->cumul_collectif = $value['cumul_collectif'];
                        $level_history->id_distrib_parent = $value['id_distrib_parent'];
                        $level_history->created_at = Carbon::parse($period);
                        $level_history->save();
/*
                    Level::updateOrInsert(
                        ['distributeur_id' => $value['distributeur_id']],
                        [
                            'etoiles' => $value['etoiles'],
                            'rang' => $value['rang'],
                            'period' => $period,
                            'new_cumul' => $value['new_cumul'],
                            'cumul_total' => $value['cumul_total'],
                            'cumul_collectif' => $value['cumul_collectif'],
                            'id_distrib_parent' => $value['id_distrib_parent'],
                            'created_at' => Carbon::parse($period)
                        ]
                    );
                    Distributeur::updateOrInsert(
                        ['distributeur_id' => $value['distributeur_id']],
                        [
                            'etoiles_id' => $value['etoiles'],
                            'rang' => $value['rang'],
                            'nom_distributeur' => $value['nom_distributeur'],
                            'pnom_distributeur' => $value['pnom_distributeur'],
                            'id_distrib_parent' => $value['id_distrib_parent'],
                            'created_at' => Carbon::parse($date)
                        ]
                    );

                    Level_current::updateOrInsert(
                        ['distributeur_id' => $value['distributeur_id']],
                        [
                            'etoiles' => $value['etoiles'],
                            'rang' => $value['rang'],
                            'period' => $period,
                            'new_cumul' => $value['new_cumul'],
                            'cumul_total' => $value['cumul_total'],
                            'cumul_collectif' => $value['cumul_collectif'],
                            'id_distrib_parent' => $value['id_distrib_parent'],
                            'created_at' => Carbon::parse($period)
                        ]
                    );
                    /*

                    $level_history = new Level_History();
                    $level_history->distributeur_id = $value['distributeur_id'];
                    $level_history->rang = $value['rang'];
                    $level_history->period = $period;
                    $level_history->etoiles = $value['etoiles'];
                    $level_history->new_cumul = $value['new_cumul'];
                    $level_history->cumul_total = $value['cumul_total'];
                    $level_history->cumul_collectif = $value['cumul_collectif'];
                    $level_history->id_distrib_parent = $value['id_distrib_parent'];
                    $level_history->created_at = Carbon::parse($date);
                    $level_history->save();*/
                }
                echo 'Fichier inséré : '.$fichier.'<br/>';
            }
        }
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
        $level = '6686147';
        global $etoilesDiff;
        $bonus = 0;
        $bonusTotal = 0;
        $infosparent = Level_current_2024_01::where('distributeur_id', $level)->first();

        $genealogie = $this->getChild($level);
        return $genealogie;
        $nbChild = count($genealogie);

        foreach ($genealogie as $key => $enfants) {

            $childs = Level_current_2024_01::where('distributeur_id', $enfants)->first();
            if($childs->new_cumuul)
            {

            }
            $etoilesDiff = $infosparent->etoiles - $childs->etoiles;
            //return $etoilesDiff.' = '.$infosparent->etoiles.' - '.$childs->etoiles;

            $taux = $this->etoilesChecker($infosparent->etoiles, $etoilesDiff);

            $part = array(
                'distributeur_id' => $childs->distributeur_id,
                'etoiles' => $childs->etoiles,
                'taux' => $taux,
                'cumul_total' => $childs->cumul_total,
                'part_parent' => ($childs->cumul_total * $taux)
            );
            $partTab[] = $part;
            $partTab[] = $this->getChildrenNetworkBonusCalculate($childs->distributeur_id, $taux);
        }
        //return $partTab;
        $partBonus = Arr::flatten($partTab) ;
        //return count($partBonus); 3758
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

        return $bonusTotal;

        return 'STOP';

        Level_current_2024_01::where('distributeur_id', '6686147')->first();

        $this->getChildrenNetworkBonusCalculate($level->distributeur_id, $level->etoiles);

        return $genealogie;
        //$flattenTree = Arr::flatten($genealogie);
        /*
        for($i=0; $i < count($flattenTree); $i++)
        {
            if($i%2 == 0){
                $j = $i+1;
                if($j >= count($flattenTree))
                {
                    $j = $i;
                }
                else{
                    $final[] = array(
                        'niveau' => $flattenTree[$i],
                        'distributeur_id' => $flattenTree[$j]
                    );
                }
            }
        }*/
        return $final;
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

    public function UpdateData($files)
    {

        $res = $files;
        //$no_parent = Distributeur::where('id_distrib_parent', 0)->get('distributeur_id');
        $i=0;
        while(!feof($res)){
            //dd($res);
            $ligne = fgets($res);
            preg_match("/[0-9]{7}(.*?)[0-9]{7}/", $ligne, $match);

            $tabs[] = explode(' ', array_key_exists($i, $match) ? $match[$i] : null);
        }


        foreach($tabs as $key => $value){
            switch(count($value))
            {
                case 8:
                $final[] = array(
                    'count' => count($value),
                    'distributeur_id' => $value[0],
                    'rang' => $value[1],
                    'nom_distributeur' => $value[2],
                    'pnom_distributeur' => '',
                    'etoiles' => $value[3],
                    'new_cumul' => str_replace([",","$"], "", $value[4]),
                    'cumul_total' => str_replace([",","$"], "", $value[5]),
                    'cumul_collectif' => str_replace([",","$"], "", $value[6]),
                    'id_distrib_parent' => $value[7]
                );
                break;
                case 9:
                $final[] = array(
                    'count' => count($value),
                    'distributeur_id' => $value[0],
                    'rang' => $value[1],
                    'nom_distributeur' => $value[2],
                    'pnom_distributeur' => $value[3],
                    'etoiles' => $value[4],
                    'new_cumul' => str_replace([",","$"], "", $value[5]),
                    'cumul_total' => str_replace([",","$"], "", $value[6]),
                    'cumul_collectif' => str_replace([",","$"], "", $value[7]),
                    'id_distrib_parent' => $value[8]
                );
                break;
                case 10:
                    $final[] = array(
                        'count' => count($value),
                        'distributeur_id' => $value[0],
                        'rang' => $value[1],
                        'nom_distributeur' => $value[2],
                        'pnom_distributeur' => $value[3].' '.$value[4],
                        'etoiles' => $value[5],
                        'new_cumul' => str_replace([",","$"], "", $value[6]),
                        'cumul_total' => str_replace([",","$"], "", $value[7]),
                        'cumul_collectif' => str_replace([",","$"], "", $value[8]),
                        'id_distrib_parent' => $value[9]
                    );
                break;
                case 11:
                    $final[] = array(
                        'count' => count($value),
                        'distributeur_id' => $value[0],
                        'rang' => $value[1],
                        'nom_distributeur' => $value[2].' '.$value[3],
                        'pnom_distributeur' => $value[4].' '.$value[5],
                        'etoiles' => $value[6],
                        'new_cumul' => str_replace([",","$"], "", $value[7]),
                        'cumul_total' => str_replace([",","$"], "", $value[8]),
                        'cumul_collectif' => str_replace([",","$"], "", $value[9]),
                        'id_distrib_parent' => $value[10]
                    );
                break;
                case 12:
                $final[] = array(
                    'count' => count($value),
                    'distributeur_id' => $value[0],
                    'rang' => $value[1],
                    'nom_distributeur' => $value[2],
                    'pnom_distributeur' => '',
                    'etoiles' => $value[3],
                    'new_cumul' => str_replace([",","$"], "", $value[4]),
                    'cumul_total' => str_replace([",","$"], "", $value[5]),
                    'cumul_collectif' => str_replace([",","$"], "", $value[6]),
                    'id_distrib_parent' => $value[7]
                );
                break;
                default:;
            }
        }

        return $final;
    }
}
