<?php

namespace App\Http\Controllers;

use Illuminate\Http\final;
use App\Models\Distributeur;
use App\Models\Level;
use App\Models\Achat;
use App\Models\Etoile;
use App\Models\Level_History;
use App\Models\Level_comparatif;
use App\Models\Level_current;
use App\Models\Level_current_2024_02;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Arr;
use Carbon\Carbon;
use PHPUnit\Framework\Constraint\IsEmpty;

use function PHPUnit\Framework\isEmpty;
use function PHPUnit\Framework\isNull;

class AddAutoDistribController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function array_flatten($array = null) {
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

    public function index()
    {


    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
        /*
        *///return $levels;
        $db = 'App\Models\Level_current_2024_02';
        $db_result = $db::all();

        foreach ($db_result as $key => $value) {
            $result[] = $this->CalculCumlIndividuel($value->distributeur_id, $value->cumul_collectif, $value->new_cumul);
        }

        return $result;


        /*
        $db_result = $db::where('distributeur_id', '6686147')->first();

        $result[] = $this->CalculCumlIndividuel($db_result->distributeur_id, $db_result->cumul_collectif, $db);
        return $result;
        //return $db_result;
        */
        /*
        $ccumul = Level_History::selectRaw("DATE_FORMAT(created_at, '%Y-%m') AS new_date")
            ->selectRaw("sum(cumul_total) as cumul_total, etoiles, rang, id_distrib_parent, distributeur_id,  created_at, updated_at")
            ->selectRaw("sum(new_cumul) as new_cumul")
            ->selectRaw("sum(cumul_individuel) as cumul_individuel")
            ->selectRaw("sum(cumul_collectif) as cumul_collectif")
            ->groupBy('distributeur_id')
            ->groupBy('new_date')
            ->orderBy('etoiles', 'DESC')
            ->get();

        $reste = $ccumul;
        $returned = [];
        foreach ($reste as $key => $value) {
            $etoiles_requis = Etoile::where('etoile_level', ( $value->etoiles+1))->first();
            $levelInfos = Level::where('distributeur_id', $value->distributeur_id)->first();
            $individuel = $value->cumul_individuel + $levelInfos->cumul_individuel;
            $collectif = $value->cumul_collectif + $levelInfos->cumul_collectif;
            //return $indivuduel;
            switch($value->etoiles)
            {
                case 1:
                    //return 'Passage du niveau 1* au niveau 2*';
                    $etoiles = ($individuel >= $etoiles_requis->cumul_individuel) ? 2 : 1;
                    $returned[] = array(
                        'rang' => $value->rang,
                        'distributeur_id' => $value->distributeur_id,
                        'etoiles' => $etoiles,
                        'cumul_individuel' => $individuel,
                        'new_cumul' => $value->new_cumul,
                        'cumul_total' => $value->cumul_total,
                        'cumul_collectif' => $collectif,
                        'period' => $value->new_date,
                        'etoiles limit' => $etoiles_requis->cumul_individuel,
                        'id_distrib_parent' => $value->id_distrib_parent,
                        'created_at' => $value->created_at,
                        'updated_at' => $value->updated_at
                    );
                break;
                case 2:
                    // Passage du niveau 2* au niveau 3*
                    $etoiles = ($individuel >= $etoiles_requis->cumul_individuel) ? 3 : 2;
                    $returned[] = array(
                        'rang' => $value->rang,
                        'distributeur_id' => $value->distributeur_id,
                        'etoiles' => $etoiles,
                        'cumul_individuel' => $individuel,
                        'new_cumul' => $value->new_cumul,
                        'cumul_total' => $value->cumul_total,
                        'cumul_collectif' => $collectif,
                        'period' => $value->new_date,
                        'etoiles limit' => $etoiles_requis->cumul_individuel,
                        'id_distrib_parent' => $value->id_distrib_parent,
                        'created_at' => $value->created_at,
                        'updated_at' => $value->updated_at
                    );
                break;
                case 3:
                    // Passage du niveau 3* au niveau 4*
                    $etoiles = ($individuel >= $etoiles_requis->cumul_individuel) ? 4 : 3;
                    //return $etoiles_requis->cumul_individuel_1;
                    $returned[] = array(
                        'rang' => $value->rang,
                        'distributeur_id' => $value->distributeur_id,
                        'etoiles' => $etoiles,
                        'cumul_individuel' => $individuel,
                        'new_cumul' => $value->new_cumul,
                        'cumul_total' => $value->cumul_total,
                        'cumul_collectif' => $collectif,
                        'period' => $value->new_date,
                        'etoiles limit' => $etoiles_requis->cumul_individuel,
                        'id_distrib_parent' => $value->id_distrib_parent,
                        'created_at' => $value->created_at,
                        'updated_at' => $value->updated_at
                    );
                break;
                case 4:
                    //return $etoiles_requis->cumul_collectif_2;
                    if($collectif >= $etoiles_requis->cumul_collectif_1){
                        $nbthird = $this->getSubdistribids($value->distributeur_id, 4);
                        if(count($nbthird) >= 2)
                        {
                            $etoiles = 5;
                        }
                        elseif($collectif >= $etoiles_requis->cumul_collectif_2){
                            $nbthird = $this->getSubdistribids($value->distributeur_id, 4);

                            if(count($nbthird) >= 3)
                            {
                                $etoiles = 5;
                            }
                            elseif($collectif >= $etoiles_requis->cumul_collectif_3){
                                $nbthird = $this->getSubdistribids($value->distributeur_id, 4);
                                $nbforth = $this->getSubdistribids($value->distributeur_id, 3);
                                if((count($nbthird) >= 2) && (count($nbforth) >= 4))
                                {
                                    $etoiles = 5;
                                }else {
                                    $etoiles = 4;
                                }
                            }
                        }
                        else {
                            $etoiles = 4;
                        }
                    }
                    else {
                        $etoiles = ($individuel >= $etoiles_requis->cumul_individuel) ? 5 : 4;
                    }
                    $returned[] = array(
                        'rang' => $value->rang,
                        'distributeur_id' => $value->distributeur_id,
                        'etoiles' => $etoiles,
                        'cumul_individuel' => $individuel,
                        'new_cumul' => $value->new_cumul,
                        'cumul_total' => $value->cumul_total,
                        'cumul_collectif' => $collectif,
                        'period' => $value->new_date,
                        'etoiles limit' => $etoiles_requis->cumul_individuel,
                        'id_distrib_parent' => $value->id_distrib_parent,
                        'created_at' => $value->created_at,
                        'updated_at' => $value->updated_at
                    );
                break;
                case 5:
                    // Passage du niveau 3* au niveau 4*
                    if($collectif >= $etoiles_requis->cumul_collectif_1){
                        $nbthird = $this->getSubdistribids($value->distributeur_id, 5);
                        if(count($nbthird) >= 2)
                        {
                            $etoiles = 6;
                        }
                        elseif($collectif >= $etoiles_requis->cumul_collectif_2){
                            $nbthird = $this->getSubdistribids($value->distributeur_id, 5);

                            if(count($nbthird) >= 3)
                            {
                                $etoiles = 6;
                            }
                            elseif($collectif >= $etoiles_requis->cumul_collectif_3){
                                $nbthird = $this->getSubdistribids($value->distributeur_id, 5);
                                $nbforth = $this->getSubdistribids($value->distributeur_id, 4);
                                if((count($nbthird) >= 2) && (count($nbforth) >= 4))
                                {
                                    $etoiles = 6;
                                }else {
                                    $etoiles = 5;
                                }
                            }
                            elseif($collectif >= $etoiles_requis->cumul_collectif_4){
                                $nbthird = $this->getSubdistribids($value->distributeur_id, 5);
                                $nbforth = $this->getSubdistribids($value->distributeur_id, 4);
                                if((count($nbthird) >= 2) && (count($nbforth) >= 6))
                                {
                                    $etoiles = 6;
                                }else {
                                    $etoiles = 5;
                                }
                            }
                        }
                        else {
                            $etoiles = 5;
                        }
                    }
                    else {
                        $etoiles = 5;
                    }
                    $returned[] = array(
                        'rang' => $value->rang,
                        'distributeur_id' => $value->distributeur_id,
                        'etoiles' => $etoiles,
                        'eex_toiles' => $value->etoiles,
                        'cumul_individuel' => $individuel,
                        'new_cumul' => $value->new_cumul,
                        'cumul_total' => $value->cumul_total,
                        'cumul_collectif' => $collectif,
                        'period' => $value->new_date,
                        'etoiles limit' => $etoiles_requis->cumul_collectif_1,
                        'id_distrib_parent' => $value->id_distrib_parent,
                        'created_at' => $value->created_at,
                        'updated_at' => $value->updated_at
                    );
                break;
                case 6:
                    // Passage du niveau 5* au niveau 6*
                    if($collectif >= $etoiles_requis->cumul_collectif_1){
                        $nbthird = $this->getSubdistribids($value->distributeur_id, 6);
                        if(count($nbthird) >= 2)
                        {
                            $etoiles = 7;
                        }
                        elseif($collectif >= $etoiles_requis->cumul_collectif_2){
                            $nbthird = $this->getSubdistribids($value->distributeur_id, 6);

                            if(count($nbthird) >= 3)
                            {
                                $etoiles = 7;
                            }
                            elseif($collectif >= $etoiles_requis->cumul_collectif_3){
                                $nbthird = $this->getSubdistribids($value->distributeur_id, 6);
                                $nbforth = $this->getSubdistribids($value->distributeur_id, 5);
                                if((count($nbthird) >= 2) && (count($nbforth) >= 4))
                                {
                                    $etoiles = 7;
                                }else {
                                    $etoiles = 6;
                                }
                            }
                            elseif($collectif >= $etoiles_requis->cumul_collectif_4){
                                $nbthird = $this->getSubdistribids($value->distributeur_id, 6);
                                $nbforth = $this->getSubdistribids($value->distributeur_id, 5);
                                if((count($nbthird) >= 1) && (count($nbforth) >= 6))
                                {
                                    $etoiles = 7;
                                }else {
                                    $etoiles = 6;
                                }
                            }
                        }
                        else {
                            $etoiles = 6;
                        }
                    }
                    else {
                        $etoiles = 6;
                    }
                    $returned[] = array(
                        'rang' => $value->rang,
                        'distributeur_id' => $value->distributeur_id,
                        'etoiles' => $etoiles,
                        'cumul_individuel' => $individuel,
                        'new_cumul' => $value->new_cumul,
                        'cumul_total' => $value->cumul_total,
                        'cumul_collectif' => $collectif,
                        'period' => $value->new_date,
                        'etoiles limit' => $etoiles_requis->cumul_individuel,
                        'id_distrib_parent' => $value->id_distrib_parent,
                        'created_at' => $value->created_at,
                        'updated_at' => $value->updated_at
                    );
                break;
                case 7:
                    // Passage du niveau 7* au niveau 8*
                    if($collectif >= $etoiles_requis->cumul_collectif_1){
                        $nbthird = $this->getSubdistribids($value->distributeur_id, 7);
                        if(count($nbthird) >= 2)
                        {
                            $etoiles = 8;
                        }
                        elseif($collectif >= $etoiles_requis->cumul_collectif_2){
                            $nbthird = $this->getSubdistribids($value->distributeur_id, 7);

                            if(count($nbthird) >= 3)
                            {
                                $etoiles = 8;
                            }
                            elseif($collectif >= $etoiles_requis->cumul_collectif_3){
                                $nbthird = $this->getSubdistribids($value->distributeur_id, 7);
                                $nbforth = $this->getSubdistribids($value->distributeur_id, 6);
                                if((count($nbthird) >= 2) && (count($nbforth) >= 4))
                                {
                                    $etoiles = 8;
                                }else {
                                    $etoiles = 7;
                                }
                            }
                            elseif($collectif >= $etoiles_requis->cumul_collectif_4){
                                $nbthird = $this->getSubdistribids($value->distributeur_id, 7);
                                $nbforth = $this->getSubdistribids($value->distributeur_id, 6);
                                if((count($nbthird) >= 1) && (count($nbforth) >= 6))
                                {
                                    $etoiles = 8;
                                }else {
                                    $etoiles = 7;
                                }
                            }
                        }
                        else {
                            $etoiles = 7;
                        }
                    }
                    else {
                        $etoiles = 7;
                    }
                    $returned[] = array(
                        'rang' => $value->rang,
                        'distributeur_id' => $value->distributeur_id,
                        'etoiles' => $etoiles,
                        'cumul_individuel' => $individuel,
                        'new_cumul' => $value->new_cumul,
                        'cumul_total' => $value->cumul_total,
                        'cumul_collectif' => $collectif,
                        'period' => $value->new_date,
                        'etoiles limit' => $etoiles_requis->cumul_individuel,
                        'id_distrib_parent' => $value->id_distrib_parent,
                        'created_at' => $value->created_at,
                        'updated_at' => $value->updated_at
                    );
                break;
                case 8:
                    // Passage du niveau 8* au niveau 9*
                    if($collectif >= $etoiles_requis->cumul_collectif_1){
                        $nbthird = $this->getSubdistribids($value->distributeur_id, 8);
                        if(count($nbthird) >= 2)
                        {
                            $etoiles = 9;
                        }
                        elseif($collectif >= $etoiles_requis->cumul_collectif_2){
                            $nbthird = $this->getSubdistribids($value->distributeur_id, 8);

                            if(count($nbthird) >= 3)
                            {
                                $etoiles = 9;
                            }
                            elseif($collectif >= $etoiles_requis->cumul_collectif_3){
                                $nbthird = $this->getSubdistribids($value->distributeur_id, 8);
                                $nbforth = $this->getSubdistribids($value->distributeur_id, 7);
                                if((count($nbthird) >= 2) && (count($nbforth) >= 4))
                                {
                                    $etoiles = 9;
                                }else {
                                    $etoiles = 8;
                                }
                            }
                            elseif($collectif >= $etoiles_requis->cumul_collectif_4){
                                $nbthird = $this->getSubdistribids($value->distributeur_id, 8);
                                $nbforth = $this->getSubdistribids($value->distributeur_id, 7);
                                if((count($nbthird) >= 1) && (count($nbforth) >= 6))
                                {
                                    $etoiles = 9;
                                }else {
                                    $etoiles = 8;
                                }
                            }
                        }
                        else {
                            $etoiles = 8;
                        }
                    }
                    else {
                        $etoiles = 8;
                    }
                    $returned[] = array(
                        'rang' => $value->rang,
                        'distributeur_id' => $value->distributeur_id,
                        'etoiles' => $etoiles,
                        'cumul_individuel' => $individuel,
                        'new_cumul' => $value->new_cumul,
                        'cumul_total' => $value->cumul_total,
                        'cumul_collectif' => $collectif,
                        'period' => $value->new_date,
                        'etoiles limit' => $etoiles_requis->cumul_individuel,
                        'id_distrib_parent' => $value->id_distrib_parent,
                        'created_at' => $value->created_at,
                        'updated_at' => $value->updated_at
                    );
                break;
                case 9:
                    // Passage du niveau 9* au niveau 10*
                    $nbthird = $this->getSubdistribids($value->distributeur_id, 9);
                    if(count($nbthird) >= 2)
                    {
                        $etoiles = 10;
                    }
                    else {
                        $etoiles = 9;
                    }
                    $returned[] = array(
                        'rang' => $value->rang,
                        'distributeur_id' => $value->distributeur_id,
                        'etoiles' => $etoiles,
                        'cumul_individuel' => $individuel,
                        'new_cumul' => $value->new_cumul,
                        'cumul_total' => $value->cumul_total,
                        'cumul_collectif' => $collectif,
                        'period' => $value->new_date,
                        'etoiles limit' => $etoiles_requis->cumul_individuel,
                        'id_distrib_parent' => $value->id_distrib_parent,
                        'created_at' => $value->created_at,
                        'updated_at' => $value->updated_at
                    );
                break;
                case 10:
                    // Passage du niveau 10* au niveau 11*
                    //return 'Passage du niveau 10* au niveau 11*';
                    $nbthird = $this->getSubdistribids($value->distributeur_id, 9);
                    if(count($nbthird) >= 3)
                    {
                        $etoiles = 11;
                    }
                    else {
                        $etoiles = 10;
                    }
                    $returned[] = array(
                        'rang' => $value->rang,
                        'distributeur_id' => $value->distributeur_id,
                        'etoiles' => $etoiles,
                        'cumul_individuel' => $individuel,
                        'new_cumul' => $value->new_cumul,
                        'cumul_total' => $value->cumul_total,
                        'cumul_collectif' => $collectif,
                        'period' => $value->new_date,
                        'etoiles limit' => $etoiles_requis->cumul_individuel,
                        'id_distrib_parent' => $value->id_distrib_parent,
                        'created_at' => $value->created_at,
                        'updated_at' => $value->updated_at
                    );
                break;
                default: $etoiles = $value->etoiles;
                    $returned[] = array(
                        'rang' => $value->rang,
                        'distributeur_id' => $value->distributeur_id,
                        'etoiles' => $etoiles,
                        'cumul_individuel' => $individuel,
                        'new_cumul' => $value->new_cumul,
                        'cumul_total' => $value->cumul_total,
                        'cumul_collectif' => $collectif,
                        'period' => $value->new_date,
                        'etoiles limit' => $etoiles_requis->cumul_individuel,
                        'id_distrib_parent' => $value->id_distrib_parent,
                        'created_at' => $value->created_at,
                        'updated_at' => $value->updated_at
                    );
            }

            //$etoiles = $this->reseauCheckLevel($request->distributeur_id, $cumul_collectif, $pv->etoiles, true);
            //return $etoiles;
        }
        return $returned;

*/
    }

    public function CalculCumlIndividuel($disitributeurId, $cuml_parent, $new_cumul)
    {
        $children = [];
        $individuelTab = [];
        $total = 0;
        $reste = 0;
        $childDistributeurs = Level_current_2024_02::where('id_distrib_parent', $disitributeurId)->get();
        $levels = Level_current_2024_02::where('distributeur_id', $disitributeurId)->first();
        $nbrChildren = count($childDistributeurs);

        if($nbrChildren != 0)
        {
            foreach ($childDistributeurs as $child)
            {
                $total = $total + $child->cumul_collectif;
            }
            if($total)
            {
                try {
                    $levels->cumul_individuel = $cuml_parent - $total;
                    if($levels->update())
                    echo "<font color=green>Cumul individuel updated</font>";

                }
                catch(\Illuminate\Database\QueryException $exept){
                    dd($exept->getMessage());
                }
            }
            /*
            $individuelTab[] = array(
                'distributeur_id' => $disitributeurId,
                'cuml_parent' => $cuml_parent,
                'total' => $total,
                'reste' => $cuml_parent - $total,
                'child' => $children
            );
            */

            $children[] = $this->CalculCumlIndividuel($child->distributeur_id, $child->cumul_collectif, $children);
        }
        else {
            if($cuml_parent > $new_cumul){

                if($total)
                {
                    try {
                        $levels->cumul_individuel = $cuml_parent;
                        if($levels->update())
                        echo "<font color=green>Cumul individuel updated</font>";

                    }
                    catch(\Illuminate\Database\QueryException $exept){
                        dd($exept->getMessage());
                    }
                }
                /*
                $individuelTab[] = array(
                    'distributeur_id' => $disitributeurId,
                    'cuml_parent' => $cuml_parent,
                    'total' => $total,
                    'reste' => $cuml_parent
                );
                */
            }
            elseif($cuml_parent <= $new_cumul)
            {
                if($total)
                {
                    try {
                        $levels->cumul_individuel = $new_cumul;
                        if($levels->update())
                        echo "<font color=green>Cumul individuel updated</font>";

                    }
                    catch(\Illuminate\Database\QueryException $exept){
                        dd($exept->getMessage());
                    }
                }
                /*
                $individuelTab[] = array(
                    'distributeur_id' => $disitributeurId,
                    'cuml_parent' => $cuml_parent,
                    'total' => $total,
                    'reste' => $new_cumul
                );
                */
            }
        }

        return $individuelTab;
        /*
        $levels = $db::where('distributeur_id', $disitributeurId)->first();

        if($levels) {
            try {
                $levels->cumul_individuel = $reste;
                if($levels->update())
                echo "<font color=green>Cumul individuel updated</font>";

            } catch(\Illuminate\Database\QueryException $exept){
                dd($exept->getMessage());
            }
        }

        */

    }
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $final)
    {
        //
    }

    /**
    *
    * fonction qui ajoute dans une table les distributeurs manquants qui sont dans l'autre
    *
     */

    public function comparatifTab()
    {

        $levels = Level::select('distributeur_id')->get();
        $comparatif = Level::all();
        foreach ($comparatif as $key => $value) {
            $tab[] = $value->distributeur_id;
        }
        //return $tab;
        $level_comp = Level_comparatif::whereNotIn('distributeur_id', $tab)->get();

        foreach ($level_comp as $key => $value) {

            try {

                Level::insert([
                    'distributeur_id' => $value->distributeur_id,
                    'rang' => $value->rang,
                    'etoiles' => $value->etoiles,
                    'new_cumul' => $value->new_cumul,
                    'cumul_total' => $value->cumul_total,
                    'cumul_collectif' => $value->cumul_collectif,
                    'id_distrib_parent' => $value->id_distrib_parent
                ]);
            }
            catch(\Illuminate\Database\QueryException $exept){
                dd($exept->getMessage());
            }
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
        $cumul_total = Level_History::where('distributeur_id', $id)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') AS new_date, sum(cumul_total) as cumul_total, distributeur_id, created_at")
            ->groupBy('new_date')
            ->get();
        //$levels = Level_History::where('distributeur_id', $id)->get();
        //return $cumul_total;

        foreach ($cumul_total as $key => $value) {
            $result = $this->CalculCumlCollectif($value->distributeur_id);
        }

        $result = (object) Arr::flatten($result);

        $cumul = 0;
        foreach ($result as $key => $val) {
            $cumul = $cumul + $val->cumul_collectif;
            /*
            $level_current[] = array(
                'period' => $val->new_date,
                'rang' => $val->rang,
                'distributeur_id' => $val->distributeur_id,
                'etoiles' => $val->etoiles,
                'cumul_individuel' => $val->cumul_individuel,
                'new_cumul' => $val->new_cumul,
                'cumul_total' => $val->cumul_total,
                'cumul_collectif' => $val->cumul_collectif,
                'cumul' => $cumul,
                'id_distrib_parent' => $val->id_distrib_parent,
                'created_at' => $val->created_at,
                'updated_at' => $val->updated_at

            );*/
            try {
                $current = new Level_current();

                $current->rang = $val->rang;
                $current->distributeur_id = $val->distributeur_id;
                $current->etoiles = $val->etoiles;
                $current->cumul_individuel = $val->cumul_individuel;
                $current->new_cumul = $val->new_cumul;
                $current->cumul_total = $val->cumul_total;
                $current->cumul_collectif = $cumul;
                $current->period = $val->new_date;
                $current->id_distrib_parent = $val->id_distrib_parent;
                $current->created_at = $val->created_at;
                $current->updated_at = $val->updated_at;

                $current->save();

            } catch(\Illuminate\Database\QueryException $exept){
                dd($exept->getMessage());
            }
        }
    }


    public function getSubdistribids($disitributeurId, $rang)
    {
        $children = [];

        $children[] = $disitributeurId;
        $parentDistributeurs = Distributeur::where('id_distrib_parent', $disitributeurId)->where('etoiles_id', '>=', $rang)->get();
            foreach ($parentDistributeurs as $parent)
            {
                $children[] = $this->getSubdistribids($parent->distributeur_id, $children);
            }
        return $children;
    }

    public function CalculCumlCollectif($disitributeurId)
    {
        $children = [];
        $childDistributeurs = Level_History::where('id_distrib_parent', $disitributeurId)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') AS new_date, sum(cumul_total) as cumul_total, distributeur_id, created_at")
            ->groupBy('new_date')
            ->get();
        $nbrChildren = count($childDistributeurs);
        if($nbrChildren !=0)
        {
            return $childDistributeurs;
            $total = 0;
            foreach ($childDistributeurs as $child)
            {
                $total = $total + $child->cumul_collectif;
                if($nbrChildren != 0)
                    $children[] = $this->CalculCumlCollectif($child->distributeur_id, $child->cumul_collectif);
            }

        }
        else {
            $pcumul = Level_History::where('distributeur_id', $disitributeurId)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') AS new_date")
            ->selectRaw("sum(cumul_total) as cumul_total, etoiles, rang, id_distrib_parent, distributeur_id,  created_at, updated_at")
            ->selectRaw("sum(new_cumul) as new_cumul")
            ->selectRaw("sum(cumul_individuel) as cumul_individuel")
            ->selectRaw("sum(cumul_collectif) as cumul_collectif")
            ->groupBy('new_date')
            ->orderBy('new_date', 'ASC')
            ->get();
            $reste[] = $pcumul;
        }
        return $reste;
        /*
        $levels = Level::where('distributeur_id', $disitributeurId)->first();

        if($levels) {
            try {
                $levels->cumul_individuel = $reste;
                if($levels->update())
                $response = "<font color=green>Cumul individuel updated</font>";

            } catch(\Illuminate\Database\QueryException $exept){
                dd($exept->getMessage());
            }
        }

        return array(
            'count' => $nbrChildren,
            'distributeur_id' => $disitributeurId,
            'cuml_parent' => $cuml_parent,
            'total' => $total,
            'reste' => $reste,
            'child' => $children);
        */
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
    public function update(Request $final, string $id)
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
