<?php

namespace App\Http\Controllers;

use App\Models\Achat;
use App\Models\Distributeur;
use App\Models\Distributor;
use App\Models\Etoile;
use App\Models\Level_current_test;
use App\Services\EternalHelperLegacyMatriculeDB;
use App\Services\DistributorRankService;
use App\Services\GradeCalculator;
use App\Services\EternalHelper;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;


class debugageController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        /////////////
        /*
        $scandir = scandir("./assets/dossier/2024-01");
                $period = '2024-02';
                $debut = '2024-02';
                foreach($scandir as $fichier){

            if ($fichier !== '.' && $fichier !== '..') {

                $res = fopen('./assets/dossier/2024-01/'.$fichier, 'rb');
                $final = $this->UpdateData($res);
                //return $final;

                foreach ($final as $value) {

                    $insertModif = Level_current_2024_02::where('distributeur_id', $value['distributeur_id'])->first();

                        $insertModif->distributeur_id = $value['distributeur_id'];
                        $insertModif->period = $period;
                        $insertModif->etoiles = $value['etoiles'];
                        $insertModif->rang = $value['rang'];
                        $insertModif->new_cumul = 0; //$achats->new_cumul;
                        $insertModif->cumul_total = 0 ; //$achats->new_cumul;
                        $insertModif->cumul_collectif = $value['cumul_collectif'];
                        $insertModif->id_distrib_parent = $value['id_distrib_parent'];
                        $insertModif->created_at = Carbon::parse($debut);
                        $insertModif->update();
                }

                echo 'Fichier inséré : '.$fichier.'<br/>';
            }

        }*/
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $level = Level_current_test::where('id_distrib_parent', 3315206)->where('period', '2024-11')->get();
        return $level;
        //CALCUL DU CUMUL INDIVIDUEL AU CAS PAR CAS
        /**/
        //$dbRecup = Level_current_test::where('distributeur_id', '6685253')->where('period', $period)->first();
        //return $dbRecup;
        /*
        $period = '2024-03';
        $dbRecup = Level_current_test::where('period', $period)->get();
        //return $dbRecup;
        foreach ($dbRecup as $value) {

            $collectif = Level_current_test::selectRaw('SUM(cumul_collectif) as children_collectif, etoiles, distributeur_id')
                        ->where('period', $period)
                        ->where('id_distrib_parent', $value->distributeur_id)
                        //->where('etoiles', '<', $value->etoiles)
                        ->get();
            $reste = $value->cumul_collectif - $collectif[0]->children_collectif;
            if(count($collectif) > 0)
            {/*
                if($reste >= 0)
                {

                    $updater = Level_current_test::where('distributeur_id', $value->distributeur_id)->where('period', $period)->first();
                    $updater->cumul_individuel = $reste;
                    $updater->update();

                    $list[] = array(
                        'reste > 0' => $reste,
                        'period' => $value->period,
                        'distributeur_id' => $value->distributeur_id,
                        'cumul_collectif_distrib' => $value->cumul_collectif,
                        'cumul_collectif_children' => $collectif[0]->children_collectif,
                        'actuel_cumul_individuel' => $value->cumul_individuel,
                        'cumul_individuel' => $reste,
                    );
                }
                else
                {
                    /*
                    $updater = Level_current_test::where('distributeur_id', $value->distributeur_id)->where('period', $period)->first();
                    $updater->cumul_individuel = $reste;
                    $updater->update();
                    *//*
                    $reste = $value->cumul_collectif - $collectif[0]->children_collectif;
                    $list[] = array(
                        'reste < 0' => $reste,
                        'period' => $value->period,
                        'etoiles' => $value->etoiles,
                        'distributeur_id' => $value->distributeur_id,
                        'cumul_collectif_distrib' => $value->cumul_collectif,
                        'cumul_collectif_children' => $collectif[0]->children_collectif,
                        'actuel_cumul_individuel' => $value->cumul_individuel,
                        'cumul_individuel' => $reste,
                    );
                }*//*
            }
            else {
                /*
                $collectif = Level_current_test::selectRaw('SUM(cumul_collectif) as children_collectif, etoiles, distributeur_id')
                        ->where('period', $period)
                        ->where('distributeur_id', $value->distributeur_id)
                        //->where('etoiles', '<', $value->etoiles)
                        ->get();
                return $collectif;
                $reste = $value->cumul_collectif - $collectif[0]->children_collectif;
                $list[] = array(
                    'reste < 0' => $reste,
                    'period' => $value->period,
                    'etoiles' => $value->etoiles,
                    'distributeur_id' => $value->distributeur_id,
                    'cumul_collectif_distrib' => $value->cumul_collectif,
                    'cumul_collectif_children' => $collectif[0]->children_collectif,
                    'actuel_cumul_individuel' => $value->cumul_individuel,
                    'cumul_individuel' => $reste,
                );

            }
        }

        return $list;
        //
        /*
        $achatsAll = Achat::selectRaw("distributeur_id, id_distrib_parent")
        //->where('distributeur_id', $id)
        ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') AS new_date")
        ->selectRaw("sum(pointvaleur) as new_cumul")
        ->groupBy('distributeur_id')
        ->distinct('distributeur_id')
        //->limit(10)
        ->pluck('distributeur_id')->toArray();

        $allDistrib[] = Level_current_2024_02::pluck('distributeur_id')->toArray();
        $allDistrib = Arr::flatten($allDistrib);

        $cmp = array_intersect($allDistrib, $achatsAll);

        foreach ($cmp as $key => $id) {

            $levelCurrentTest = Level_current_2024_02::join('achats', 'achats.distributeur_id', '=', 'level_current_2024_02s.distributeur_id')
            ->where('achats.distributeur_id', $id)->selectRaw("DATE_FORMAT(achats.created_at, '%Y-%m') AS new_date")
            ->selectRaw("sum(achats.pointvaleur) as cumul_achats")
            ->selectRaw("level_current_2024_02s.new_cumul, level_current_2024_02s.cumul_total, level_current_2024_02s.cumul_collectif")
            ->groupBy('achats.distributeur_id')->first();

            if( $levelCurrentTest != null)
            {
                $levelInsertAchats = Level_current_2024_02::where('distributeur_id', $id)->first();
                $cumul_total = $levelCurrentTest->cumul_total + $levelCurrentTest->cumul_achats;
                $cumul_collectif = $levelCurrentTest->cumul_collectif + $levelCurrentTest->cumul_achats;
                /*
                $levelInsertAchats->new_cumul = $levelCurrentTest->cumul_achats;
                $levelInsertAchats->cumul_total = $cumul_total;
                $levelInsertAchats->cumul_collectif = $cumul_collectif;
                $levelInsertAchats->update();
                */
                //return $levelCurrentTest;
                /*
                $pedigreTab[] = array(
                    'distributeur_id' => $levelInsertAchats->distributeur_id,
                    'pv achat' => $levelCurrentTest->cumul_achats,
                    'new_cumul' =>  $levelCurrentTest->cumul_achats,
                    'cumul_total' => $cumul_total,
                    'cumul_collectif' => $cumul_collectif,
                    'id_distrib_parent' => $levelInsertAchats->id_distrib_parent,
                );
            }
        }

        //return $pedigreTab;

        foreach ($pedigreTab as $key => $pedigreParent) {

            //$pedigreParent = $this->array_flatten($pedigreParent);

            if( $pedigreParent != null)
            {
                $pedigre[] = $this->getParentNetwork($pedigreParent['id_distrib_parent'], $pedigreParent['new_cumul']);
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

        //return $pedigreTab;


        return 'Insetion réussi !';*/
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        /*
        // inséré d'une manière particulière des achats à un réseau donné
        // sans touché au reste de la base de donnée
        /*
        $achatsAll = Achat::selectRaw("distributeur_id, id_distrib_parent")
        //->where('distributeur_id', $id)
        ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') AS new_date")
        ->selectRaw("sum(pointvaleur) as new_cumul")
        ->groupBy('distributeur_id')
        ->distinct('distributeur_id')
        //->limit(10)
        ->pluck('distributeur_id')->toArray();

        $enfants[] = array(
            'id' => '6686147',
        );
        $enfants[] = $this->getChildrenNetwork('6686147');
        $enfants = Arr::flatten($enfants);

        $cmp = array_intersect($enfants, $achatsAll);

        foreach ($cmp as $key => $id) {

            $achats = Achat::selectRaw('distributeur_id, id_distrib_parent')
            ->where('distributeur_id', $id)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') AS new_date")
            ->selectRaw("sum(pointvaleur) as new_cumul")
            ->groupBy('distributeur_id')
            ->distinct('distributeur_id')
            //->limit(10)
            ->first();

            $levelCurrentTest = Level_current_2024_02::where('distributeur_id', $id)->first();

            if( $levelCurrentTest != null)
            {

                $cumul_total = $levelCurrentTest->cumul_total + $achats->new_cumul;
                $cumul_collectif = $levelCurrentTest->cumul_collectif + $achats->new_cumul;

                $levelCurrentTest->new_cumul = $achats->new_cumul;
                $levelCurrentTest->cumul_total = $achats->new_cumul;
                $levelCurrentTest->cumul_collectif = $levelCurrentTest->cumul_collectif + $achats->new_cumul;
                $levelCurrentTest->update();

                //return $levelCurrentTest;
                $pedigreTab[] = array(
                    'distributeur_id' => $levelCurrentTest->distributeur_id,
                    'new_cumul' =>  $achats->new_cumul,
                    'id_distrib_parent' => $levelCurrentTest->id_distrib_parent
                );
            }
        }
        // inséré d'une manière particulière des achats à un réseau donné
        // sans touché au reste de la base de donnée

        $achatsAll = Achat::selectRaw("distributeur_id, id_distrib_parent")
        //->where('distributeur_id', $id)
        ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') AS new_date")
        ->selectRaw("sum(pointvaleur) as new_cumul")
        ->groupBy('distributeur_id')
        ->distinct('distributeur_id')
        //->limit(10)
        ->pluck('distributeur_id')->toArray();

        return $achatsAll;

        $enfants[] = $this->getChildrenNetwork('6686147', 0);
        $enfants = Arr::flatten($enfants);

        $cmp = array_intersect($enfants, $achatsAll);

        foreach ($cmp as $key => $id) {

            $achats = Achat::selectRaw('distributeur_id, id_distrib_parent')
            ->where('distributeur_id', $id)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') AS new_date")
            ->selectRaw("sum(pointvaleur) as new_cumul")
            ->groupBy('distributeur_id')
            ->distinct('distributeur_id')
            //->limit(10)
            ->first();

            $levelCurrentTest = Level_current_2024_02::where('distributeur_id', $id)->first();

            if( $levelCurrentTest != null)
            {

                $cumul_total = $levelCurrentTest->cumul_total + $achats->new_cumul;
                $cumul_collectif = $levelCurrentTest->cumul_collectif + $achats->new_cumul;
                /*
                $levelCurrentTest->new_cumul = $achats->new_cumul;
                $levelCurrentTest->cumul_total = $achats->new_cumul;
                $levelCurrentTest->cumul_collectif = $levelCurrentTest->cumul_collectif + $achats->new_cumul;
                $levelCurrentTest->update();
                *
                //return $levelCurrentTest;
                $pedigreTab[] = array(
                    'distributeur_id' => $levelCurrentTest->distributeur_id,
                    'new_cumul' =>  $achats->new_cumul,
                    'id_distrib_parent' => $levelCurrentTest->id_distrib_parent
                );
            }
        }*/
    }

    /**
     * Display the specified resource.
     */

    public function addCumulToParainsDebug($id_distrib_parent, $cumul, $period)
    {
        $children = [];

        $parains = Level_current_test::where('distributeur_id', $id_distrib_parent)->where('period', $period)->first();

        if($parains)
        {
            $parains->cumul_total = $parains->cumul_total + $cumul;
            $parains->cumul_collectif = $parains->cumul_collectif + $cumul;
            $parains->update();

            $children[] = array(
                'distributeur_id' => $parains->distributeur_id,
                'value_pv' => $cumul,
                'cumul_total' => $parains->cumul_total.' + '.$cumul.' = '.$parains->cumul_total+$cumul,
                'cumul_collectif' =>  $parains->cumul_collectif.' + '.$cumul.' = '.$parains->cumul_collectif+$cumul,
                'children' =>  $this->addCumulToParainsDebug($parains->id_distrib_parent, $cumul, $period)
            );
        }

        return $children;//Arr::flatten($children);
    }

    public function getChildrenNetworkCumul($disitributeurId, $period)
    {
        $children = [];

        $childDistributeurs = Level_current_test::where('id_distrib_parent', $disitributeurId)->where('period', $period)->get();
        foreach ($childDistributeurs as $child)
        {
            $children[] = array(
                'distributeur_id' => $child->distributeur_id,
                'children' => $this->getChildrenNetworkCumul($child->distributeur_id, $period, $children)
            );
            //$children[] = $this->array_flatten($temp);
        }
        $tab_total = Arr::flatten($children); //return $children;//Arr::flatten($children);
        return $tab_total;
    }


    public function show(Request $request)
    {

        /*
        $period = '2025-06';
        $updateTab = [];
        $select = Level_current_test::where('cumul_total', '>', 0)->where('period', $period)->get();

        foreach ($select as $value) {
            $select_last_period = Level_current_test::where('period', '2025-05')->where('distributeur_id', $value->distributeur_id)->first();
            $level = Level_current_test::where('period', $period)->where('distributeur_id', $value->distributeur_id)->first();

            $level->cumul_individuel = $select_last_period->cumul_individuel;
            $level->new_cumul = 0;
            $level->cumul_total = 0;
            $level->cumul_collectif = $select_last_period->cumul_collectif;
            $level->update();
            $updateTab[] = $level;
        }

        return $updateTab;
        */


        // NOUVEAUX ADHERENTS QUI ONT ETE INSERER DANS LEVEL_CURRENT_TEST AVEC UNE PERIOD=0
        /*
        $period = '2025-06';
        $rank = new DistributorRankService();
        $calculator = new GradeCalculator();
        $level_tab = [];

        $distrib = Distributeur::join('achats', 'achats.distributeur_id', '=', 'distributeurs.distributeur_id')
        ->groupBy('achats.distributeur_id')
        ->selectRaw('*, sum(achats.pointvaleur) as new_achat')
        ->where('achats.period', $period)
        ->where('distributeurs.etoiles_id', 1)
        //->toSql();
        ->get();

        $level = Level_current_test::where('period', 0)->get();

        if($level) {

            foreach ($level as $level_val){

                $level_indiv = Level_current_test::where('distributeur_id', $level_val->distributeur_id)->first();

                $level_indiv->period = $period;
                $level_indiv->update();

                $level_tab[] = array(
                    "period" => $period,
                    "distributeur_id" =>$level_indiv->distributeur_id,
                    "etoiles" => $level_indiv->etoiles,
                    "cumul_individuel" => $level_indiv->cumul_individuel,
                    "new_cumul" => $level_indiv->new_cumul,
                    "cumul_total" => $level_indiv->cumul_total,
                    "cumul_collectif" => $level_indiv->cumul_collectif,
                );
            }
        }

        return $level_tab;

        *//* AVANCEMENT EN GRADE D'UN SEUL DISTRIBUTEUR POUR UNE PERIODE DONNEE (CLAUDE.AI)

            $period = '2025-06';
            $distributeur_matricule = 2224878;

            // Initialiser les services
            $branchQualifier = new EternalHelperLegacyMatriculeDB();
            $calculator = new GradeCalculator();

            // IMPORTANT: Charger les maps avant toute utilisation
            $branchQualifier->loadAndBuildMaps();

            // Récupérer les données de niveau
            $level = Level_current_test::where('distributeur_id', $distributeur_matricule)
                                ->where('period', $period)
                                ->first();

            if (!$level) {
                return ['error' => 'LevelCurrent non trouvé'];
            }

            // Si vous voulez analyser manuellement les branches qualifiées
            $pass1Count = $branchQualifier->countQualifiedBranches($distributeur_matricule, $level->etoiles);
            $pass2Count = $level->etoiles > 1 ?
                $branchQualifier->countQualifiedBranches($distributeur_matricule, $level->etoiles - 1) : 0;

            // Calculer le nouveau grade potentiel
            // IMPORTANT: Utiliser la NOUVELLE signature
            $newPotentialLevel = $calculator->calculatePotentialGrade(
                $level->etoiles,
                (float)$level->cumul_individuel,
                (float)$level->cumul_collectif,
                $distributeur_matricule,  // Passer le matricule
                $branchQualifier         // Passer l'instance du branchQualifier
            );

            // Retourner les résultats pour analyse
            return [
                'message' => "Analyse pour Matricule {$distributeur_matricule}",
                'current_grade' => $level->etoiles,
                'cumul_individuel' => $level->cumul_individuel,
                'cumul_collectif' => $level->cumul_collectif,
                'branches_grade_n' => $pass1Count,
                'branches_grade_n_minus_1' => $pass2Count,
                'calculated_new_grade' => $newPotentialLevel,
                'promotion' => $newPotentialLevel > $level->etoiles ? 'OUI' : 'NON',
            ];

            $level->etoiles = $newPotentialLevel;
            $level->update();

        /*
        $period = '2025-03';
        $distributeur_id = 2273898;
        $rank = new DistributorRankService();
        $calculator = new GradeCalculator();

        $level = Level_current_test::where('distributeur_id', $distributeur_id)->where('period', $period)->first();

        $countChildren = $rank->checkMultiLevelQualificationSeparateCountsMatricule($level->distributeur_id, $level->etoiles);
        $countChildrenpass1 = $countChildren['level_n_qualified_count'];
        $countChildrenpass2 = $countChildren['level_n_minus_1_qualified_count'];
        $newPotentialLevel = $calculator->calculatePotentialGrade($level->etoiles, $level->cumul_individuel, $level->cumul_collectif, $countChildrenpass1,  $countChildrenpass2, $level->distributeur_id);

        return [$countChildrenpass1, $countChildrenpass2, $newPotentialLevel];

                /*
                $level->period = $period;
                $level->etoiles = $newPotentialLevel;
                $level->cumul_individuel = $value->new_achat;
                $level->new_cumul = $value->new_achat;
                $level->cumul_total = $value->new_achat;
                $level->cumul_collectif = $value->new_achat;
                $level->save();

                $level_tab[] = array(
                    "period" => $level->period,
                    "new_achat" => $value->new_achat,
                    "distributeur_id" =>$level->distributeur_id,
                    "etoiles" => $level->etoiles,
                    "cumul_individuel" => $level->cumul_individuel,
                    "new_cumul" => $level->new_cumul,
                    "cumul_total" => $level->cumul_total,
                    "cumul_collectif" => $level->cumul_collectif,
                );
            }
        }

        return $level_tab;

        //COPIER LE CUMUL COLLECTIF DU MOIS PRECEDENT AU MOIS SUIVANT
        /*
        $i=0;
        $collectif = Level_current_test::select('distributeur_id', 'cumul_collectif')->where('period', '2025-02')->get();
        foreach ($collectif as $key => $value) {
            $collectifEquals = Level_current_test::where('distributeur_id', $value->distributeur_id)->where('period', '2025-03')->first();
            $collectifEquals->cumul_collectif = $value->cumul_collectif;
            //$collectifEquals->cumul_individuel = $value->cumul_individuel;
            $collectifEquals->update();
            $i++;
        }
        return $i.' distributeurs ont été mis à jour';
        /**/
        //DUPLIQUER LES ELEMENTS DU MOIS PRECEDANT POUR LE MOIS EN COURS
        //EXEMPL : DUPLIQUER LES ELEMENTS DE LA PERIODE 2024-06 POUR LA PERIODE 2024-07 EN RAMENANT A ZERO
        //LE NEW CUMUL ET LE CUMUL TOTAL
        //2024-08
        /*

        $period = '2025-06';
        $level = Level_current_test::where('period', '2025-05')->get();
        //return $level;

        foreach ($level as $line) {

            try {
                Level_current_test::insert([
                    'distributeur_id' => $line->distributeur_id,
                    'period' => $period,
                    'rang' =>  $line->rang,
                    'etoiles' => $line->etoiles,
                    'cumul_individuel' => $line->cumul_individuel,
                    'new_cumul' => 0,
                    'cumul_total' => 0,
                    'cumul_collectif' => $line->cumul_collectif,
                    'id_distrib_parent' => $line->id_distrib_parent,
                    'created_at' => Carbon::parse($period.'-02')
                ]);
            }
            catch(\Illuminate\Database\QueryException $exept){
                dd($exept->getMessage());
            }

        }
        return 'finished';

        /**/
        // SCRIPT CALCUL DES GRADES DES DISTRIBUTEURS AYANT VALIDES
        /*
        $rank = new DistributorRankService();
        $calculator = new GradeCalculator();
        $period = '2025-03';
        $distrib = Distributeur::all();
        $achats = Achat::selectRaw('period, distributeur_id, id_distrib_parent, sum(pointvaleur) as new_achats')
            ->groupBy('distributeur_id')
            ->where('period', $period)
            //->toSql();
            ->get();
        //return $achats;

        foreach ($distrib as $val) {
            $level = Level_current_test::where('distributeur_id', $val->distributeur_id)
                ->where('period', $period)
                ->select('etoiles', 'cumul_individuel', 'cumul_collectif')
                ->first();

            if($level)
            {
                $countChildren = $rank->checkMultiLevelQualificationSeparateCountsMatricule($val->distributeur_id, $level->etoiles);
                $countChildrenpass1 = $countChildren['level_n_qualified_count'];
                $countChildrenpass2 = $countChildren['level_n_minus_1_qualified_count'];
                $newPotentialLevel = $calculator->calculatePotentialGrade($level->etoiles, $level->cumul_individuel, $level->cumul_collectif, $countChildrenpass1,  $countChildrenpass2);

                if($newPotentialLevel > $level->etoiles)
                {
                    $tab[] = array(
                        'distributeur_id' => $val->distributeur_id,
                        'etoiles_actuel' => $level->etoiles,
                        'etoiles_avancement' => $newPotentialLevel,
                        'status' => 'Avancement en grade',
                        'count_enfants' => $countChildren
                    );

                    $distrib_change = Distributeur::where('distributeur_id', $val->distributeur_id)->first();
                    $distrib_change->etoiles_id = $newPotentialLevel;
                    $level->etoiles = $newPotentialLevel;
                    $level->update();
                    $distrib_change->update();

                }
            }
        }
        return $tab;


        $rank = new DistributorRankService();
        $calculator = new GradeCalculator();
        $period = '2025-03';

        $level = Level_current_test::where('distributeur_id', '2292819')->where('period', $period)->first();
        //return $level;

        if($level)
        {
            $countChildren = $rank->checkMultiLevelQualificationSeparateCountsMatricule($level->distributeur_id, $level->etoiles);
            $countChildrenpass1 = $countChildren['level_n_qualified_count'];
            $countChildrenpass2 = $countChildren['level_n_minus_1_qualified_count'];
            $newPotentialLevel = $calculator->calculatePotentialGrade($level->etoiles, $level->cumul_individuel, $level->cumul_collectif, $countChildrenpass1,  $countChildrenpass2);
            return [$newPotentialLevel, $countChildrenpass1, $countChildrenpass2];

            if($newPotentialLevel > $level->etoiles)
            {
                $tab[] = array(
                    'distributeur_id' => $level->distributeur_id,
                    'etoiles_actuel' => $level->etoiles,
                    'etoiles_avancement' => $newPotentialLevel,
                    'status' => 'Avancement en grade',
                    'count_enfants' => $countChildren
                );

                $distrib_change = Distributeur::where('distributeur_id', $level->distributeur_id)->first();
                $distrib_change->etoiles_id = $newPotentialLevel;
                $level->etoiles = $newPotentialLevel;
                $level->update();
                $distrib_change->update();
            }
            else {
                $tab[] = array(
                    'distributeur_id' => $level->distributeur_id,
                    'etoiles_actuel' => $level->etoiles,
                    'etoiles_avancement' => $newPotentialLevel,
                    'status' => 'Aucun changement',
                    'count_enfants' => $countChildren
                );
            }
        }

        /*
         // C'EST ICI QUE CA SE PASSE
        // RECALCULER L'AVANCEMENT EN GRADE DES DISTRIBUTEURS AYANT
        //
        $rank = new DistributorRankService();
        $calculator = new GradeCalculator();
        $period = '2025-04';
        $achats = Achat::selectRaw('period, distributeur_id, id_distrib_parent, sum(pointvaleur) as new_achats')
            ->groupBy('distributeur_id')
            ->where('period', $period)
            //->toSql();
            ->get();
        //return $achats;

        foreach ($achats as $val) {
            $level = Level_current_test::where('distributeur_id', $val->distributeur_id)->where('period', $period)->first();

            if($level)
            {
                $countChildren = $rank->checkMultiLevelQualificationSeparateCountsMatricule($level->distributeur_id, $level->etoiles);
                $countChildrenpass1 = $countChildren['level_n_qualified_count'];
                $countChildrenpass2 = $countChildren['level_n_minus_1_qualified_count'];
                $newPotentialLevel = $calculator->calculatePotentialGrade($level->etoiles, $level->cumul_individuel, $level->cumul_collectif, $countChildrenpass1,  $countChildrenpass2);

                if($newPotentialLevel > $level->etoiles)
                {
                    $tab[] = array(
                        'distributeur_id' => $level->distributeur_id,
                        'etoiles_actuel' => $level->etoiles,
                        'etoiles_avancement' => $newPotentialLevel,
                        'status' => 'Avancement en grade',
                        'count_enfants' => $countChildren
                    );

                    $distrib_change = Distributeur::where('distributeur_id', $level->distributeur_id)->first();
                    $distrib_change->etoiles_id = $newPotentialLevel;
                    $level->etoiles = $newPotentialLevel;
                    $level->update();
                    $distrib_change->update();
                }
                else {
                    $tab[] = array(
                        'distributeur_id' => $level->distributeur_id,
                        'etoiles_actuel' => $level->etoiles,
                        'etoiles_avancement' => $newPotentialLevel,
                        'status' => 'Aucun changement',
                        'count_enfants' => $countChildren
                    );
                }
            }

            else {
                $tabEtoilesNull[] = $val->distributeur_id;
                $distrib = Distributeur::where('distributeur_id', $val->distributeur_id)->first();

                if($distrib)
                {
                    try {
                        Level_current_test::insert([
                            'distributeur_id' => $val->distributeur_id,
                            'period' => $period,
                            'rang' =>  $distrib->rang,
                            'etoiles' =>  1,
                            'cumul_individuel' => $val->new_achats,
                            'new_cumul' => $val->new_achats,
                            'cumul_total' => $val->new_achats,
                            'cumul_collectif' => $val->new_achats,
                            'id_distrib_parent' => $val->id_distrib_parent,
                            'created_at' => Carbon::parse($period.'-02')
                        ]);
                    }
                    catch(\Illuminate\Database\QueryException $exept){
                        dd($exept->getMessage());
                    }

                    $countChildren = $rank->checkMultiLevelQualificationSeparateCountsMatricule($val->distributeur_id, 1);
                    $countChildrenpass1 = $countChildren['level_n_qualified_count'];
                    $countChildrenpass2 = $countChildren['level_n_minus_1_qualified_count'];
                    $newPotentialLevel = $calculator->calculatePotentialGrade(1, $val->new_achats, $val->new_achats, $countChildrenpass1,  $countChildrenpass2);

                    if($newPotentialLevel > 1)
                    {
                        $tab[] = array(
                            'distributeur_id' => $val->distributeur_id,
                            'etoiles_actuel' => 1,
                            'etoiles_avancement' => $newPotentialLevel,
                            'status' => 'Avancement en grade',
                            'count_enfants' => $countChildren
                        );

                        $distrib_change = Distributeur::where('distributeur_id', $val->distributeur_id)->first();
                        $level_change = Level_current_test::where('distributeur_id', $val->distributeur_id)->first();
                        $distrib_change->etoiles_id = $newPotentialLevel;
                        $level_change->etoiles = $newPotentialLevel;
                        $level_change->update();
                        $distrib_change->update();
                    }
                    else {
                        $tab[] = array(
                            'distributeur_id' => $val->distributeur_id,
                            'etoiles_actuel' => 1,
                            'etoiles_avancement' => $newPotentialLevel,
                            'status' => 'Aucun changement',
                            'count_enfants' => $countChildren
                        );
                    }
                }
            }
        }

        return $tab;

        //
        // AGREGE LE CUMUL AUX AYANTS DROITS
        //

        $period = '2025-06';
        $personal_acaht = Achat::selectRaw('distributeur_id, sum(achats.pointvaleur) as new_achats, period')
            ->groupBy('achats.distributeur_id')
            ->where('achats.period', $period)
            //->toSql();
            ->get();

        foreach ($personal_acaht as $achat){
            $level_achat = Level_current_test::where('distributeur_id', $achat->distributeur_id)->where('period', $period)->first();

            $level_achat->cumul_individuel = $level_achat->cumul_individuel + $achat->new_achats;
            $level_achat->new_cumul = $achat->new_achats;
            $level_achat->cumul_total = $achat->new_achats;
            $level_achat->cumul_collectif = $level_achat->cumul_collectif + $achat->new_achats;
            $level_achat->update();

            $arrayTab[] = array(
                'period' => $level_achat->period,
                'distributeur_id' => $level_achat->distributeur_id,
                'etoiles' => $level_achat->etoiles,
                'new_achats' => $achat->new_achats,
                'cumul_individuel' => $level_achat->cumul_individuel,
                'new_cumul' => $level_achat->new_cumul,
                'total_cumul' => $level_achat->cumul_total,
                'cumul_collectif' => $level_achat->cumul_collectif
            );
        }

        return $arrayTab;

        /**/
        //AJOUTER LE NEW CUMUL AUX PARRAINS D'UN DISTRIBUTEUR PRECIS
        //LE VRAI ICI
        /*
        $period = '2025-06';
        //$distributeur_id = 2420075;
        $achats = Achat::selectRaw('distributeur_id, sum(achats.pointvaleur) as new_achats, id_distrib_parent, period')
            ->groupBy('achats.distributeur_id')
            ->where('achats.period', $period)
            //->where('distributeur_id', $distributeur_id)
            //->toSql();
            ->get();
        //return $achats;

        $eternalhelpers = new EternalHelper();

        foreach ($achats as $val) {
            $addCumul[] = $eternalhelpers->addCumulToParainsDebug($val->id_distrib_parent, $val->new_achats, $period);
        }

        return $addCumul;

        /**/
        // SUPPRESSION AUTOMATIQUE DES DOUBLONS DANS LA BD - COMMENCE ICI
        /*
        $period = '2024-11';
        $array = [];
        $eternalhelpers = new EternalHelper();
        $level_10 = Level_current_test::selectRaw('COUNT(period) as nbr, period, distributeur_id, new_cumul, cumul_total')
            ->where('period', $period)
            ->groupBy('period')
            ->groupBy('distributeur_id')
            ->having('nbr', '>', '1')
            ->get();

        //return $level_10;

        foreach ($level_10 as $value) {
            $control = Level_current_test::where('period', $period)->where('distributeur_id', $value->distributeur_id)->where('new_cumul', 0)->where('cumul_total', 0)->first();
            if($control){
                $control->delete();
                $tab[] = array(
                    'distributeur_id' => $value->distributeur_id,
                    'entry' => 'deleted'
                );
            }
            else {
                $tab[] = array(
                    'distributeur_id' => $value->distributeur_id,
                    'entry' => 'non deleted'
                );
            }

                /*
                $control = Level_current_test::where('period', '2024-02')->where('distributeur_id', $value->distributeur_id)->first();
                if($control){
                    $delete = Level_current_test::where('period', $period)->where('distributeur_id', $value->distributeur_id)->first();
                    $delete->delete();
                    $array[] = $value->distributeur_id;
                    //return $array;
                }
                else {
                    $delete = Level_current_test::where('period', $period)->where('distributeur_id', $value->distributeur_id)->first();
                    $delete->period = '2024-02';
                    $delete->update();
                    $array[] = $value->distributeur_id;
                    //return $array;
                }
                /**/
            /*
            else {
                $tab[] = array(
                    'distributeur_id' => $value->distributeur_id,
                    'cumul' => null,
                    'new_cumul' => $value->new_cumul,
                    'new_cucumul_totalmul' => $value->cumul_total
                );
            }*/
            /*
        }
        return 'finished';calculAvancementDistributeur
        */

        /* VERIFIER LE CUMUL DES ACHATS, CALCULER L'AVANCEMENT EN GRADE ET REPERCUTER LE CUMUL ACHAT SUR LE RESEAU PARENT*/
        /*
        $period = '2025-03';
        $eternalhelpers = new EternalHelper;
        $achat = Achat::selectRaw('period, SUM(pointvaleur) as new_achats, distributeur_id, id_distrib_parent')->where('period', $period)->groupby('distributeur_id')->get();
        foreach ($achat as $value) {
            $level = Level_current_test::where('distributeur_id', $value->distributeur_id)->where('period', $value->period)->first();
            if($value->new_achats > $level->new_cumul)
            {
                $etoilesCountChildren = array_sum($eternalhelpers->getChildrenNetworkAdvance($level->distributeur_id, $level->etoiles, 1));
                $etoiles = $eternalhelpers->calculAvancementDistribDebug($level->distributeur_id, $level->etoiles, $value->new_achats, $level->cumul_collectif, $etoilesCountChildren);
                $addCumul[] = $eternalhelpers->addCumulToParainsDebug($value->id_distrib_parent, $value->new_achats, $period);

                $level->etoiles = $etoiles;
                $level->cumul_individuel = $value->new_achats + $level->cumul_individuel;
                $level->new_cumul = $value->new_achats;
                $level->cumul_total = $value->new_achats + $level->cumul_total;
                $level->cumul_collectif = $value->new_achats + $level->cumul_collectif;
                $level->update();

                $tab[] = array(
                    'period' => $value->period,
                    'distributeur_id' => $value->distributeur_id,
                    'cumul_pv' => $value->new_achats,
                    'new_cumul' => $level->new_cumul,
                    'etoiles_ancien' => $level->etoiles,
                    'etoiles' => $etoiles
                );
            }
        }

        return $tab;
        // CA TERMINE ICI

        if(count($achat) > 0){
            $pv = $achat->sum('pointvaleur');
            $period = $achat[0]->period;
            $etoiles = $eternalhelpers->calculAvancementDistribDebug($value->distributeur_id, $value->etoiles, $pv, $pv);
            //$array[] = array('periodAchat' => $period, 'cumul PV' => $pv, 'etoiles' => $etoiles, 'info' => $value);
            $addCumul[] = $eternalhelpers->addCumulToParainsDebug($value->id_distrib_parent, $pv, $period);

            $level_u = Level_current_test::where('distributeur_id', $value->distributeur_id)->where('period', 0)->first();
            $level_u->period = $period;
            $level_u->etoiles = $etoiles;
            $level_u->cumul_individuel = $pv;
            $level_u->new_cumul = $pv;
            $level_u->cumul_total = $pv;
            $level_u->cumul_collectif = $pv;
            $level_u->update();

        }
        else {
            $period = $level[0]->created_at->format('Y-m');
            $level_u = Level_current_test::where('distributeur_id', $value->distributeur_id)->where('period', 0)->first();
            $level_u->period = $period;
            $level_u->update();
            //$array[] = array('periodAchat' => $period, 'info' => $value);
        }

        return 'its done !!!';


        //$service = new GradeService();

        // Act : appel du service
        //$newGrade = $service->updateEtoiles('5532212', '2025-02');

        // Assert : vérifier que le grade a bien changé
        //return $newGrade;

        //dd(LevelCurrentTest::all());
        //return $this->calculateNewGrade('2271212');

       /*
        $tabRecap = [];
        $tabNegatif = []; // Tableau pour stocker les cas où $diff < 0

        $levelsJan = Level_current_test::where('period', '2025-01')->get()->keyBy('distributeur_id');
        $levelsFeb = Level_current_test::where('period', '2025-02')
                    ->whereIn('distributeur_id', $levelsJan->keys())
                    ->select('distributeur_id', 'cumul_collectif', 'cumul_total') // Sélectionner explicitement les colonnes
                    ->get()
                    ->keyBy('distributeur_id');

        foreach ($levelsJan as $distributeurId => $value) {
            if (isset($levelsFeb[$distributeurId])) {
                $level2 = $levelsFeb[$distributeurId];
                $diff = $level2->cumul_collectif - $value->cumul_collectif;

                if ($diff > 0 && $diff != $level2->cumul_total) {
                    $tabRecap[] = [
                        'distributeur_id' => $distributeurId,
                        'diff' => $diff,
                        'cumul_total' => $level2->cumul_total
                    ];
                } else if ($diff == 0 && $level2->cumul_total > 0) {
                    // Ajouter les cas où diff est négatif
                    $tabNegatif[] = [
                        'distributeur_id' => $distributeurId,
                        'diff' => $diff,
                        'cumul_total' => $level2->cumul_total
                    ];
                }
            }
        }

        // Retourner les deux tableaux pour analyse
        return [
            'positif' => $tabRecap,
            'negatif' => $tabNegatif
        ];
        /*

        $levelsJan = Level_current_test::where('period', '2025-01')->get()->keyBy('distributeur_id');
        $levelsFeb = Level_current_test::where('period', '2025-02')
                    ->whereIn('distributeur_id', $levelsJan->keys())
                    ->get()
                    ->keyBy('distributeur_id');

        foreach ($levelsFeb as $distributeurId => $feb) {
            $jan = $levelsJan[$distributeurId];

            if ($feb->cumul_collectif == $jan->cumul_collectif) {
                // Calcul de la correction
                //$diff = $jan->cumul_collectif - $feb->cumul_total;
                $feb->cumul_collectif += $feb->cumul_total;

                // Mise à jour en base
                $feb->save();
            }
        }
        return 'ça marche';
        /*
        $tab = array(2224990, 2224991, 2224992, 2224993, 2224906, 2224995, 2224994, 2224997, 2224996, 2224998, 2224999
                    ,2225000, 2224982, 2224983, 2420051, 2420052, 2224907, 2292837, 2292880, 2292817, 2292824, 2292878
                    ,2292818, 2292822, 2420001, 2420002, 2292826, 2420004, 2420005, 2420006, 2292586);

        foreach ($tab as $value) {
            $level[] = Level_current_test::where('distributeur_id', $value)->get();
        }

        return $level;


        $eternalhelpers = new EternalHelper;
        $level = Level_current_test::where('distributeur_id')->where('period', 2025-02)->first();
        foreach ($level as $value) {
            $achat = Achat::where('distributeur_id', $value->distributeur_id)->get();
            if(count($achat) > 0){
                $pv = $achat->sum('pointvaleur');
                $period = $achat[0]->period;
                $etoiles = $eternalhelpers->calculAvancementDistribDebug($value->distributeur_id, $value->etoiles, $pv, $pv);
                //$array[] = array('periodAchat' => $period, 'cumul PV' => $pv, 'etoiles' => $etoiles, 'info' => $value);
                $addCumul[] = $eternalhelpers->addCumulToParainsDebug($value->id_distrib_parent, $pv, $period);

                $level_u = Level_current_test::where('distributeur_id', $value->distributeur_id)->where('period', 0)->first();
                $level_u->period = $period;
                $level_u->etoiles = $etoiles;
                $level_u->cumul_individuel = $pv;
                $level_u->new_cumul = $pv;
                $level_u->cumul_total = $pv;
                $level_u->cumul_collectif = $pv;
                $level_u->update();

            }
            else {
                $period = $level[0]->created_at->format('Y-m');
                $level_u = Level_current_test::where('distributeur_id', $value->distributeur_id)->where('period', 0)->first();
                $level_u->period = $period;
                $level_u->update();
                //$array[] = array('periodAchat' => $period, 'info' => $value);
            }
        }

        return 'its done !!!';

        /*
        $now = Carbon::now();
        $distributeurs = Distributeur::whereDate('created_at', '>', Carbon::today()->toDateString())->get();

        return view('layouts.distrib.index', [
            "distributeurs" => $distributeurs,
        ]);
        /*
        foreach ($distributeurs as $value) {
            $array[] = $value->distributeur_id;
            $delete = Level_current_test::where('distributeur_id', $value->distributeur_id);
            $delete->delete();
        }

        return $array;

        /*
        $eternalhelpers = new EternalHelper();
        $tab = [];
        $cumul = 0;
        //'3315208'
        //->skip(1000)->take(1000)
        $level = Level_current_test::where('period', '2025-01')->where('distributeur_id', 3315208)->first();
        $level_0 = Level_current_test::where('period', '2025-01')->where('id_distrib_parent', 3315208)->get();
        return [$level_0->sum('cumul_collectif'), ($level_0->sum('cumul_collectif')-$level->cumul_collectif), $level_0];
        foreach ($level_0 as $value) {
            $level_1 = Level_current_test::where('period', '2024-12')->where('id_distrib_parent', $value->distributeur_id)->get();
            foreach ($level_1 as $value) {
                $level_perso = Level_current_test::where('period', '2024-12')->where('distributeur_id', $value->distributeur_id)->first();
                $level_2 = Level_current_test::where('period', '2024-12')->where('id_distrib_parent', $value->distributeur_id)->get();
                $cumul_collectif_2 = $level_2->sum('cumul_collectif');
                $tab[] = [$cumul_collectif_2, $level_2];
            }
            $cumul = $cumul + $level_1->sum('cumul_collectif');
        }
        $cumul_collectif = $level_0->sum('cumul_collectif') + $cumul;
        return [$cumul_collectif, $tab];
        /*
        foreach ($level_9 as $level_9_valu) {
            /*
            $level_05 = new Level_current_test();
            $level_05->rang = 0;
            $level_05->period = '2024-10';
            $level_05->distributeur_id = $level_9_valu->distributeur_id;
            $level_05->etoiles = $level_9_valu->etoiles;
            $level_05->cumul_individuel	 = $level_9_valu->cumul_individuel;
            $level_05->new_cumul = 0;
            $level_05->cumul_total = 0;
            $level_05->cumul_collectif = $level_9_valu->cumul_collectif;
            $level_05->id_distrib_parent = $level_9_valu->id_distrib_parent;
            $level_05->created_at = Carbon::parse('2024-10-05');
            $level_05->save();


        }

        //return $tab;
        /*
        if($level)
        {
            foreach ($level as $val) {
                $etoiles = $eternalhelpers->avancementGrade($val->distributeur_id, $val->etoiles, $val->cumul_individuel, $val->cumul_collectif);
                $individus = Level_current_test::where('id', $val->id)->first();
                if($etoiles > $individus->etoiles){
                    /*
                    $individus->etoiles = $etoiles;
                    $individus->update();
                    *//*
                    $tab[] = array(
                        'new etoiles' => $etoiles,
                        'infos' => $individus
                    );
                }
            }*
            return $tab;
        }
        else{
            return 'Aucune entré ne correspond à la requette !!!!!';
        }
        /**/
        // RECHERCE DE DISTRIBUTEURS AYANT FAIT DES ACHATS
        // MAIS QUI N'ONT PAS ETE PRIS EN CHARGE DANS LE PRINT OUT
        // ET AU NIVEAU DU CUMUL DES PARAINS
        /*
        $periods = Achat::groupby('period')->get('period');
        foreach ($periods as $period) {

            $period = $period->period;
            $achats = Achat::selectRaw('SUM(pointvaleur) as new_achats, distributeur_id, id_distrib_parent')->where('period', $period)->groupby('distributeur_id')->get();

            $eternalhelpers = new EternalHelper();

            foreach ($achats as $val) {
                $level = Level_current_test::where('distributeur_id', $val->distributeur_id)->where('period', $period)->first();

                if($level->new_cumul == 0)
                {

                    $distrib = Distributeur::where('distributeur_id', $val->distributeur_id)->first('etoiles_id');
                    $cumul_individuel = $level->cumul_individuel + $val->new_achats;
                    $cumul_total = $level->cumul_total + $val->new_achats;
                    $cumul_collectif = $level->cumul_collectif + $val->new_achats;

                    $addnewcumul = $eternalhelpers->addNewCumul($val->distributeur_id, $val->new_achats, $period);
                    $etoiles = $eternalhelpers->avancementGrade($val->distributeur_id, $distrib->etoiles_id, $cumul_individuel, $cumul_collectif);

                    if($etoiles > $distrib->etoiles_id)
                    {
                        $level->etoiles = $etoiles;
                        $level->update();
                        $distrib->etoiles_id = $etoiles;
                        $distrib->update();
                    }
                    else {
                        $level->etoiles = $distrib->etoiles_id;
                        $level->update();
                    }

                    $addCumul[] = $eternalhelpers->addCumulToParainsDebug($level->id_distrib_parent, $val->new_achats, $period);

                    $lastAchat = Achat::orderBy('period', 'DESC')->first();
                    $lastAchatPeriod = Carbon::createFromFormat('Y-m', $lastAchat->period);//
                    $diffmonths = $lastAchatPeriod->diffInMonths(Carbon::createFromFormat('Y-m', $period));
                    $periodCarbon = Carbon::createFromFormat('Y-m', $period);

                    //return [$period,$diffmonths];

                    for($i=1; $i<=$diffmonths; $i++)
                    {
                        $mois = $periodCarbon->addMonth();
                        $periodup = $mois->format('Y-m');

                        $addnewcumuldiffere = $eternalhelpers->addNewCumulDiffere($val->distributeur_id, $val->new_achats, $periodup);
                        $etoiles = $eternalhelpers->avancementGrade($val->distributeur_id, $addnewcumuldiffere[0]['etoiles'], $addnewcumuldiffere[0]['cumul_individuel'], $addnewcumuldiffere[0]['cumul_collectif']);

                        $levelInsert = Level_current_test::where('distributeur_id', $val->distributeur_id)->where('period', $periodup)->first();
                        $distrib = Distributeur::where('distributeur_id', $val->distributeur_id)->first();

                        if($etoiles > $levelInsert->etoiles)
                        {
                            $levelInsert->etoiles = $etoiles;
                            $levelInsert->update();
                            $distrib->etoiles_id = $etoiles;
                            $distrib->update();
                        }

                        $addcumultoparaindiffere[] = $eternalhelpers->addCumulToParainsDebugDiffere($levelInsert->id_distrib_parent, $val->new_achats, $periodup);

                        $tabmois[] = $periodup;
                        $mois = $mois;
                    }

                    $tab[] = array(
                        'period' => $period,
                        'distributeur_id' => $val->distributeur_id,
                        'etoiles' => $etoiles,
                        'cumul_individuel' => $cumul_individuel,
                        'new_cumul' => $val->new_achats,
                        'cumul_total' => $cumul_total,
                        'cumul_collectif' => $cumul_collectif,
                        'id_distrib_parent' => $val->id_distrib_parent
                    );
                }
            }
        }

        return $tab;
        //NETTOYAGE DISTRIBUTEURS DONT LE RANG EST DE ZERO (0) DANS LES PRINTOUT
        /* LE VRAI ICI
        $level_02 = [];
        $level_03 = [];
        $comparatif1 = Distributeur::all();
        foreach ($comparatif1 as $value) {
            $level_02[] = $value->distributeur_id;
        }

        $comparatif2 = Level_current_test::where('period', '2024-08')->groupBy('distributeur_id')->get();
        foreach ($comparatif2 as $value) {
            $level_03[] = $value->distributeur_id;
        }

        $result = array_diff($level_02, $level_03);
        //return $result;

        foreach ($result as $key => $value) {
            $insert = Distributeur::where('distributeur_id', $value)->first();
            /*
            //return $insert->distributeur_id;
            $level_05 = new Level_current_test();
            $level_05->rang = 0;
            $level_05->period = '2024-08';
            $level_05->distributeur_id = $insert->distributeur_id;
            $level_05->etoiles = $insert->etoiles_id;
            $level_05->cumul_individuel	 = 0;
            $level_05->new_cumul = 0;
            $level_05->cumul_total = 0;
            $level_05->cumul_collectif = 0;
            $level_05->id_distrib_parent = $insert->id_distrib_parent;
            $level_05->created_at = $insert->created_at;
            $level_05->save();

            /*
            // POUR SUPPRIMER LES DOUBLONS
            if(count($insert) == 2){
                foreach ($insert as $key => $valu) {
                    if($key == 1)
                    {
                        $delete = Level_current_test::where('id', $insert[$key]['id'])->first();
                        //$delete->delete();
                    }
                }
            }

        }

        return 'done';/*$insert; //*/

        /*
        $level_comp = Level_current_test::whereNotIn('distributeur_id', $level_02)->where('period', '2024-02')->groupBy('distributeur_id')->get();
        return $level_comp;
        foreach ($level_comp as $value) {
            try {
                Level_current_test::insert([

                    'distributeur_id' => $value->distributeur_id,
                    'period' => Carbon::parse($value->created_at)->format('Y-m'),
                    'rang' => 0,
                    'etoiles' => $value->etoiles_id,
                    'cumul_individuel' => 0,
                    'new_cumul' => 0,
                    'cumul_total' => 0,
                    'cumul_collectif' => 0,
                    'id_distrib_parent' => $value->id_distrib_parent,
                    'created_at' => $value->created_at
                ]);
            }
            catch(\Illuminate\Database\QueryException $exept){
                dd($exept->getMessage());
            }
        }
            */

        //return $this->comparatifTab();
        //REGULARISER LES ANCIENNES VALIDATIONS OUBLIEES
        /*
        $eternals = new EternalHelper();

        $level = Achat::selectRaw('period, distributeur_id, sum(pointvaleur) as new_achats, id_distrib_parent, created_at')->whereBetween('updated_at', ['2024-08-19','2024-08-26'])->groupBy('distributeur_id')->get();
        //return $level;
        foreach ($level as $val) {

            $level = Level_current_test::where('distributeur_id', $val->distributeur_id)->where('period', $val->period)->first();
            if($level) {

                $individuel = $val->new_achats + $level->cumul_individuel;
                $new_cumul = $val->new_achats + $level->new_cumul;
                $cumul_total = $val->new_achats + $level->cumul_total;
                $collectif = $val->new_achats + $level->cumul_collectif;

                $eternals->addCumulToParainsDebug($val->id_distrib_parent, $val->new_achats, $val->period);
                $level->etoiles = $eternals->avancementGrade($val->distributeur_id, $level->etoiles, $individuel, $collectif);
                $level->cumul_individuel = $individuel;
                $level->new_cumul = $new_cumul;
                $level->cumul_total = $cumul_total;
                $level->cumul_collectif = $collectif;

                $level->update();
            }
            else {
                $eternals->addCumulToParainsDebug($val->id_distrib_parent, $val->new_achats, $val->period);
                $etoiles = $eternals->avancementGrade($val->distributeur_id, 1, $val->new_achats, $val->new_achats);

                $level_05 = new Level_current_test();
                $level_05->rang = 0;
                $level_05->period = $val->period;
                $level_05->distributeur_id = $val->distributeur_id;
                $level_05->etoiles = $etoiles;
                $level_05->cumul_individuel	 = $val->new_achats;
                $level_05->new_cumul = $val->new_achats;
                $level_05->cumul_total = $val->new_achats;
                $level_05->cumul_collectif = $val->new_achats;
                $level_05->id_distrib_parent = $val->id_distrib_parent;
                $level_05->created_at = $val->created_at;
                $level_05->save();
            }

            //$addparain = $eternals->addCumulToParainsDebug($value->distributeur_id, $value->pointvaleur, $value->period);
            $tab[] = array(
                'period' => $val->period,
                'distributeur_id' => $val->distributeur_id,
                'pointvaleur' => $val->new_achats,
                "id_distrib_parent" => $val->id_distrib_parent,
                "created_at" => $val->created_at
            );
        }
        return $tab;
        */
        //METTRE A JOUR LES PERIODES DES ANCIENNES VALIDATIONS DEJA REGULARISE
        /*
        $tab = array();
        $level = Achat::selectRaw('period, distributeur_id, sum(pointvaleur) as new_achats, id_distrib_parent, created_at')
                ->whereBetween('updated_at', ['2024-08-19','2024-08-26'])
                ->groupBy('distributeur_id')
                ->get();

        foreach ($level as $val) {

            $levelad = Level_current_test::where('distributeur_id', $val->distributeur_id)->where('period', $val->period)->first();
            $now = Carbon::now();
            $perioded = Carbon::createFromFormat('Y-m', $val->period);
            $nbturn =  $perioded->diffInMonths($now);
            //return $nbturn;

            if($nbturn > 0)
            {
                for($i=1; $i<$nbturn; $i++)
                {
                    $perioded = Carbon::createFromFormat('Y-m', $val->period);
                    $dated = $perioded->addMonths($i)->format('Y-m');
                    $levelmodif = Level_current_test::where('distributeur_id', $val->distributeur_id)->where('period', $dated)->first();

                    if($levelmodif) {

                        $levelmodif->etoiles = $levelad->etoiles;
                        $levelmodif->cumul_individuel = $levelmodif->cumul_individuel + $val->new_achats;
                        $levelmodif->cumul_collectif = $levelmodif->cumul_collectif + $val->new_achats;
                        $levelmodif->update();

                        $tab[] = array(
                            'period' => $dated,
                            'etoiles' => $levelad->etoiles,
                            'etoiled' => $levelmodif->etoiles,
                            'distributeur_id' => $levelmodif->distributeur_id,
                            'cumul_individuel' => $levelmodif->cumul_individuel + $val->new_achats,
                            'new_cumul' => $levelmodif->new_cumul,
                            'total_cumul' => $levelmodif->cumul_total,
                            'cumul_collectif' => $levelmodif->cumul_collectif + $val->new_achats
                        );
                    } else {

                        $level_05 = new Level_current_test();
                        $level_05->rang = 0;
                        $level_05->period = $dated;
                        $level_05->distributeur_id = $val->distributeur_id;
                        $level_05->etoiles = $levelad->etoiles;
                        $level_05->cumul_individuel	 = $val->new_achats;
                        $level_05->new_cumul = 0;
                        $level_05->cumul_total = 0;
                        $level_05->cumul_collectif = $val->new_achats;
                        $level_05->id_distrib_parent = $val->id_distrib_parent;
                        $level_05->created_at = $val->created_at;
                        $level_05->save();

                        $tab[] = array(
                            'isit' => 'must be created',
                            'period' => $dated,
                            'distributeur_id' => $val->distributeur_id,
                            'cumul_individuel' => $val->new_achats,
                            'new_cumul' => 0,
                            'total_cumul' => 0,
                            'cumul_collectif' => $val->new_achats
                        );
                    }
                }
            }
        }

        return $tab;
        */

        /*/

        $leveled = Level_current_test::where('etoiles', 5)->where('cumul_collectif', '>=', 20000)->where('period', $period)->get();

        foreach ($leveled as $value) {
            $achat_verif = Achat::where('distributeur_id', $value->distributeur_id)->where('period', $period)->first();
            if($achat_verif)
            {
                $verif = Level_current_test::selectRaw('period, sum(cumul_collectif) as collectif, COUNT(distributeur_id) as nbr')
                ->where('id_distrib_parent', $value->distributeur_id)->where('period', $period)->first();

                $netoyage = Level_current_test::where('distributeur_id', $value->distributeur_id)->where('period', $period)->first();

                if($verif) {
                    $cumul_individuel = $value->cumul_collectif - $verif->collectif;
                    $etoiles = $this->calculAvancementDistributeur($value->distributeur_id, $value->etoiles, $cumul_individuel, $value->cumul_collectif);
                    if($etoiles > $value->etoiles)
                    {
                        //
                        $netoyage->etoiles = $etoiles;
                        $netoyage->cumul_individuel = $cumul_individuel;
                        $netoyage->update();
                        //
                    }
                    else {
                        //
                        $netoyage->cumul_individuel = $cumul_individuel;
                        $netoyage->update();
                        //
                    }

                    $tab[] = array(
                        'children' => true,
                        'period' => $value->period,
                        'distributeur_id' => $value->distributeur_id,
                        'Exetoiles' => $value->etoiles,
                        'etoiles' => $etoiles,
                        'cumul_individuel' => $cumul_individuel,
                        'cumul_collectif' => $value->cumul_collectif,
                    );
                }
                else {
                    $etoiles = $this->calculAvancementDistributeur($value->distributeur_id, $value->etoiles, $value->cumul_collectif, $value->cumul_collectif);
                    if($etoiles > $value->etoiles)
                    {
                        //
                        $netoyage->etoiles = $etoiles;
                        $netoyage->update();
                        //
                    }
                    else {

                        $tab[] = array(
                            'children' => false,
                            'period' => $value->period,
                            'distributeur_id' => $value->distributeur_id,
                            'Exetoiles' => $value->etoiles,
                            'etoiles' => $etoiles,
                            'cumul_individuel' => $value->cumul_collectif,
                            'cumul_collectif' => $value->cumul_collectif,
                        );
                    }
                }
            }
            else {
                $tab[] = array(
                    'PAS DACHATS' => 'la clé nest pas entrée',
                    'period' => $value->period,
                    'distributeur_id' => $value->distributeur_id,
                    'Exetoiles' => $value->etoiles,
                    'cumul_collectif' => $value->cumul_collectif,
                );
            }
        }

        return $tab;
        //

        $levelDistribEtoilesCampare = Level_current_test::where('level_current_tests.period', '2024-11')
        ->join('distributeurs', function ($join) {
            $join->on('distributeurs.distributeur_id', '=', 'level_current_tests.distributeur_id')
            ->on('level_current_tests.etoiles', '>', 'distributeurs.etoiles_id');
        })->get();

        foreach ($levelDistribEtoilesCampare as $value) {
            $distrib = Distributeur::where('distributeur_id', $value->distributeur_id)->first();
            /*
            $distrib->etoiles_id = $value->etoiles;
            $distrib->update();
            /*
            $tab[] = array(
                'distributeur_id' => $distrib->distributeur_id,
                'etoiles_id' => $distrib->etoiles_id,
                'etoiles' => $value->etoiles
            );
        }

        return $tab;
        */

        /*
        $period = '2025-03';
        $period2 = '2025-02';
        $id = 2271128 ;
        $isit = 0;
        $tabRecap = [];
        $eternalhelpers = new EternalHelper();
        $level_t = Level_current_test::where('distributeur_id', $id)->where('period', $period)->first();
        $coutChildren = $eternalhelpers->getChildrenNetworkAdvance($level_t->distributeur_id, $level_t->etoiles, 1);
        $etoilesCountChildren = array_sum($coutChildren);
        $etoiles = $eternalhelpers->calculAvancementDistribDebug($level_t->distributeur_id, $level_t->etoiles, $level_t->cumul_individuel, $level_t->cumul_collectif, $etoilesCountChildren);

        return array(
            'distributeur_id' => $level_t->distributeur_id,
            'etoiles_actuel' => $level_t->etoiles,
            'count_enfants' => $coutChildren
        );
        /*
        $achat = Achat::join('level_current_tests', 'level_current_tests.distributeur_id', '=', 'achats.distributeur_id')
        ->groupBy('achats.distributeur_id')
        ->selectRaw('*, sum(achats.pointvaleur) as new_achat')
        ->where('achats.period', $period)
        ->where('level_current_tests.period', $period)
        //->where('level_current_tests.etoiles', 6)
        //->toSql();
        ->get();

        foreach ($achat as $value) {
            $etoilesCountChildren = array_sum($eternalhelpers->getChildrenNetworkAdvance($value->distributeur_id, $value->etoiles, 1));
            $etoiles = $eternalhelpers->calculAvancementDistribDebug($value->distributeur_id, $value->etoiles, $value->cumul_individuel, $value->cumul_collectif, $etoilesCountChildren);
            if($etoiles > $value->etoiles)
            {
                $isbonus = $eternalhelpers->isBonusEligible($value->etoiles, $value->new_achat);
                if($isbonus) {
                    $tabRecap[] = array(
                        'distributeur_id' => $value->distributeur_id,
                        'ex_etoiles' => $value->etoiles,
                        'etoiles' => $etoiles
                    );
                }
                else {
                    $tabRecap[] = array(
                        'distributeur_id' => $value->distributeur_id,
                        'ex_etoiles' => $value->etoiles
                    );
                }
            }
            else {
                $tabRecap[] = array(
                    'distributeur_id' => $value->distributeur_id,
                    'ex_etoiles' => $value->etoiles
                );
            }
            /*
            if($etoiles >= $value->etoiles)
            {
                $level_own = Level_current_test::where('distributeur_id', $value->distributeur_id)->where('period', $period)->first();
                $distrib_own = Distributeur::where('distributeur_id', $value->distributeur_id)->first();
                $distrib_own->etoiles_id = $etoiles;
                $distrib_own->update();
                $level_own->etoiles = $etoiles;
                $level_own->update();
            }
            /*
        }

        return $tabRecap;


        $regul = Level_current_test::where('distributeur_id', $id)->where('period', $period)->first();
        $cumulChildVerif = Level_current_test::selectRaw('sum(cumul_collectif) as childCollectif')->where('id_distrib_parent', $id)->where('period', $period)->get();

        $cumul_individuel = $regul->cumul_collectif - $cumulChildVerif[0]->childCollectif;
        $etoiles_requis = Etoile::where('etoile_level', ( $regul->etoiles+1 ))->first();
        $distribChildren = Distributeur::where('id_distrib_parent', $id)->where('etoiles_id','>=', $regul->etoiles)->get();

        $etoilesCountChildren = array_sum($this->getChildrenNetworkAdvance($id, $regul->etoiles, 1));

        return $eternalhelpers->calculAvancementDistribDebug($regul->distributeur_id, $regul->etoiles, $cumul_individuel, $regul->cumul_collectif, $etoilesCountChildren);

        foreach ($distribChildren as $value) {
            if($value->etoiles_id >= $regul->etoiles)
            {
                $compt[] = $isit++;
            }
            else {
                $etoiles = $this->calculAvancementDistributeur($regul->distributeur_id, $regul->etoiles, $cumul_individuel, $regul->cumul_collectif, $period);
                $compt[] = $etoiles;
            }
        }
        return $compt;

        /*
        $verif = [];
        /*
        $all_level = Level_current_test::where('level_current_tests.period', $period)->join('level_current_tests as lct', function ($join) {
            $join->on('lct.distributeur_id', '=', 'level_current_tests.distributeur_id')
            ->where('lct.period', '2024-07')
            ->where('level_current_tests.cumul_individuel', '>', 'lct.cumul_individuel')
            ->where('level_current_tests.cumul_collectif', '>','cumul_collectif');
        })->get();

        return $all_level;
        /*
        $regul = Level_current_test::where('distributeur_id', '2273565')->where('period', $period)->first();
        //return $regul;

        $chckCollectif = Level_current_test::where('distributeur_id', '2273565')->where('period', $period2)->first();
        if($chckCollectif)
        {
            $cumul_individuel = $regul->cumul_individuel + $chckCollectif->cumul_individuel;
            $cumul_collectif = $regul->cumul_collectif + $chckCollectif->cumul_collectif;
            if($chckCollectif->etoiles >= $regul->etoiles)
            {
                $etoiles = $chckCollectif->etoiles;
            }
            else {
                $etoiles = $regul->etoiles;
            }
            /*
            //$level_own = Level_current_test::where('distributeur_id', $value->distributeur_id)->where('period', $period)->first();
            $chckCollectif->cumul_individuel = $cumul_individuel;
            $chckCollectif->cumul_collectif = $cumul_collectif;
            $chckCollectif->etoiles = $etoiles;
            $chckCollectif->update();
            /*

            $verif[] = array(
                'period' => $period,
                'distributeur_id' => '2290425',
                'cumul_individuel' => $cumul_individuel,
                'cumul_collectif' => $cumul_collectif,
                'etoiles' => $etoiles,
            );
        }

        return $verif;

        $etoiles = 3; $ligne = 0; $nbr = 0;

        $all_level = Achat::distinct('distributeur_id')->where('achats.period', $period)->join('level_current_tests as lct', function ($join) {
            $join->on('lct.distributeur_id', '=', 'achats.distributeur_id')->where('lct.period', '2024-11')->where('lct.etoiles', '=','3');
        })->get(['lct.period', 'lct.distributeur_id', 'lct.etoiles']);

        //return $all_level;

        foreach ($all_level as $value) {
            $level_own = Level_current_test::where('distributeur_id', $value->distributeur_id)->where('period', $period)->first();
            if($value->cumul_collectif >= 2500)
            {
                $level = Level_current_test::where('period', $period)->where('id_distrib_parent', $value->distributeur_id)->where('etoiles', '>=', 3)->get();
                if(count($level) >= 2)
                {
                    $level_own->etoiles = 4;
                    $tab[] = array(
                        '1er cas' => true,
                        'distributeur_id' => $value->distributeur_id,
                        'ancien grade' => $value->etoiles,
                        'nouveau grade' => $level_own->etoiles
                    );
                }
                else {
                    $countChild = Level_current_test::where('period', $period)->where('id_distrib_parent', $value->distributeur_id)->get();
                    foreach ($countChild as $value) {
                        $nbr = $this->getChilrenAvancement($value->distributeur_id, $etoiles, $period);
                        $tab[] = array(
                            'cas' => '1er cas',
                            'distributeur_id' => $value->distributeur_id,
                            'count' => $nbr
                        );
                    }
                    //if(count($nbr) > 0) $ligne = $ligne + $nbr;
                }
            }
            elseif($level_own->cumul_collectif >= 1250) {
                $level = Level_current_test::where('period', $period)->where('id_distrib_parent', $value->distributeur_id)->where('etoiles', '>=', 2)->get();
                if(count($level) >= 3)
                {
                    $level_own->etoiles = 4;
                    $tab[] = array(
                        '2eme cas' => true,
                        'distributeur_id' => $value->distributeur_id,
                        'ancien grade' => $value->etoiles,
                        'nouveau grade' => $level_own->etoiles
                    );
                }
                else {
                    $countChild = Level_current_test::where('period', $period)->where('id_distrib_parent', $value->distributeur_id)->get();
                    foreach ($countChild as $value) {
                        $nbr = $this->getChilrenAvancement($value->distributeur_id, $etoiles, $period);
                        $tab[] = array(
                            'cas' => '1er cas',
                            'distributeur_id' => $value->distributeur_id,
                            'count' => $nbr
                        );
                    }
                }
            }
            else{
                $tab[] = ['3eme cas', $level_own->cumul_collectif, 'quota insuffisant'];
            }
        }

        return $tab;

        // SUPPRESSION AUTOMATIQUE DES DOUBLONS DANS LA BD - TERMINE ICI
        /*
        // CONTROLE SI LE GRADE DE LA PERIOD PRECEDENTE EST INFERIEUR A LA PERIODE SUIVANTE - COMMENCE ICI

        $level = Level_current_test::where('period', '2024-10')->where('etoiles', '<', function (Builder $query) {
            $query->selectRaw('avg(i.etoiles)')->where('period', '2024-11')->from('level_current_tests as i');
        })->where('distributeur_id', '=', 'i.distributeur_id')->get();

        $level = Level_current_test::where('level_current_tests.period', '2024-10')->join('level_current_tests as lct', function ($join) {
            $join->on('lct.distributeur_id', '=', 'level_current_tests.distributeur_id')->where('lct.period', '2024-11')->where('lct.etoiles', '<','level_current_tests.etoiles');
        })->tosql();

        return $level;

        foreach ($level_10 as $value) {
            $level_11 = Level_current_test::where('period', '2024-11')->where('distributeur_id', $value->distributeur_id)->where('etoiles','<', $value->etoiles)->first();
            if($level_11)
            {
                $array[] = array(
                    'distributeur_id' => $level_11->distributeur_id,
                    'etoiles_10' => $value->etoiles,
                    'etoiles_11' => $level_11->etoiles,
                    'collectif_10' => $value->cumul_collectif,
                    'collectif_11' => $level_11->cumul_collectif
                );
            }
        }

        return $array;
            /*
            $cumul_individuel = $level->cumul_individuel + $achats->new_achats;
            $cumul_total = $level->cumul_total + $achats->new_achats;
            $cumul_collectif = $level->cumul_collectif + $achats->new_achats;
            $etoiles = $eternalhelpers->avancementGrade($achats->distributeur_id, $level->etoiles, $cumul_individuel, $cumul_collectif);

            $level->cumul_individuel = $cumul_individuel;
            $level->etoiles = $etoiles;
            $level->new_cumul = $achats->new_achats ?? 0;
            $level->cumul_total = $cumul_total;
            $level->cumul_collectif = $cumul_collectif;
            $level->update();
            */

        /*
        $period = '2024-11';
        $level = Level_current_test::selectRaw('SUM(cumul_total) as collectif')->where('id_distrib_parent', '2221830')->where('period', $period)->get();
        return [$period, $level];
        /*
        // CONTROLE SI LE GRADE DE LA PERIOD PRECEDENTE EST INFERIEUR A LA PERIODE SUIVANTE - COMMENCE ICI

        $ethernal = new EternalHelper();
        $etoiles = 4;
        $nbr = 0;
        $tab = [];
        $level_10 = Level_current_test::where('distributeur_id', '2273806')->where('period', '2024-10')->first();
        $parentDistributeurs = Distributeur::where('id_distrib_parent', '2273806')->get();

        foreach ($parentDistributeurs as $value) {
            $tab[] = $this->getChildrenEtoiles($level_10->distributeur_id, $level_10->etoiles, $nbr);
        }

        return $tab;

        return $ethernal->avancementGrade($level_10->distributeur_id, $level_10->etoiles, $level_10->cumul_individuel, $level_10->cumul_collectif);

        // CONTROLE SI LE GRADE DE LA PERIOD PRECEDENTE EST INFERIEUR A LA PERIODE SUIVANTE - COMMENCE ICI
        */

        // CONTROLE SI LE CUMUL TOTAL DE LA PERIOD PRECEDENTE EST INFERIEUR CELUI DE LA PERIODE SUIVANTE - COMMENCE ICI
        /*

        $level = Level_current_test::where('level_current_tests.period', '2024-11')->join('level_current_tests as lct', function ($join) {
            $join->on('lct.distributeur_id', '=', 'level_current_tests.distributeur_id')->where('lct.period', '2024-10')->where('lct.cumul_collectif', '>','level_current_tests.cumul_collectif');
        })->get();

        return ['ça marche', $level];

        $tab = [];
        foreach ($level as $value) {
            $check = Level_current_test::where('distributeur_id', $value->distributeur_id)->where('period', '2024-02')->first();
            if($value->cumul_collectif > $check->cumul_collectif)
            {
                if($check->cumul_total == 0)
                {
                    /*
                    $check->cumul_collectif = $value->cumul_collectif;
                    $check->update();

                    $tab[] = array(
                        'distributeur_id' => $value->distributeur_id,
                        'Vérifié' => true,
                        'period1' => $value->period,
                        'total_01' => $value->cumul_total,
                        'collectif_01' => $value->cumul_collectif,
                        'period2' => $check->period,
                        'total_02' => $check->cumul_total,
                        'collectif_02' => $check->cumul_collectif
                    );

                }
            }
        }
        return $tab;

        // CONTROLE SI LE CUMUL TOTAL DE LA PERIOD PRECEDENTE EST INFERIEUR CELUI DE LA PERIODE SUIVANTE - COMMENCE ICI
        /** */


        /*
        $rank = new DistributorRankService();
        $calculator = new GradeCalculator();

        // ... dans votre méthode ...
        $period = '2024-11'; // Assurez-vous que cette valeur est correcte

        // Logguer la période pour être sûr
        Log::debug("Exécution de la requête pour la période : [{$period}]");
        // dd($period); // Décommenter pour stopper et vérifier interactivement

        try {
            $distribs = Distributeur::join(
                    'level_current_tests',
                    // --- CORRECTION : Revenir à la jointure sur le matricule ---
                    'level_current_tests.distributeur_id',
                    '=',
                    'distributeurs.distributeur_id'
                    // --- FIN CORRECTION ---
                )
                ->select( // Garder les alias pour la clarté
                    'distributeurs.distributeur_id',
                    'level_current_tests.etoiles',
                    'distributeurs.etoiles_id',
                    'level_current_tests.cumul_individuel',
                    'level_current_tests.cumul_collectif'
                    // 'distributeurs.id as distributeur_pk_id' // Optionnel
                )
                // Comparaison des niveaux d'étoiles
                ->whereColumn('distributeurs.etoiles_id', '<', 'level_current_tests.etoiles')
                // Filtrer par période
                ->where('level_current_tests.period', $period)
                ->get();

            // Logguer le nombre de résultats
            Log::debug("Nombre de distributeurs trouvés : " . $distribs->count());

        } catch (\Exception $e) {
            // Capturer et logguer toute exception potentielle
            Log::error("Erreur lors de l'exécution de la requête : " . $e->getMessage());
            // Retourner une collection vide ou gérer l'erreur comme il convient
            return collect();
        }


        foreach ($distribs as $distrib) {

            $countChildren = $rank->checkMultiLevelQualificationSeparateCountsMatricule($distrib->distributeur_id, $distrib->etoiles_id);
            $countChildrenpass1 = $countChildren['level_n_qualified_count'];
            $countChildrenpass2 = $countChildren['level_n_minus_1_qualified_count'];
            $newPotentialLevel = $calculator->calculatePotentialGrade($distrib->etoiles_id, $distrib->cumul_individuel, $distrib->cumul_collectif, $countChildrenpass1,  $countChildrenpass2);

            if($newPotentialLevel > $distrib->etoiles_id)
            {
                $distrib_change = Distributeur::where('distributeur_id', $distrib->distributeur_id)->first();
                $distrib_change->etoiles_id = $newPotentialLevel;
                $distrib_change->update();
                $tab[] = array(
                    'distributeur_id' => $distrib->distributeur_id,
                    'etoiles_actuel' => $distrib->etoiles_id,
                    'etoiles_avancement' => $newPotentialLevel,
                    'count_enfants' => $countChildren
                );
            }
            else {
                $level_change = Level_current_test::where('distributeur_id', $distrib->distributeur_id)->where('period', $period)->first();
                $level_change->etoiles = $newPotentialLevel;
                $level_change->update();
                $tab[] = array(
                    'distributeur_id' => $distrib->distributeur_id,
                    'etoiles_actuel' => $distrib->etoiles_id,
                    'etoiles_avancement' => $newPotentialLevel,
                    'status' => 'Aucun changement',
                    'count_enfants' => $countChildren
                );
            }
        }

        return $tab;

        /*

        $period = '2024-06';
        $eternalhelpers = new EternalHelper();

        $distribinfos = Level_current_test::where('period', $period)->get();

        foreach ($distribinfos as $key => $value) {

            $distrib = Distributeur::where('distributeur_id', $value->distributeur_id)->first();
            if($distrib)
            {
                if($value->etoiles > $distrib->etoiles_id)
                {
                    $distrib->etoiles_id = $value->etoiles;
                    $distrib->update();

                    $tab[] = array(
                        'distributeur_id' => $distrib->distributeur_id,
                        'etoiles_id' => $distrib->etoiles_id,
                        'etoiles' => $value->etoiles,
                    );
                }
            }
        }
        return $tab;
        /*
        $level_06 = Level_current_test::where('period', '2024-06')->get();

        foreach ($level_06 as $lev06) {

                $level = new Level_current_test();
                $level->period = $period;
                $level->distributeur_id = $lev06->distributeur_id;
                $level->etoiles = 1;
                $level->cumul_individuel = 0;
                $level->new_cumul = 0;
                $level->cumul_total = 0;
                $level->cumul_collectif = 0;
                $level->id_distrib_parent = $lev06->id_distrib_parent;
                $level->created_at = Carbon::parse($period.'-05');
                $level->save();
        }
        return 'periode '.$period.' inséré avec succès';

        //
        /*

        $level_06 = Level_current_test::where('period', '2024-05')->offset(6000)->limit(2000)->get();
        //return $level_06;
        foreach ($level_06 as $value) {

            $level_05 = Level_current_test::where('distributeur_id', $value->distributeur_id)->where('period', $period)->first();
            if($level_05)
            {
                $level_05->etoiles = $value->etoiles;
                $level_05->cumul_individuel	 = $value->cumul_individuel;
                $level_05->new_cumul = 0;
                $level_05->cumul_total = 0;
                $level_05->cumul_collectif = $value->cumul_collectif;
                $level_05->update();
            }
            else{
                $level_ = new Level_current_test();
                $level_->period = $period;
                $level_->distributeur_id = $value->distributeur_id;
                $level_->etoiles = 1;
                $level_->cumul_individuel = 0;
                $level_->new_cumul = 0;
                $level_->cumul_total = 0;
                $level_->cumul_collectif = 0;
                $level_->id_distrib_parent = $value->id_distrib_parent;
                $level_->created_at = Carbon::parse($period.'-05');
                $level_->save();
            }
        }

        return 'periode '.$period.' modifié avec succès';
        /*
        $achat = Achat::selectRaw('distributeur_id, SUM(pointvaleur) as new_achats, period, id_distrib_parent')
                        ->where('period', $period)
                        ->groupBy('distributeur_id')
                        //->where('distributeur_id', '2221847')
                        ->get();
        //return $achat;
        foreach ($achat as $val) {

            $level = Level_current_test::where('distributeur_id', $val->distributeur_id)->where('period', $period)->first();

            if($level)
            {
                $etoiles = $eternalhelpers->avancementGrade($level->distributeur_id, $level->etoiles, $val->new_achats, $val->new_achats);

                $level->etoiles = $etoiles;
                $level->cumul_individuel = $level->cumul_individuel + $val->new_achats;
                $level->new_cumul = $val->new_achats;
                $level->cumul_total = $val->new_achats;
                $level->cumul_collectif = $level->cumul_collectif + $val->new_achats;
                $level->update();

            }
            else {

                $etoiles = $eternalhelpers->avancementGrade($val->distributeur_id, 1, $val->new_achats, $val->new_achats);
                $level_ = new Level_current_test();
                $level_->period = $period;
                $level_->distributeur_id = $val->distributeur_id;
                $level_->etoiles = $etoiles;
                $level_->cumul_individuel = $val->new_achats;
                $level_->new_cumul = $val->new_achats;
                $level_->cumul_total = $val->new_achats;
                $level_->cumul_collectif = $val->new_achats;
                $level_->id_distrib_parent = $val->id_distrib_parent;
                $level_->created_at = Carbon::parse($period.'-05');
                $level_->save();
            }
        }

        return 'periode '.$period.' new cumul ajouté avec succès';
        /**
        $achats = Achat::selectRaw('distributeur_id, id_distrib_parent, sum(pointvaleur) as new_achats')
                        ->groupBy('distributeur_id')
                        //->where('distributeur_id', '2224817')
                        ->where('period', $period)
                        //->toSql();
                        ->get();
        //return $achat;

        $lev = Level_current_test::where('period', $period)->get();

        foreach ($lev as $val) {
            $level = Level_current_test::where('distributeur_id', $val->distributeur_id)->where('period', $period)->first();
            $etoiles = $eternalhelpers->avancementGrade($val->distributeur_id, $val->etoiles, $val->cumul_individuel, $val->cumul_collectif);

            $level->etoiles = $etoiles;
            $level->update();
        }

        return 'periode '.$period.' etoiles mis à jour avec succès';

        /**/

        //POUR AJOUTER LES DISTRIBUTEURS PRESENT DANS UNE TABLE MAIS ABSENT DANS UNE AUTRE TABLE
        /*
        return $this->comparatifTab('2024-10', '2024-11');
        // Calcule le gain apporter aux réseau du parrain, après insertion
        // des bons d'achats des distributeurs dans la base de donnée

        //AJOUTER LE CUMUL DU MOIS DES BONS D'ACHATS AU DISTRIBUTEUR CONCERNE ID INDIVIDUEL
        //LE VRAI ICI
        /*
        $period = '2024-09';
        $achats = Achat::selectRaw('distributeur_id, sum(achats.pointvaleur) as new_achats, id_distrib_parent, period')
            ->groupBy('achats.distributeur_id')
            ->where('achats.period', $period)
            //->toSql();
            ->get();
        //return $achats;
        $eternalhelpers = new EternalHelper();
        foreach ($achats as $val) {

            $level = Level_current_test::where('distributeur_id', $val->distributeur_id)->where('period', $period)->first();
            if($level)
            {
                $cumul_total = $val->new_achats;
                $cumul_individuel = $level->cumul_individuel + $val->new_achats;
                $cumul_collectif = $level->cumul_collectif + $val->new_achats;
                $etoiles = $eternalhelpers->avancementGrade($level->distributeur_id, $level->etoiles, $cumul_individuel, $cumul_collectif);

                $level->etoiles = $etoiles;
                $level->cumul_individuel = $cumul_individuel;
                $level->new_cumul = $val->new_achats;
                $level->cumul_total = $cumul_total;
                $level->cumul_collectif = $cumul_collectif;
                $level->update();

                $distrib = Distributeur::where('distributeur_id', $val->distributeur_id)->first();
                $distrib->etoiles_id = $etoiles;
                $distrib->update();

                $children[] = array(
                    'etoile' => $etoiles,
                    'distributeur_id' => $level->distributeur_id,
                    'cumul_individuel' => $level->cumul_individuel.' + '.$val->new_achats.' = '.$cumul_individuel,
                    'new_cumul' => $val->new_achats,
                    'cumul_total' => $level->cumul_total.' + '.$val->new_achats.' = '.$cumul_total,
                    'cumul_collectif' =>  $level->cumul_collectif.' + '.$val->new_achats.' = '.$level->cumul_collectif+$val->new_achats,
                    'response' =>  'insertion effectuée avec succès'
                );
            }
            else {
                $levels = Distributeur::where('distributeur_id', $val->distributeur_id)->first();

                $cumul_total = $val->new_achats;
                $cumul_individuel = $val->new_achats;
                $cumul_collectif = $val->new_achats;
                $etoiles = $eternalhelpers->avancementGrade($levels->distributeur_id, $levels->etoiles_id, $cumul_individuel, $cumul_collectif);

                $levels->etoiles_id = $etoiles;
                $levels->update();

                try {
                    Level_current_test::insert([
                        'distributeur_id' => $levels->distributeur_id,
                        'period' => $period,
                        'rang' => 0,
                        'etoiles' => $etoiles,
                        'cumul_individuel' => $cumul_individuel,
                        'new_cumul' => $val->new_achats,
                        'cumul_total' => $cumul_total,
                        'cumul_collectif' => $cumul_collectif,
                        'id_distrib_parent' => $levels->id_distrib_parent,
                        'created_at' => $levels->created_at
                    ]);
                }
                catch(\Illuminate\Database\QueryException $exept){
                    dd($exept->getMessage());
                }

                $children[] = array(
                    'etoile' => $etoiles,
                    'distributeur_id' => $levels->distributeur_id,
                    'cumul_individuel' => $levels->cumul_individuel.' + '.$val->new_achats.' = '.$cumul_individuel,
                    'new_cumul' => $val->new_achats,
                    'cumul_total' => $levels->cumul_total.' + '.$val->new_achats.' = '.$cumul_total,
                    'cumul_collectif' =>  $levels->cumul_collectif.' + '.$val->new_achats.' = '.$cumul_collectif,
                    'response' =>  'DEPUIS LEVELS'
                );
            }
        }

        return $children;
        */

    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
        echo 'bonjour';
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

    public function comparatifTab($period1, $period2)
    {
        $comparatif = Level_current_test::where('period', $period2)->get();
        foreach ($comparatif as $value) {
            $tab2[] = $value->distributeur_id;
        }

        $level_comp = Level_current_test::where('period', $period1)->whereNotIn('distributeur_id', $tab2)->get();
        return $level_comp;

        foreach ($level_comp as $value) {
            $h = $value->created_at->format('Y-m');
            $inf = Carbon::parse($h)->lte($period2);
            $lastAchatPeriod = Carbon::createFromFormat('Y-m', $period2);
            $diffmonths = $lastAchatPeriod->diffInMonths(Carbon::createFromFormat('Y-m', $period2));
            if($inf)
            {
                $achat = Achat::selectRaw('sum(pointvaleur) as new_cumul')->where('distributeur_id', $value->distributeur_id)->where('period', $period2)->first();
                if($achat) {

                    $level_insert = new Level_current_test();
                    $level_insert->period = $period2;
                    $level_insert->distributeur_id = $value->distributeur_id;
                    $level_insert->etoiles = $value->etoiles;
                    $level_insert->cumul_individuel = $value->cumul_individuel + $achat->new_cumul ?? 0;
                    $level_insert->new_cumul = $achat->new_cumul ?? 0;
                    $level_insert->cumul_total = $achat->new_cumul ?? 0;
                    $level_insert->cumul_collectif = $value->cumul_collectif + $achat->new_cumul ?? 0;
                    $level_insert->id_distrib_parent = $value->id_distrib_parent;
                    $level_insert->created_at = Carbon::parse($period2.'-05');
                    $level_insert->save();

                    $level_inserted[] = array(
                        'distributeur_id' => $value->distributeur_id,
                        'period' => $period2,
                        'rang' => 0,
                        'etoiles' => $value->etoiles,
                        'cumul_individuel' => $value->cumul_individuel + $achat->new_cumul ?? 0,
                        'new_cumul' => $achat->new_cumul ?? 0,
                        'cumul_total' => $achat->new_cumul ?? 0,
                        'cumul_collectif' => $value->cumul_collectif + $achat->new_cumul ?? 0,
                        'id_distrib_parent' => $value->id_distrib_parent,
                        'created_at' => $value->created_at
                    );
                }
            }
            else {
                $level_inserted[] = array(
                    'distributeur_id' => $value->distributeur_id,
                    'period' => Carbon::parse($period2),
                    'created_at' => $value->created_at,
                    'info' => 'le distributeur n\'existe pas encore'
                );
            }
            /*
            try {
                Level_current_test::insert([

                    'distributeur_id' => $value->distributeur_id,
                    'period' => Carbon::parse($value->created_at)->format('Y-m'),
                    'rang' => 0,
                    'etoiles' => $value->etoiles_id,
                    'cumul_individuel' => 0,
                    'new_cumul' => 0,
                    'cumul_total' => 0,
                    'cumul_collectif' => 0,
                    'id_distrib_parent' => $value->id_distrib_parent,
                    'created_at' => $value->created_at
                ]);
            }
            catch(\Illuminate\Database\QueryException $exept){
                dd($exept->getMessage());
            }
            /**/
        }
        return $level_inserted;
    }

    public function getParentNetwork($parent, $cumul, $period)
    {
         $children = [];

        //$childinfos = Distributeur::join('levels', 'levels.distributeur_id', '=', 'distributeurs.distributeur_id')->get(['distributeurs.*', 'levels.*']);
        $childDistributeurs = Level_current_test::where('distributeur_id', $parent)->where('period', $period)->get();

        foreach ($childDistributeurs as $child)
        {
            /*
            $achatsInsert = Level_current_test::where('distributeur_id', $child->distributeur_id)->first();
            $achatsInsert->cumul_total = $child->cumul_total + $cumul;
            $achatsInsert->cumul_collectif = $child->cumul_collectif + $cumul;
            $achatsInsert->update();
            */
            $children[] = array(
                'id' => $child->distributeur_id,
                'new_cumul' => $cumul,
                'cumul_total' => $child->cumul_total.' + '.$cumul.' = '.$child->cumul_total+$cumul,
                'cumul_collectif' =>  $child->cumul_collectif.' + '.$cumul.' = '.$child->cumul_collectif+$cumul,
                'response' =>  'insertion effectuée avec succès'
            );

            $children[] = $this->getParentNetwork($child->id_distrib_parent, $cumul, $period);
        }
        return $children;//Arr::flatten($children);
    }


    public function addCumulToParains($period)
    {
        $achatsAll = Achat::selectRaw("distributeur_id, id_distrib_parent")
            //->where('distributeur_id', $id)
            ->where('period', $period)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') AS new_date")
            ->selectRaw("sum(pointvaleur) as new_cumul")
            ->groupBy('distributeur_id')
            //->limit(10)
            ->get();

        foreach ($achatsAll as $achats) {

            if( $achats != null)
            {
                $pedigre[] = $this->getParentNetwork($achats->id_distrib_parent, $achats->new_cumul, $period);
            }
        }

        return $pedigre;
    }

    public function CalculCumlIndividuel($disitributeurId, $parentDistributeurId, $cumul_collectif, $cumul_individuel, $new_cumul, $period)
    {
        $children = [];
        $individuelTab = [];
        $total = 0;
        $reste = 0;
        $childDistributeurs = Distributeur::where('id_distrib_parent', $disitributeurId)->get();
        $total = Level_current_test::selectRaw('SUM(cumul_collectif) as collectif')->where('id_distrib_parent', $disitributeurId)->where('period', $period)->get();
        $level = Level_current_test::where('distributeur_id', $disitributeurId)->where('period', $period)->first();
        //return $level;

        $nbrChildren = count($childDistributeurs);

        if($nbrChildren > 0)
        {
            //return $childDistributeurs;
            $reste = $level->cumul_collectif - $total[0]->collectif;
            if($level->cumul_individuel != $reste)
            {
                $individuelTab[] = array(
                    'cas' => '1er cas standard',
                    'period' => $period,
                    'distributeur_id' => $disitributeurId,
                    'id_distrib_parent' => $parentDistributeurId,
                    'cumul_individuel' => $level->cumul_individuel,
                    'cumul_collectif' => $level->cumul_collectif,
                    'cumul_enfants_collectif' => $total[0]->collectif,
                    'reste' => $reste,
                    //'response' => 'cumul_individuel ajouter',
                );
            }
            /*
            $updater = Level_current_test::where('distributeur_id', $disitributeurId)->where('period', $period)->first();
            $updater->cumul_individuel = $reste;
            $updater->update();
            */
            foreach ($childDistributeurs as $child)
            {
                $individuelTab[] = $this->CalculCumlIndividuel($child->distributeur_id, $child->id_distrib_parent, $level->cumul_collectif,  $level->cumul_individuel, $level->new_cumul, $period, $children);
            }
            /*
            else {
                $reste = $total - $cumul_parent;
            }*/


        }

        else {
            if($cumul_collectif > $new_cumul){

                $reste = $cumul_collectif;
                /*
                $updater = Level_current_2024_02::where('distributeur_id', $disitributeurId)->first();
                $updater->cumul_individuel = $reste;
                $updater->update();
                */
                $individuelTab[] = array(
                    'cas' => '2eme cas pas de filleul, mais fait des achats',
                    'period' => $period,
                    'distributeur_id' => $disitributeurId,
                    'cumul_collectif' => $cumul_collectif,
                    'cumul_enfants_collectif' => $total[0]->collectif,
                    'cumul_individuel' => $reste,
                    //'response' => 'cumul_individuel ajouter',
                );
            }

            elseif($cumul_collectif <= $new_cumul)
            {
                /*
                $updater = Level_current_2024_02::where('distributeur_id', $disitributeurId)->first();
                $updater->cumul_individuel = $reste;
                $updater->update();
                */
                $individuelTab[] = array(
                    'cas' => '3eme cas pas de filleul, 1er achat',
                    'period' => $period,
                    'distributeur_id' => $disitributeurId,
                    'cumul_collectif' => $cumul_collectif,
                    'new_cumul' => $new_cumul,
                    'cumul_enfants_collectif' => $total[0]->collectif,
                    'cumul_individuel' => $reste,
                    //'response' => 'cumul_individuel ajouter',
                );

            }
        }

       return $individuelTab;

    }


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

    public function getChildrenNetworkAdvance($disitributeurId, $level, $i)
    {
        $children = [];
        $nbr = 0;
        $allChildren = Distributeur::where('id_distrib_parent', $disitributeurId)->get();
        if($i > 0)
        {
            foreach ($allChildren as $value) {
                if($value->etoiles_id >= $level)
                {
                    $children[] = array(
                        'isit' => 1
                    );
                }
                else {
                    $children[] = array(
                        'isit' => $this->getChildrenNetworkAdvanceLoup($value->distributeur_id, $level)
                    );
                    //$children[] = $this->array_flatten($temp);
                }
                //$tab_etoiles[] = $this->mapRecursive($child, $regul->etoiles);
            }
        }
        else {
            foreach ($allChildren as $value) {
                if($value->etoiles_id == ($level-1))
                {
                    $children[] = array(
                        'isit' => 1
                    );
                    continue;
                }
                else {
                    $children[] = array(
                        'isit' => $this->getChildrenNetworkAdvanceLoup($value->distributeur_id, ($level-1))
                    );
                    //$children[] = $this->array_flatten($temp);
                }
                //$tab_etoiles[] = $this->mapRecursive($child, $regul->etoiles);
            }
        }
        return Arr::flatten($children);
    }

    public function getChildrenNetworkAdvanceLoup($disitributeurId, $level)
    {
        $children = [];
        $nbr = 0;
        $allChildren = Distributeur::where('id_distrib_parent', $disitributeurId)->get();

        foreach ($allChildren as $value) {
            if($value->etoiles_id == $level)
            {
                $children[] = array(
                    'isit' => 1
                );
                //exit();
            }
            else {
                $children[] = array(
                    'isit' => $this->getChildrenNetworkAdvanceLoup($value->distributeur_id, $level)
                );
                //$children[] = $this->array_flatten($temp);
            }
            //$tab_etoiles[] = $this->mapRecursive($child, $regul->etoiles);
        }
        $nbr = array_sum(Arr::flatten($children));
        if($nbr > 0) $nbr = 1;
        return $nbr;
    }

    public function getChildrenNetworkTest($disitributeurId, $cumul_collectif, $period)
    {
        $children = [];

        $Level = Level_current_test::where('id_distrib_parent', $disitributeurId)->where('period', $period)->get();
        if($Level->sum('cumul_collectif') > $cumul_collectif)
        {
            foreach ($Level as $child)
            {
                $children[] = array(
                    'id' => $child->distributeur_id,
                    'etoiles' => $child->etoiles_id,
                    'children' => $this->getChildrenNetworkTest($child->distributeur_id, $child->cumul_collectif, $period, $children)
                );
                //$children[] = $this->array_flatten($temp);
            }
        }

        return $children;//Arr::flatten($children);
    }

    public function getChildrenNetwork($disitributeurId, $level)
    {
        $children = [];

        $childDistributeurs = Distributeur::where('id_distrib_parent', $disitributeurId)->get();
        $level++;
        foreach ($childDistributeurs as $child)
        {
            $children[] = array(
                'tour' => $level,
                'id' => $child->distributeur_id,
                'etoiles' => $child->etoiles_id,
                'children' => $this->getChildrenNetwork($child->distributeur_id, $level, $children)
            );
            //$children[] = $this->array_flatten($temp);
        }
        return $children;//Arr::flatten($children);
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

    public function getChilrenAvancement($disitributeurId, $rang, $period)
    {
        $children = [];
        $nbr = 0;
        $count = [];
        //$children[] = $disitributeurId;
        $parentDistributeurs = Level_current_test::where('id_distrib_parent', $disitributeurId)->where('period', $period)->get();

        foreach ($parentDistributeurs as $parent)
        {
            $childDistributeurs = Level_current_test::where('id_distrib_parent', $parent->distributeur_id)->where('period', $period)->where('etoiles', '>=', $rang)->get();
            $direct = $childDistributeurs->count();
            if($direct > 0){
                $nbr = $nbr + $direct;
                $count[] = $nbr;
            }
            else {
                $children[] = $this->getChilrenAvancement($parent->distributeur_id, $rang, $period, $children);
            }
        }

        return $count;
    }

    public function mapRecursive($array, $rang) {
        $result = [];
        $compt = 0;
        foreach ($array as $item) {
            if($item['etoiles'] >= $rang)
            $result = [1];
            else
            //$result[] = $item['etoiles'];
            $result = array_merge($result, $this->mapRecursive($item['children'], $rang));
        }
        return array_filter($result);
    }

    public function getChildrenEtoiles($disitributeurId, $rang)
    {
        $children = [];
        $nbr = 0;
        $count = [];

        $parentDistributeurs = Distributeur::where('id_distrib_parent', $disitributeurId)->get();
        return $parentDistributeurs;
        foreach ($parentDistributeurs as $parent)
        {
            $childDistributeurs = Distributeur::where('id_distrib_parent', $parent->distributeur_id)->where('etoiles_id', '>=', $rang)->get();
            $direct = $childDistributeurs->count();
            if($direct > 0){
                $nbr = $nbr + $direct;
                $count[] = $nbr;
            }
            else {
                $children[] = $this->getChildrenEtoiles($parent->distributeur_id, $rang, $children);
            }
        }


        return $count;
    }


    function calculAvancementDistributeurDebug($distributeur_id, $etoiles, $cumul_individuel, $cumul_collectif, $period)
    {
        switch($etoiles)
        {
            case 1:
                //return 'Passage du niveau 1* au niveau 2*';
                $etoiles_individuel = Etoile::where('cumul_individuel', '>=', 100)->where('cumul_individuel', '<=', $cumul_individuel)->latest()->first();
                if($etoiles_individuel){
                    return $etoiles_individuel->etoile_level;
                }
                else {
                    return $etoiles;
                }

            break;
            case 2:
                //return 'Passage du niveau 2* au niveau 3*';
                $etoiles_individuel = Etoile::where('cumul_individuel', '>=', 200)->where('cumul_individuel', '<=', $cumul_individuel)->latest()->first();
                if($etoiles_individuel){
                    return $etoiles_individuel->etoile_level;
                }
                else {
                    return $etoiles;
                }

            break;
            case 3:
                //return 'Passage du niveau 3* au niveau supérieur';
                return $etoiles;
            break;
            case 4:
                //return 'Passage du niveau 4* au niveau supérieur';

                $parentDirect = Distributeur::where('id_distrib_parent', $distributeur_id)->where('etoiles_id', '>=', $etoiles)->get();
                if($parentDirect->count() >= 2)
                {
                    $etoiles = 5;
                    return $etoiles;
                }
                else {
                    return $etoiles;
                }
            break;
                default: return $etoiles;
        }
    }


    public function calculAvancementDistributeur($distributeur_id, $etoiles, $cumul_individuel, $cumul_collectif, $period)
    {
        $final_result = [];
        $tab_etoiles = [];
        $nb = 0;
        $etoiles_requis = Etoile::orderBy('etoile_level', 'DESC')->get();
        $childrenVerific = Distributeur::where('id_distrib_parent', $distributeur_id)->get();

        foreach ($etoiles_requis as $value) {

            if($cumul_collectif >= $value->cumul_collectif_2)
            {
                $rang_1 = $value->etoile_level-1;
                $rang_2 = $value->etoile_level-2;
                for($i=0; $i < $childrenVerific->count(); $i++)
                {
                    $child = $this->getChildrenNetwork($childrenVerific[$i]->distributeur_id, 0);
                    //return $child;
                    $tab_etoiles_1[] = $this->mapRecursive($child, $rang_1);
                    $tab_etoiles_2[] = $this->mapRecursive($child, $rang_2);
                    $childTab[] = $child;
                }
                $tab_1 = Arr::flatten($tab_etoiles_1);
                $tab_2 = Arr::flatten($tab_etoiles_2);
                $nbChild_1 = array_sum($tab_1);
                $nbChild_2 = array_sum($tab_2);

                return $tab_etoiles_2;

                if($cumul_collectif >= $value->cumul_collectif_1)
                {
                    return ['stage 1', $etoiles, $cumul_collectif, $value];
                    /*


                $rang_1 = $value->etoile_level-1;
                $rang_2 = $value->etoile_level-2;
                for($i=0; $i < $childrenVerific->count(); $i++)
                {
                    $child = $this->getChildrenNetwork($childrenVerific[$i]->distributeur_id, 0);
                    $tab_etoiles_1[] = $this->mapRecursive($child, $rang_1);
                    $tab_etoiles_2[] = $this->mapRecursive($child, $rang_2);
                }
                $tab_1 = Arr::flatten($tab_etoiles_1);
                $tab_2 = Arr::flatten($tab_etoiles_2);
                $nbChild_1 = array_sum($tab_1);
                $nbChild_2 = array_sum($tab_2);

                if($cumul_collectif >= $value->cumul_collectif_1)
                {
                    return ['stage 1', $etoiles, $cumul_collectif, $value];
                    if($nbChild_1 >= $value->nb_child_1)
                    {
                        return ['cas 1-1', $value->etoile_level, $rang_1, $rang_2, $etoiles, $nbChild_1, $nbChild_2];
                    }
                    elseif($nbChild_1 >= 1)
                    {
                        if($nbChild_2 >= $value->nb_child_4)
                        {
                            return ['cas 1-2', $value->etoile_level];
                        }
                        else {
                            return ['cas 1-3', $etoiles];
                        }
                    }
                }
                else {
                    return ['stage 2', $etoiles, $cumul_collectif, $value];

                }
                */
                }
                else {
                    return [
                        'for debogage',
                        'Level'=>'stage 2',
                        'distributeur_id'=>$distributeur_id,
                        'etoiles_id'=>$etoiles,
                        'cumul_collectif'=>$cumul_collectif,
                        'etoiles info'=>$value,
                        'Rang 1'=>$rang_1,
                        'Rang 2'=>$rang_2,
                        'nb de '.$rang_1=>$nbChild_1,
                        'nb de '.$rang_2=>$nbChild_2,
                        'childrenEtoiles'=>$childrenVerific
                    ];

                    if($nbChild_1 >= $value->nb_child_2)
                    {
                        return ['cas 2-1', $value->etoile_level];
                    }
                    elseif($nbChild_1 >= $value->nb_child_1)
                    {
                        if($nbChild_2 >= $value->nb_child_3)
                        {
                            return ['cas 2-2', $value->etoile_level];
                        }
                        elseif($nbChild_1 >= 1) {
                            if($nbChild_2 >= $value->nb_child_4)
                            {
                                return ['cas 2-3', $value->etoile_level];
                            }
                            else {
                                return ['cas 2-4', $etoiles];
                            }
                        }
                    }
                }
            }
        }

        return ['cas 3', $etoiles];

        /*
        foreach ($childrenVerific as $element) {
            if($element->etoiles_id >= $etoiles)
            {

            }
            $child = $this->getChildrenNetwork($element->distributeur_id, 0);
            //$tab_etoiles[] = $child;
            //return $child;
            $tab_etoiles[] = $this->mapRecursive($child, $etoiles);
        }
        $tab = Arr::flatten($tab_etoiles);
        $nbChild = array_sum($tab);
        return $tab;
        */
        return $etoiles_requis;

        $cumul_1 = Etoile::where('cumul_collectif_1', 780000)->first();
        $cumul_2 = Etoile::where('cumul_collectif_2', 400000)->first();
        $cumul_3 = Etoile::where('cumul_collectif_1', 580000)->first();
        $cumul_4 = Etoile::where('cumul_collectif_2', 280000)->first();
        $cumul_5 = Etoile::where('cumul_collectif_1', 145000)->first();
        $cumul_6 = Etoile::where('cumul_collectif_2', 73000)->first();
        $cumul_7 = Etoile::where('cumul_collectif_1', 35000)->first();
        $cumul_8 = Etoile::where('cumul_collectif_2', 16000)->first();
        $cumul_9 = Etoile::where('cumul_collectif_1', 7800)->first();
        $cumul_10 = Etoile::where('cumul_collectif_2', 3800)->first();
        $cumul_11 = Etoile::where('cumul_collectif_1', 2200)->first();
        $cumul_12 = Etoile::where('cumul_collectif_2', 1000)->first();


        switch($etoiles)
        {
            case 1:
                //return 'Passage du niveau 1* au niveau 2*';
                $etoiles_individuel = Etoile::where('cumul_individuel', '>=', 100)->where('cumul_individuel', '<=', $cumul_individuel)->latest()->first();
                if($etoiles_individuel){
                    return $etoiles_individuel->etoile_level;
                }
                else {
                    return $etoiles;
                }

            break;
            case 2:
                //return 'Passage du niveau 2* au niveau 3*';
                $etoiles_individuel = Etoile::where('cumul_individuel', '>=', 200)->where('cumul_individuel', '<=', $cumul_individuel)->latest()->first();
                if($etoiles_individuel){
                    return $etoiles_individuel->etoile_level;
                }
                else {
                    return $etoiles;
                }

            break;
            case 3:
                //return 'Passage du niveau 3* au niveau supérieur';
                if(count($childrenVerif_0) >= 3)
                {
                    $etoile = 11;
                    return $etoile;
                }
                elseif(count($childrenVerif_0) >= 2)
                {
                    $etoile = 10;
                    return $etoile;
                }
                elseif($cumul_collectif >= $cumul_1->cumul_collectif_1)
                {
                    if(count($childrenVerif_1) >= 2)
                    {
                        $etoile = $cumul_1->etoile_level;
                        return $etoile;
                    }
                }
                elseif($cumul_collectif >= $cumul_2->cumul_collectif_2)
                {
                    if($childrenVerif_1)
                    {
                        if(count($childrenVerif_1) >= 3)
                        {
                            $etoile = $cumul_2->etoile_level;
                            return $etoile;
                        }
                        else {
                            if(count($childrenVerif_1) >= 2 && count($childrenVerif_2) >= 4)
                            {
                                $etoile = $cumul_2->etoile_level;
                                return $etoile;
                            }
                            elseif(count($childrenVerif_1) >= 1 && count($childrenVerif_2) >= 6)
                            {
                                $etoile = $cumul_2->etoile_level;
                                return $etoile;
                            }
                        }
                    }
                }
                elseif($cumul_collectif >= $cumul_3->cumul_collectif_1)
                {
                    if($childrenVerif_2)
                    {
                        if(count($childrenVerif_2) >= 2)
                        {
                            $etoile = $cumul_3->etoile_level;
                            return $etoile;
                        }
                    }
                }
                elseif($cumul_collectif >= $cumul_4->cumul_collectif_2)
                {
                    if($childrenVerif_2)
                    {
                        if(count($childrenVerif_2) >= 3)
                        {
                            $etoile = $cumul_4->etoile_level;
                            return $etoile;
                        }
                        else {
                            if(count($childrenVerif_2) >= 2 && count($childrenVerif_3) >= 4)
                            {
                                $etoile = $cumul_4->etoile_level;
                                return $etoile;
                            }
                            elseif(count($childrenVerif_2) >= 1 && count($childrenVerif_3) >= 6)
                            {
                                $etoile = $cumul_4->etoile_level;
                                return $etoile;
                            }
                        }
                    }
                }
                elseif($cumul_collectif >= $cumul_5->cumul_collectif_1)
                {
                    if($childrenVerif_2)
                    {
                        if(count($childrenVerif_2) >= 2)
                        {
                            $etoile = $cumul_5->etoile_level;
                            return $etoile;
                        }
                    }
                }
                elseif($cumul_collectif >= $cumul_6->cumul_collectif_2)
                {
                    if($childrenVerif_3)
                    {
                        if(count($childrenVerif_2) >= 3)
                        {
                            $etoile = $cumul_6->etoile_level;
                            return $etoile;
                        }
                        else {
                            if(count($childrenVerif_3) >= 2 && count($childrenVerif_4) >= 4)
                            {
                                $etoile = $cumul_6->etoile_level;
                                return $etoile;
                            }
                            elseif(count($childrenVerif_3) >= 1 && count($childrenVerif_4) >= 6)
                            {
                                $etoile = $cumul_6->etoile_level;
                                return $etoile;
                            }
                        }
                    }
                }
                elseif($cumul_collectif >= $cumul_7->cumul_collectif_1)
                {
                    if($childrenVerif_2)
                    {
                        if(count($childrenVerif_2) >= 2)
                        {
                            $etoile = $cumul_7->etoile_level;
                            return $etoile;
                        }
                    }
                }
                elseif($cumul_collectif >= $cumul_8->cumul_collectif_2)
                {
                    if($childrenVerif_2)
                    {
                        if(count($childrenVerif_2) >= 3)
                        {
                            $etoile = $cumul_8->etoile_level;
                            return $etoile;
                        }
                        else {
                            if(count($childrenVerif_2) >= 2 && count($childrenVerif_3) >= 4)
                            {
                                $etoile = $cumul_8->etoile_level;
                                return $etoile;
                            }
                            elseif(count($childrenVerif_2) >= 1 && count($childrenVerif_3) >= 6)
                            {
                                $etoile = $cumul_8->etoile_level;
                                return $etoile;
                            }
                        }
                    }
                }
                elseif($cumul_collectif >= $cumul_9->cumul_collectif_1)
                {
                    if($childrenVerif_2)
                    {
                        if(count($childrenVerif_2) >= 2)
                        {
                            $etoile = $cumul_9->etoile_level;
                            return $etoile;
                        }
                    }
                }
                elseif($cumul_collectif >= $cumul_10->cumul_collectif_2)
                {
                    if($childrenVerif_2)
                    {
                        if(count($childrenVerif_2) >= 3)
                        {
                            $etoile = $cumul_10->etoile_level;
                            return $etoile;
                        }
                        else {
                            if(count($childrenVerif_2) >= 2 && count($childrenVerif_3) >= 4)
                            {
                                $etoile = $cumul_10->etoile_level;
                                return $etoile;
                            }
                            elseif(count($childrenVerif_2) >= 1 && count($childrenVerif_3) >= 6)
                            {
                                $etoile = $cumul_10->etoile_level;
                                return $etoile;
                            }
                        }
                    }
                }
                elseif($cumul_collectif >= $cumul_11->cumul_collectif_1)
                {
                    if($childrenVerif_3)
                    {
                        if(count($childrenVerif_3) >= 2)
                        {
                            $etoile = $cumul_11->etoile_level;
                            return $etoile;
                        }
                    }
                }
                elseif($cumul_collectif >= $cumul_12->cumul_collectif_2)
                {
                    if($childrenVerif_3)
                    {
                        if(count($childrenVerif_3) >= 3)
                        {
                            $etoile = $cumul_12->etoile_level;
                            return $etoile;
                        }
                    }
                    else {
                        $etoiles_individuel = Etoile::where('cumul_individuel', '>=', 1000)->where('cumul_individuel', '<=', $cumul_individuel)->latest()->first();
                        if($etoiles_individuel){
                            return $etoiles_individuel->etoile_level;
                        }
                        else {
                            return $etoiles;
                        }
                    }
                }
                else {
                    return $etoiles;
                }

            break;
            case 4:
                //return 'Passage du niveau 4* au niveau supérieur';
                if(count($childrenVerif_0) >= 3)
                {
                    $etoile = 11;
                    return $etoile;
                }
                elseif(count($childrenVerif_0) >= 2)
                {
                    $etoile = 10;
                    return $etoile;
                }
                elseif($cumul_collectif >= $cumul_1->cumul_collectif_1)
                {
                    if(count($childrenVerif_1) >= 2)
                    {
                        $etoile = $cumul_1->etoile_level;
                        return $etoile;
                    }
                }
                elseif($cumul_collectif >= $cumul_2->cumul_collectif_2)
                {
                    if($childrenVerif_1)
                    {
                        if(count($childrenVerif_1) >= 3)
                        {
                            $etoile = $cumul_2->etoile_level;
                            return $etoile;
                        }
                        else {
                            if(count($childrenVerif_1) >= 2 && count($childrenVerif_2) >= 4)
                            {
                                $etoile = $cumul_2->etoile_level;
                                return $etoile;
                            }
                            elseif(count($childrenVerif_1) >= 1 && count($childrenVerif_2) >= 6)
                            {
                                $etoile = $cumul_2->etoile_level;
                                return $etoile;
                            }
                        }
                    }
                }
                elseif($cumul_collectif >= $cumul_3->cumul_collectif_1)
                {
                    if($childrenVerif_2)
                    {
                        if(count($childrenVerif_2) >= 2)
                        {
                            $etoile = $cumul_3->etoile_level;
                            return $etoile;
                        }
                    }
                }
                elseif($cumul_collectif >= $cumul_4->cumul_collectif_2)
                {
                    if($childrenVerif_2)
                    {
                        if(count($childrenVerif_2) >= 3)
                        {
                            $etoile = $cumul_4->etoile_level;
                            return $etoile;
                        }
                        else {
                            if(count($childrenVerif_2) >= 2 && count($childrenVerif_3) >= 4)
                            {
                                $etoile = $cumul_4->etoile_level;
                                return $etoile;
                            }
                            elseif(count($childrenVerif_2) >= 1 && count($childrenVerif_3) >= 6)
                            {
                                $etoile = $cumul_4->etoile_level;
                                return $etoile;
                            }
                        }
                    }
                }
                elseif($cumul_collectif >= $cumul_5->cumul_collectif_1)
                {
                    if($childrenVerif_2)
                    {
                        if(count($childrenVerif_2) >= 2)
                        {
                            $etoile = $cumul_3->etoile_level;
                            return $etoile;
                        }
                    }
                }
                elseif($cumul_collectif >= $cumul_6->cumul_collectif_2)
                {
                    /**/
                    $tab_etoiles = [];
                    $nb = 0;
                    $childrenVerific = Distributeur::where('id_distrib_parent', $distributeur_id)->get();
                    //return $childrenVerific;

                    foreach ($childrenVerific as $element) {
                        $child = $this->getChildrenNetwork($element->distributeur_id, 0);
                        //$tab_etoiles[] = $child;
                        //return $child;
                        $tab_etoiles[] = $this->mapRecursive($child, $etoiles);
                    }
                    $tab = Arr::flatten($tab_etoiles);
                    return array_sum($tab);

                    if($result == false)
                    return 'raté';
                    else
                    return 'réussi';

                    if($childrenVerif_2)
                    {
                        if(count($childrenVerif_2) >= 3)
                        {
                            $etoile = $cumul_6->etoile_level;
                            return $etoile;
                        }
                        else {
                            if(count($childrenVerif_2) >= 2 && count($childrenVerif_3) >= 4)
                            {
                                $etoile = $cumul_6->etoile_level;
                                return $etoile;
                            }
                            elseif(count($childrenVerif_2) >= 1 && count($childrenVerif_3) >= 6)
                            {
                                $etoile = $cumul_6->etoile_level;
                                return $etoile;
                            }
                        }
                    }
                    elseif($childrenVerif_3) {
                        return [$childrenVerif_3, $cumul_6->cumul_collectif_2];
                    }
                    else {
                        return [$cumul_collectif, $cumul_6->cumul_collectif_2];
                    }
                }
                elseif($cumul_collectif >= $cumul_7->cumul_collectif_1)
                {
                    if($childrenVerif_2)
                    {
                        if(count($childrenVerif_2) >= 2)
                        {
                            $etoile = $cumul_3->etoile_level;
                            return $etoile;
                        }
                    }
                }
                elseif($cumul_collectif >= $cumul_8->cumul_collectif_2)
                {
                    if($childrenVerif_2)
                    {
                        if(count($childrenVerif_2) >= 3)
                        {
                            $etoile = $cumul_8->etoile_level;
                            return $etoile;
                        }
                        else {
                            if(count($childrenVerif_2) >= 2 && count($childrenVerif_3) >= 4)
                            {
                                $etoile = $cumul_8->etoile_level;
                                return $etoile;
                            }
                            elseif(count($childrenVerif_2) >= 1 && count($childrenVerif_3) >= 6)
                            {
                                $etoile = $cumul_8->etoile_level;
                                return $etoile;
                            }
                        }
                    }
                }
                elseif($cumul_collectif >= $cumul_9->cumul_collectif_1)
                {
                    if($childrenVerif_2)
                    {
                        if(count($childrenVerif_2) >= 2)
                        {
                            $etoile = $cumul_3->etoile_level;
                            return $etoile;
                        }
                    }
                }
                elseif($cumul_collectif >= $cumul_10->cumul_collectif_2)
                {
                    /**/

                    $tab_etoiles = [];
                    $nb = 0;
                    $childrenVerific = Distributeur::where('id_distrib_parent', $distributeur_id)->get();
                    return [$etoiles, $childrenVerific];

                    foreach ($childrenVerific as $element) {
                        $child = $this->getChildrenNetwork($element->distributeur_id, 0);
                        $tab_etoiles[] = $child;
                        return $child;
                        $tab_etoiles[] = $this->mapRecursive($child, $etoiles);
                    }
                    $tab = Arr::flatten($tab_etoiles);
                    return array_sum($tab);

                    if($childrenVerif_2)
                    {
                        if(count($childrenVerif_2) >= 3)
                        {
                            $etoile = $cumul_10->etoile_level;
                            return $etoile;
                        }
                        else {
                            if(count($childrenVerif_2) >= 2 && count($childrenVerif_3) >= 4)
                            {
                                $etoile = $cumul_10->etoile_level;
                                return $etoile;
                            }
                            elseif(count($childrenVerif_2) >= 1 && count($childrenVerif_3) >= 6)
                            {
                                $etoile = $cumul_10->etoile_level;
                                return $etoile;
                            }
                        }
                    }
                }
                else {
                    return $etoiles;
                }

            break;
            case 5:
                //return 'Passage du niveau 5* au niveau supérieur*';
                if(count($childrenVerif_0) >= 3)
                {
                    $etoile = 11;
                    return $etoile;
                }
                elseif(count($childrenVerif_0) >= 2)
                {
                    $etoile = 10;
                    return $etoile;
                }
                elseif($cumul_collectif >= $cumul_1->cumul_collectif_1)
                {
                    if(count($childrenVerif_1) >= 2)
                    {
                        $etoile = $cumul_1->etoile_level;
                        return $etoile;
                    }
                }
                elseif($cumul_collectif >= $cumul_2->cumul_collectif_2)
                {
                    if($childrenVerif_1)
                    {
                        if(count($childrenVerif_1) >= 3)
                        {
                            $etoile = $cumul_2->etoile_level;
                            return $etoile;
                        }
                        else {
                            if(count($childrenVerif_1) >= 2 && count($childrenVerif_2) >= 4)
                            {
                                $etoile = $cumul_2->etoile_level;
                                return $etoile;
                            }
                            elseif(count($childrenVerif_1) >= 1 && count($childrenVerif_2) >= 6)
                            {
                                $etoile = $cumul_2->etoile_level;
                                return $etoile;
                            }
                        }
                    }
                }
                elseif($cumul_collectif >= $cumul_3->cumul_collectif_1)
                {
                    if($childrenVerif_2)
                    {
                        if(count($childrenVerif_2) >= 2)
                        {
                            $etoile = $cumul_3->etoile_level;
                            return $etoile;
                        }
                    }
                }
                elseif($cumul_collectif >= $cumul_4->cumul_collectif_2)
                {
                    if($childrenVerif_2)
                    {
                        if(count($childrenVerif_2) >= 3)
                        {
                            $etoile = $cumul_4->etoile_level;
                            return $etoile;
                        }
                        else {
                            if(count($childrenVerif_2) >= 2 && count($childrenVerif_3) >= 4)
                            {
                                $etoile = $cumul_4->etoile_level;
                                return $etoile;
                            }
                            elseif(count($childrenVerif_2) >= 1 && count($childrenVerif_3) >= 6)
                            {
                                $etoile = $cumul_4->etoile_level;
                                return $etoile;
                            }
                        }
                    }
                }
                elseif($cumul_collectif >= $cumul_5->cumul_collectif_1)
                {
                    if($childrenVerif_2)
                    {
                        if(count($childrenVerif_2) >= 2)
                        {
                            $etoile = $cumul_3->etoile_level;
                            return $etoile;
                        }
                    }
                }
                elseif($cumul_collectif >= $cumul_6->cumul_collectif_2)
                {
                    if($childrenVerif_2)
                    {
                        if(count($childrenVerif_2) >= 3)
                        {
                            $etoile = $cumul_6->etoile_level;
                            return $etoile;
                        }
                        else {
                            if(count($childrenVerif_2) >= 2 && count($childrenVerif_3) >= 4)
                            {
                                $etoile = $cumul_6->etoile_level;
                                return $etoile;
                            }
                            elseif(count($childrenVerif_2) >= 1 && count($childrenVerif_3) >= 6)
                            {
                                $etoile = $cumul_6->etoile_level;
                                return $etoile;
                            }
                        }
                    }
                }
                elseif($cumul_collectif >= $cumul_7->cumul_collectif_1)
                {
                    if($childrenVerif_2)
                    {
                        if(count($childrenVerif_2) >= 2)
                        {
                            $etoile = $cumul_3->etoile_level;
                            return $etoile;
                        }
                    }
                }
                elseif($cumul_collectif >= $cumul_8->cumul_collectif_2)
                {
                    if($childrenVerif_2)
                    {
                        if(count($childrenVerif_2) >= 3)
                        {
                            $etoile = $cumul_8->etoile_level;
                            return $etoile;
                        }
                        else {
                            if(count($childrenVerif_2) >= 2 && count($childrenVerif_3) >= 4)
                            {
                                $etoile = $cumul_8->etoile_level;
                                return $etoile;
                            }
                            elseif(count($childrenVerif_2) >= 1 && count($childrenVerif_3) >= 6)
                            {
                                $etoile = $cumul_8->etoile_level;
                                return $etoile;
                            }
                        }
                    }
                }
                else {
                    return $etoiles;
                }

            break;
            case 6:
                //return 'Passage du niveau 6* au niveau supérieur*';
                if(count($childrenVerif_0) >= 3)
                {
                    $etoile = 11;
                    return $etoile;
                }
                elseif(count($childrenVerif_0) >= 2)
                {
                    $etoile = 10;
                    return $etoile;
                }
                elseif($cumul_collectif >= $cumul_1->cumul_collectif_1)
                {
                    if(count($childrenVerif_1) >= 2)
                    {
                        $etoile = $cumul_1->etoile_level;
                        return $etoile;
                    }
                }
                elseif($cumul_collectif >= $cumul_2->cumul_collectif_2)
                {
                    if($childrenVerif_1)
                    {
                        if(count($childrenVerif_1) >= 3)
                        {
                            $etoile = $cumul_2->etoile_level;
                            return $etoile;
                        }
                        else {
                            if(count($childrenVerif_1) >= 2 && count($childrenVerif_2) >= 4)
                            {
                                $etoile = $cumul_2->etoile_level;
                                return $etoile;
                            }
                            elseif(count($childrenVerif_1) >= 1 && count($childrenVerif_2) >= 6)
                            {
                                $etoile = $cumul_2->etoile_level;
                                return $etoile;
                            }
                        }
                    }
                }
                elseif($cumul_collectif >= $cumul_3->cumul_collectif_1)
                {
                    if($childrenVerif_2)
                    {
                        if(count($childrenVerif_2) >= 2)
                        {
                            $etoile = $cumul_3->etoile_level;
                            return $etoile;
                        }
                    }
                }
                elseif($cumul_collectif >= $cumul_4->cumul_collectif_2)
                {
                    if($childrenVerif_2)
                    {
                        if(count($childrenVerif_2) >= 3)
                        {
                            $etoile = $cumul_4->etoile_level;
                            return $etoile;
                        }
                        else {
                            if(count($childrenVerif_2) >= 2 && count($childrenVerif_3) >= 4)
                            {
                                $etoile = $cumul_4->etoile_level;
                                return $etoile;
                            }
                            elseif(count($childrenVerif_2) >= 1 && count($childrenVerif_3) >= 6)
                            {
                                $etoile = $cumul_4->etoile_level;
                                return $etoile;
                            }
                        }
                    }
                }
                elseif($cumul_collectif >= $cumul_5->cumul_collectif_1)
                {
                    if($childrenVerif_2)
                    {
                        if(count($childrenVerif_2) >= 2)
                        {
                            $etoile = $cumul_3->etoile_level;
                            return $etoile;
                        }
                    }
                }
                elseif($cumul_collectif >= $cumul_6->cumul_collectif_2)
                {
                    if($childrenVerif_2)
                    {
                        if(count($childrenVerif_2) >= 3)
                        {
                            $etoile = $cumul_6->etoile_level;
                            return $etoile;
                        }
                        else {
                            if(count($childrenVerif_2) >= 2 && count($childrenVerif_3) >= 4)
                            {
                                $etoile = $cumul_6->etoile_level;
                                return $etoile;
                            }
                            elseif(count($childrenVerif_2) >= 1 && count($childrenVerif_3) >= 6)
                            {
                                $etoile = $cumul_6->etoile_level;
                                return $etoile;
                            }
                        }
                    }
                }
                elseif($cumul_collectif >= $cumul_7->cumul_collectif_1)
                {
                    if($childrenVerif_2)
                    {
                        if(count($childrenVerif_2) >= 2)
                        {
                            $etoile = $cumul_7->etoile_level;
                            return $etoile;
                        }
                    }
                }
                elseif($cumul_collectif >= $cumul_8->cumul_collectif_2)
                {
                    if($childrenVerif_2)
                    {
                        if(count($childrenVerif_2) >= 3)
                        {
                            $etoile = $cumul_8->etoile_level;
                            return $etoile;
                        }
                        else {
                            if(count($childrenVerif_2) >= 2 && count($childrenVerif_3) >= 4)
                            {
                                $etoile = $cumul_8->etoile_level;
                                return $etoile;
                            }
                            elseif(count($childrenVerif_2) >= 1 && count($childrenVerif_3) >= 6)
                            {
                                $etoile = $cumul_8->etoile_level;
                                return $etoile;
                            }
                        }
                    }
                }
                else {
                    return $etoiles;
                }

            break;
            case 7:
                //return 'Passage du niveau 7* au niveau supérieur*';
                if(count($childrenVerif_0) >= 3)
                {
                    $etoile = 11;
                    return $etoile;
                }
                elseif(count($childrenVerif_0) >= 2)
                {
                    $etoile = 10;
                    return $etoile;
                }
                elseif($cumul_collectif >= $cumul_1->cumul_collectif_1)
                {
                    if(count($childrenVerif_1) >= 2)
                    {
                        $etoile = $cumul_1->etoile_level;
                        return $etoile;
                    }
                }
                elseif($cumul_collectif >= $cumul_2->cumul_collectif_2)
                {
                    if($childrenVerif_1)
                    {
                        if(count($childrenVerif_1) >= 3)
                        {
                            $etoile = $cumul_2->etoile_level;
                            return $etoile;
                        }
                        else {
                            if(count($childrenVerif_1) >= 2 && count($childrenVerif_2) >= 4)
                            {
                                $etoile = $cumul_2->etoile_level;
                                return $etoile;
                            }
                            elseif(count($childrenVerif_1) >= 1 && count($childrenVerif_2) >= 6)
                            {
                                $etoile = $cumul_2->etoile_level;
                                return $etoile;
                            }
                        }
                    }
                }
                elseif($cumul_collectif >= $cumul_3->cumul_collectif_1)
                {
                    if($childrenVerif_2)
                    {
                        if(count($childrenVerif_2) >= 2)
                        {
                            $etoile = $cumul_3->etoile_level;
                            return $etoile;
                        }
                    }
                }
                elseif($cumul_collectif >= $cumul_4->cumul_collectif_2)
                {
                    if($childrenVerif_2)
                    {
                        if(count($childrenVerif_2) >= 3)
                        {
                            $etoile = $cumul_4->etoile_level;
                            return $etoile;
                        }
                        else {
                            if(count($childrenVerif_2) >= 2 && count($childrenVerif_3) >= 4)
                            {
                                $etoile = $cumul_4->etoile_level;
                                return $etoile;
                            }
                            elseif(count($childrenVerif_2) >= 1 && count($childrenVerif_3) >= 6)
                            {
                                $etoile = $cumul_4->etoile_level;
                                return $etoile;
                            }
                        }
                    }
                }
                elseif($cumul_collectif >= $cumul_5->cumul_collectif_1)
                {
                    if($childrenVerif_2)
                    {
                        if(count($childrenVerif_2) >= 2)
                        {
                            $etoile = $cumul_3->etoile_level;
                            return $etoile;
                        }
                    }
                }
                elseif($cumul_collectif >= $cumul_6->cumul_collectif_2)
                {
                    if($childrenVerif_2)
                    {
                        if(count($childrenVerif_2) >= 3)
                        {
                            $etoile = $cumul_6->etoile_level;
                            return $etoile;
                        }
                        else {
                            if(count($childrenVerif_2) >= 2 && count($childrenVerif_3) >= 4)
                            {
                                $etoile = $cumul_6->etoile_level;
                                return $etoile;
                            }
                            elseif(count($childrenVerif_2) >= 1 && count($childrenVerif_3) >= 6)
                            {
                                $etoile = $cumul_6->etoile_level;
                                return $etoile;
                            }
                        }
                    }
                }
                else {
                    return $etoiles;
                }
            break;
            case 8:
                //return 'Passage du niveau 8* au niveau supérieur*';
                if(count($childrenVerif_0) >= 3)
                {
                    $etoile = 11;
                    return $etoile;
                }
                elseif(count($childrenVerif_0) >= 2)
                {
                    $etoile = 10;
                    return $etoile;
                }
                elseif($cumul_collectif >= $cumul_1->cumul_collectif_1)
                {
                    if(count($childrenVerif_1) >= 2)
                    {
                        $etoile = $cumul_1->etoile_level;
                        return $etoile;
                    }
                }
                elseif($cumul_collectif >= $cumul_2->cumul_collectif_2)
                {
                    if($childrenVerif_1)
                    {
                        if(count($childrenVerif_1) >= 3)
                        {
                            $etoile = $cumul_2->etoile_level;
                            return $etoile;
                        }
                        else {
                            if(count($childrenVerif_1) >= 2 && count($childrenVerif_2) >= 4)
                            {
                                $etoile = $cumul_2->etoile_level;
                                return $etoile;
                            }
                            elseif(count($childrenVerif_1) >= 1 && count($childrenVerif_2) >= 6)
                            {
                                $etoile = $cumul_2->etoile_level;
                                return $etoile;
                            }
                        }
                    }
                }
                elseif($cumul_collectif >= $cumul_3->cumul_collectif_1)
                {
                    if($childrenVerif_2)
                    {
                        if(count($childrenVerif_2) >= 2)
                        {
                            $etoile = $cumul_3->etoile_level;
                            return $etoile;
                        }
                    }
                }
                elseif($cumul_collectif >= $cumul_4->cumul_collectif_2)
                {
                    if($childrenVerif_2)
                    {
                        if(count($childrenVerif_2) >= 3)
                        {
                            $etoile = $cumul_4->etoile_level;
                            return $etoile;
                        }
                        else {
                            if(count($childrenVerif_2) >= 2 && count($childrenVerif_3) >= 4)
                            {
                                $etoile = $cumul_4->etoile_level;
                                return $etoile;
                            }
                            elseif(count($childrenVerif_2) >= 1 && count($childrenVerif_3) >= 6)
                            {
                                $etoile = $cumul_4->etoile_level;
                                return $etoile;
                            }
                        }
                    }
                }
                else {
                    return $etoiles;
                }
            break;
            case 9:
                //return 'Passage du niveau 9* au niveau supérieur*';
                if(count($childrenVerif_0) >= 3)
                {
                    $etoile = 11;
                    return $etoile;
                }
                elseif(count($childrenVerif_0) >= 2)
                {
                    $etoile = 10;
                    return $etoile;
                }
                elseif($cumul_collectif >= $cumul_1->cumul_collectif_1)
                {
                    if(count($childrenVerif_1) >= 2)
                    {
                        $etoile = $cumul_1->etoile_level;
                        return $etoile;
                    }
                }
                elseif($cumul_collectif >= $cumul_2->cumul_collectif_2)
                {
                    if($childrenVerif_1)
                    {
                        if(count($childrenVerif_1) >= 3)
                        {
                            $etoile = $cumul_2->etoile_level;
                            return $etoile;
                        }
                        else {
                            if(count($childrenVerif_1) >= 2 && count($childrenVerif_2) >= 4)
                            {
                                $etoile = $cumul_2->etoile_level;
                                return $etoile;
                            }
                            elseif(count($childrenVerif_1) >= 1 && count($childrenVerif_2) >= 6)
                            {
                                $etoile = $cumul_2->etoile_level;
                                return $etoile;
                            }
                        }
                    }
                }
                else {
                    return $etoiles;
                }
            break;
            case 10:
                if(count($childrenVerif_0) >= 3)
                {
                    $etoile = 11;
                    return $etoile;
                }
                else {
                    return $etoiles;
                }
            default: $etoiles = $etoiles;
        }

        return $etoiles;
        //return [$distributeur_id, $etoiles, $cumul_individuel, $cumul_collectif];

    }
}
