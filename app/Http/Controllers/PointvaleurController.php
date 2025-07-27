<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Pointvaleur;
use App\Models\Distributeur;
use App\Models\Level;
use App\Models\Level_current;
use App\Models\Level_History;

use function PHPUnit\Framework\isNull;

class PointvaleurController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {   //2271247
        $parents = Level::where('distributeur_id', '6687733)')->get();
        $childDistributeurs = Level::where('id_distrib_parent', '6687733)')->get();

        $list = array(
            "rang" => 0,
            "id" => $parents[0]->distributeur_id,
            "new_cumul" => $parents[0]->new_cumul,
            "cumul_individuel" => $parents[0]->cumul_collectif - $childDistributeurs->sum('cumul_collectif'),
            "cumul_total" => $parents[0]->cumul_total,
            "cumul_collectif" => $parents[0]->cumul_collectif,
            "id_parent" => $parents[0]->id_distrib_parent,
            "child" => $this->getSubdistribids('6685204', 0)
        );
        $tab_inter[] = $this->array_reduction($list);
        $tab_inter = $this->array_flatten($tab_inter);
        $index = array('rang', 'id', 'new_cumul', 'cumul_individuel', 'cumul_total', 'cumul_collectif', 'id_distrib_parent');
        $i = -1;
        foreach ($tab_inter as $value) {
            $i++;
            $final[] = array($index[$i] => $value);
            if($i==6) {
                $final_tab[] = array_merge($final[0], $final[1], $final[2], $final[3], $final[4], $final[5], $final[6]);
                $i = -1;
                unset($final);
            }
        }

        return $final_tab;
        foreach ($final_tab as $key => $value) {
            $levels = Level::where('distributeur_id', '=', $value['id'])->firstOrFail();
            $levels->rang = $value['rang'];
            $levels->cumul_individuel = $value['cumul_individuel'];
            $levels->update();

            $levelHistory = Level_History::where('distributeur_id', '=', $value['id'])->firstOrFail();
            $levelHistory->rang= $value['rang'];
            $levelHistory->cumul_individuel = $value['cumul_individuel'];
            $levelHistory->update();

            $distributeurs = Distributeur::where('distributeur_id', '=', $value['id'])->firstOrFail();
            $distributeurs->rang = $value['rang'];
            $levels->update();
        }
    }

    public function array_reduction($list)
    {
        $flattened_arr = array();
        array_walk_recursive($list, function($value, $key) use (&$flattened_arr) {
            $flattened_arr[] = $value;
        });

        return $flattened_arr;
    }

    public function create()
    {

        $parents = Level::where('distributeur_id', '6687733)')->get();
        $childDistributeurs = Level::where('id_distrib_parent', '6687733)')->get();

        $list = array(
            "rang" => 0,
            "id" => $parents[0]->distributeur_id,
            "new_cumul" => $parents[0]->new_cumul,
            "cumul_individuel" => $parents[0]->cumul_collectif - $childDistributeurs->sum('cumul_collectif'),
            "cumul_total" => $parents[0]->cumul_total,
            "cumul_collectif" => $parents[0]->cumul_collectif,
            "id_parent" => $parents[0]->id_distrib_parent,
            "child" => $this->getSubdistribids('6687733', 0)
        );

        return $list;

        $tab_inter[] = $this->array_reduction($list);
        $tab_inter = $this->array_flatten($tab_inter);
        $index = array('rang', 'id', 'new_cumul', 'cumul_individuel', 'cumul_total', 'cumul_collectif', 'id_distrib_parent');
        $i = -1;
        foreach ($tab_inter as $value) {
            $i++;
            $final[] = array($index[$i] => $value);
            if($i==6) {
                $final_tab[] = array_merge($final[0], $final[1], $final[2], $final[3], $final[4], $final[5], $final[6]);
                $i = -1;
                unset($final);
            }
        }

        //return $final_tab;
        /*
        foreach ($final_tab as $key => $value) {
            $levels = Level::where('distributeur_id', '=', $value['id'])->firstOrFail();
            $levels->rang = $value['rang'];
            $levels->cumul_individuel = $value['cumul_individuel'];
            $levels->update();

            $levelHistory = Level_current::where('distributeur_id', '=', $value['id'])->firstOrFail();
            $levelHistory->rang= $value['rang'];
            $levelHistory->cumul_individuel = $value['cumul_individuel'];
            $levelHistory->update();

            $distributeurs = Distributeur::where('distributeur_id', '=', $value['id'])->firstOrFail();
            $distributeurs->rang = $value['rang'];
            $levels->update();
        }*/
    }

    public function getChildrenNetwork($disitributeurId, $level)
    {
        $children = [];
        //$childinfos = Distributeur::join('levels', 'levels.distributeur_id', '=', 'distributeurs.distributeur_id')->get(['distributeurs.*', 'levels.*']);
        $childDistributeurs = Level::where('id_distrib_parent', $disitributeurId)->get();
        $level++;
        foreach ($childDistributeurs as $child)
        {
            $temp[] = array(
                'tour' => $level,
                'id' => $child->distributeur_id,
            );
            $children[] = $this->array_flatten($temp);
            $children[] = $this->getChildrenNetwork($child->distributeur_id, $level, $children);
        }
        return Arr::flatten($children);
    }
    /**
     * Store a newly created resource in storage.
     */

    public function getSubdistribids($disitributeurId, $level)
    {
        $children = [];
        $childDistributeurs = Level::where('id_distrib_parent', $disitributeurId)->get();
        $level++;
        foreach ($childDistributeurs as $child)
        {
            $actual = Level::where('id_distrib_parent', $child->distributeur_id)->get();
            $children[] = array(
                'tour' => $level,
                'id' => $child->distributeur_id,
                "new_cumul" => $child->new_cumul,
                "cumul_individuel" => $child->cumul_collectif - $actual->sum('cumul_collectif'),
                "cumul_total" => $child->cumul_total,
                "cumul_collectif" => $child->cumul_collectif,
                "id_parent" => $child->id_distrib_parent,
            );
            $children[] = $this->getSubdistribids($child->distributeur_id, $level, $children);
        }
        return $children;
    }

    public function store(Request $request)
    {
        // Validation
        $this->validate($request, [
            'numbers' => 'required|min:1|max:50|unique:pointvaleurs'
        ]);

        $pointvaleur = new Pointvaleur();
        $pointvaleur->numbers = $request->numbers;
        $pointvaleur->save();

        flash(message: 'action executer avec succes')->success();
        return back();
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
