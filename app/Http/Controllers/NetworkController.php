<?php

namespace App\Http\Controllers;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\Distributeur;
use App\Models\Level;
use App\Models\Level_History;
use App\Models\Achat;
use App\Models\Level_current;
use App\Models\Level_current_2024_01;
use App\Models\Level_current_2024_02;
use App\Models\Level_current_2024_03;
use App\Models\Level_current_test;
use App\Services\EternalHelper;
use Illuminate\Support\Arr;
use Barryvdh\DomPDF\Facade\Pdf;

class NetworkController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        //set_time_limit(240);
        $distributeurs = Distributeur::orderby('created_at', 'ASC')->get();
        $period = Achat::groupBy('period')->get('period');
        return view('layouts.network.index', [
            "distributeurs" => $distributeurs,
            "period" => $period
        ]);
    }

    public function etoilesChecker($etoiles)
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

    //ANCIENNE VERSION
    /*
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
                'children' => $this->getChildrenNetwork($child->distributeur_id, $level, $children)
            );
            //$children[] = $this->array_flatten($temp);
        }
        return $children;//Arr::flatten($children);
    }
    */

    // NOUVELLE VERSION

    public function getChildrenNetwork($distributeurId, $level = 0)
    {
        $children = Distributeur::where('id_distrib_parent', $distributeurId)->get();

        $network = [];

        foreach ($children as $child) {
            $childData = [
                'tour' => $level + 1,
                'id' => $child->distributeur_id,
            ];

            // Récupération récursive des enfants si disponibles
            $subChildren = $this->getChildrenNetwork($child->distributeur_id, $level + 1);
            if (!empty($subChildren)) {
                $childData['children'] = $subChildren;
            }

            $network[] = $childData;
        }

        return $network;
    }

    public function getSubdistribids($disitributeurId, $level)
    {
        $children = [];

        $children[] = $disitributeurId;
        $parentDistributeurs = Distributeur::where('id_distrib_parent', $disitributeurId)->select('distributeur_id')->get();

            foreach ($parentDistributeurs as $parent)
            {
                $temp[] = array(
                    'tour' => $level,
                    'id' => $parent->distributeur_id,
                );
                $children[] = $this->getSubdistribids($parent->distributeur_id, $level, $children);
            }
        return $children;
    }

    public function getSubCategoryIds(int $parentId, $array): array
    {
        if ($parentId === 0) {
            return [];
        }
        $childCategories = $array;// Get all of the child categories of the parent category.
        $subcategoryIds = [];
        foreach ($childCategories as $cle => $childCategory) {
            $subcategoryIds[] = $childCategory->distributeur_id;
            $subcategoryIds = array_merge($subcategoryIds, $this->getSubCategoryIds($childCategory->distributeur_id, $array));
        }
        return $subcategoryIds;
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

    public function bonusCalculate($disitributeurId)
    {
        $children = [];

        //$children[] = $disitributeurId;
        $parentDistributeurs = Distributeur::join('levels', 'levels.distributeur_id', '=', 'distributeurs.distributeur_id')->get(['distributeurs.*', 'levels.*'])->sortByDesc('distributeurs.distributeur_id');

        foreach ($parentDistributeurs as $cles => $parent)
        {
            if($parent->id_distrib_parent == $disitributeurId)
            {
                $children[] = $parentDistributeurs[$cles];
                $children[] = $this->bonusCalculate($parent->distributeur_id);
            }
            //$children[] = $this->bonusCalculate($parent->distributeur_id);
        }
        return $children;
    }

    public function getParentNetwork($disitribParentd)
    {
        $children = [];
        //$childinfos = Distributeur::join('levels', 'levels.distributeur_id', '=', 'distributeurs.distributeur_id')->get(['distributeurs.*', 'levels.*']);
        $parentDistributeurs = Level::where('distributeur_id', $disitribParentd)->get();
        foreach ($parentDistributeurs as $parent)
        {
            $temp[] = array(
                'parent_id' => $parent->id_distrib_parent,
            );
            $children[] = $this->array_flatten($temp);
            $children[] = $this->getParentNetwork($parent->id_distrib_parent, $children);
        }
        return Arr::flatten($children);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {

        //NOUVEAU CODE AVEC IA

        // Validation des données d'entrée
        $validated = $request->validate([
            'distributeur_id' => 'required|size:7',
            'period' => 'required|size:7',
        ]);

        // Initialisation des enfants
        $enfants = [
            ['tour' => 0, 'id' => $request->distributeur_id],
            ...$this->getChildrenNetwork($request->distributeur_id, 0)
        ];
        $enfants = Arr::flatten($enfants);

        if (empty($enfants)) {
            flash('Distributeur non affilié à Eternal Congo')->success();
            return back();
        }

        $limit = 2000;
        $response = [];

        // Récupérer tous les distributeurs et leurs niveaux en une seule requête
        $levels = Level_current_test::whereIn('distributeur_id', $enfants)
            ->where('period', $request->period)
            ->get()
            ->keyBy('distributeur_id');

        $distributeurs = Distributeur::whereIn('distributeur_id', $enfants)->get()->keyBy('distributeur_id');

        for ($i = 0; $i < min($limit, count($enfants)); $i += 2) {
            $j = $i + 1;

            if (!isset($enfants[$j])) {
                continue;
            }

            $childId = $enfants[$j];
            $parentId = $enfants[$i];

            if (!isset($levels[$childId]) || !isset($distributeurs[$childId])) {
                continue;
            }

            $distrib = $distributeurs[$childId];
            $parent = $distributeurs[$distrib->id_distrib_parent] ?? null;
            $cumulAchats = $levels[$childId];
            $ownerParent = Distributeur::where('distributeur_id', $distrib->id_distrib_parent)->first();

            $tabfinal = [
                'rang' => $parentId,
                'distributeur_id' => $childId,
                'nom_distributeur' => $distrib->nom_distributeur ?? 'N/A',
                'period' => $request->period,
                'pnom_distributeur' => $distrib->pnom_distributeur ?? 'N/A',
                'etoiles' => $cumulAchats->etoiles ?? 0,
                'new_cumul' => $cumulAchats->new_cumul ?? 0,
                'cumul_total' => $cumulAchats->cumul_total ?? 0,
                'cumul_collectif' => $cumulAchats->cumul_collectif ?? 0,
                'id_distrib_parent' => $distrib->id_distrib_parent ?? 0,
                'nom_parent' => $ownerParent->nom_distributeur ?? 'N/A',
                'pnom_parent' => $ownerParent->pnom_distributeur ?? 'N/A',
            ];

            //$response[] = Arr::flatten($tabfinal);
            $response[] = $tabfinal;
        }

        //return $response;
        return view('layouts.network.pdf', ["distributeurs" => $response]);

        /** ANCIEN CODE */
        /*
        $validated = $this->validate($request, [
            'distributeur_id' => 'required|min:7|max:7|',
            'period' => 'required|min:7|max:7|',
        ]);


        $enfants[] = array(
            'tour' => 0,
            'id' => $request->distributeur_id,
        );
        $enfants[] = $this->getChildrenNetwork($request->distributeur_id, 0);
        $enfants = Arr::flatten($enfants);
        $limit = 2000 ;//count($enfants);
        //return $enfants;

        for($i=0; $i < $limit; $i++)
        {
            if($i%2 == 0){
                $j = $i+1;
                if($j >= count($enfants))
                {
                    $j = $i;
                }
                else{
                    $level = Level_current_test::where('distributeur_id', $enfants[$j])->where('period', $request->period)->first();
                    if($level) {
                        $distrib = Distributeur::where('distributeur_id', $enfants[$j])->first();
                        $parent = Distributeur::where('distributeur_id', $distrib->id_distrib_parent)->first();
                        //$oldCumul = Level::where('distributeur_id', $distrib->distributeur_id)->first();
                        $cumulAchats = Level_current_test::where('distributeur_id', $enfants[$j])->where('period', $request->period)->first();
                        //return $cumulAchats;
                            /*->selectRaw("distributeur_id")
                            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') AS new_date")
                            ->selectRaw("sum(new_cumul) as new_cumul")
                            ->selectRaw("sum(cumul_total) as cumul_total")
                            ->selectRaw("sum(cumul_collectif) as cumul_collectif")
                            ->groupBy('new_date')
                            //->getBindings();*//*
                        $eternalhelpers = new EternalHelper();
                        //$calcetoiles = $eternalhelpers->avancementGrade($request->distributeur_id, $cumulAchats->etoiles, $cumulAchats->cumul_individuel, $cumulAchats->cumul_collectif);
                        $tabfinal = array(
                            'rang' => $enfants[$i],
                            "distributeur_id" => $enfants[$j],
                            "nom_distributeur" => $distrib->nom_distributeur,
                            "period" => $request->period ?? 0,
                            "pnom_distributeur" => $distrib->pnom_distributeur,
                            "etoiles" => $cumulAchats->etoiles ?? 0,
                            "new_cumul" => $cumulAchats->new_cumul ?? 0,
                            //"total_pv" => $cumulAchats[0]->total,
                            "cumul_total" => $cumulAchats->cumul_total ?? 0,
                            "cumul_collectif" => $cumulAchats->cumul_collectif ?? 0, //($cumulAchats->new ?? 0)
                            "id_distrib_parent" => $distrib->id_distrib_parent ?? 0,
                            "nom_parent" => $parent->nom_distributeur ?? 0,
                            "pnom_parent" => $parent->pnom_distributeur ?? 0
                        );
                        $response[] = $this->array_flatten($tabfinal);
                    }
                }
            }
        }
        //return $response;
        if(isset($response))
        {
            return view('layouts.network.pdf', [
                "distributeurs" => $response,
            ]);
        }
        else {

            flash(message: 'Distributeur non affilié à Eternal Congo')->success();
            return back();

            $distributeurs = Distributeur::orderby('created_at', 'ASC')->get();
            $period = Achat::groupBy('period')->get('period');
            return view('layouts.network.index', [
                "distributeurs" => $distributeurs,
                "period" => $period
            ]);
        }
        //ANCIENNE VERSION
        /*
        $validated = $this->validate($request, [
            'distributeur_id' => 'required|min:7|max:7|',
            'period' => 'required|min:7|max:7|',
        ]);


        $enfants[] = array(
            'tour' => 0,
            'id' => $request->distributeur_id,
        );
        $enfants[] = $this->getChildrenNetwork($request->distributeur_id, 0);
        $enfants = Arr::flatten($enfants);
        $limit = 2000 ;//count($enfants);
        //return $enfants;

        for($i=0; $i < $limit; $i++)
        {
            if($i%2 == 0){
                $j = $i+1;
                if($j >= count($enfants))
                {
                    $j = $i;
                }
                else{

                    $distrib = Distributeur::where('distributeur_id', $enfants[$j])->first();
                    $parent = Distributeur::where('distributeur_id', $distrib->id_distrib_parent)->first();
                    //$oldCumul = Level::where('distributeur_id', $distrib->distributeur_id)->first();
                    $cumulAchats = Level_current_test::where('distributeur_id', $enfants[$j])->where('period', $request->period)->first();
                    //return $cumulAchats;
                        /*->selectRaw("distributeur_id")
                        ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') AS new_date")
                        ->selectRaw("sum(new_cumul) as new_cumul")
                        ->selectRaw("sum(cumul_total) as cumul_total")
                        ->selectRaw("sum(cumul_collectif) as cumul_collectif")
                        ->groupBy('new_date')
                        //->getBindings();*/
        /*
                    $eternalhelpers = new EternalHelper();
                    //$calcetoiles = $eternalhelpers->avancementGrade($request->distributeur_id, $cumulAchats->etoiles, $cumulAchats->cumul_individuel, $cumulAchats->cumul_collectif);
                    $tabfinal = array(
                        'rang' => $enfants[$i],
                        "distributeur_id" => $enfants[$j],
                        "nom_distributeur" => $distrib->nom_distributeur,
                        "period" => $request->period ?? 0,
                        "pnom_distributeur" => $distrib->pnom_distributeur,
                        "etoiles" => $cumulAchats->etoiles ?? 0,
                        "new_cumul" => $cumulAchats->new_cumul ?? 0,
                        //"total_pv" => $cumulAchats[0]->total,
                        "cumul_total" => $cumulAchats->cumul_total ?? 0,
                        "cumul_collectif" => $cumulAchats->cumul_collectif ?? 0, //($cumulAchats->new ?? 0)
                        "id_distrib_parent" => $distrib->id_distrib_parent ?? 0,
                        "nom_parent" => $parent->nom_distributeur ?? 0,
                        "pnom_parent" => $parent->pnom_distributeur ?? 0
                    );
                    $response[] = $this->array_flatten($tabfinal);
                }
            }
        }
        //return $response;
        return view('layouts.network.pdf', [
            "distributeurs" => $response,
        ]);
        */
        /*
        return view('layouts.network.show', [
            //"distribAchat" => $distribAchat,
            //"oldDistribCumul" => $oldDistribCumul,
            //"distributeurs" => $distributeurs,
            "response" => $response,
        ]);*/

    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {

        /*$data = [
            [
                'quantity' => 1,
                'description' => '1 Year Subscription',
                'price' => '129.00'
            ]
        ];

        $pdf = Pdf::loadView('network.pdf', ['data' => $data]);

        return $pdf->download();*/
        //return 'bonjour';
        return view('layouts.network.index');
    }

    public function array_reduction($list)
    {
        $flattened_arr = array();
        array_walk_recursive($list, function($value, $key) use (&$flattened_arr) {
            $flattened_arr[] = $value;
        });

        return $flattened_arr;
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request)
    {
/*
        $parents = Level_current_test::where('distributeur_id', $request->keys())->get();
        $childDistributeurs = Level_current_test::where('id_distrib_parent', $request->keys())->get();

        $list =  $this->getSubdistribids($request->keys(), 0);

        //return $list;

        $tab_inter[] = $this->array_reduction($list);
        $tab_inter = $this->array_flatten($tab_inter);

        foreach ($tab_inter as $key => $value) {
            $distributeurs = Distributeur::join('levels', 'levels.distributeur_id', '=', 'distributeurs.distributeur_id')
            ->where('distributeurs.distributeur_id', $value)
            ->get(['distributeurs.*', 'levels.*']);

            $distribu[] = $distributeurs;
        }

        $temptab = $this->array_reduction($distribu);
        $tab_inter = $this->array_flatten($temptab);
        $distribu = Arr::flatten($tab_inter);
        //return $distribu;
        return view('layouts.network.pdf', [
            "distributeurs" => $distribu
        ]);
        */

        $period = '2024-02';

        $enfants[] = array(
            'tour' => 0,
            'id' => $request->keys()
        );
        $enfants[] = $this->getChildrenNetwork($request->keys(), 0);
        $enfants = Arr::flatten($enfants);
        return $enfants;
        for($i=0; $i < count($enfants); $i++)
        {
            if($i%2 == 0){
                $j = $i+1;
                if($j >= count($enfants))
                {
                    $j = $i;
                }
                else{

                    $distrib = Distributeur::where('distributeur_id', $enfants[$j])->first();
                    $parent = Distributeur::where('distributeur_id', $distrib->id_distrib_parent)->first();
                    //$oldCumul = Level::where('distributeur_id', $distrib->distributeur_id)->first();
                    $cumulAchats = Level_current_2024_02::where('distributeur_id', $enfants[$j])->get();
                    //return $cumulAchats;
                        /*
                        ->selectRaw("distributeur_id")
                        ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') AS new_date")
                        ->selectRaw("sum(new_cumul) as new_cumul")
                        ->selectRaw("sum(cumul_total) as cumul_total")
                        ->selectRaw("sum(cumul_collectif) as cumul_collectif")
                        ->groupBy('new_date')
                        //->getBindings();
                        */

                    $tabfinal = array(
                        'rang' => $enfants[$i],
                        "distributeur_id" => $enfants[$j],
                        "nom_distributeur" => $distrib->nom_distributeur,
                        "period" => $period,
                        "pnom_distributeur" => $distrib->pnom_distributeur,
                        "etoiles" => $cumulAchats[0]->etoiles,
                        "new_cumul" => $cumulAchats[0]->new_cumul ?? 0,
                        //"total_pv" => $cumulAchats[0]->total,
                        "cumul_total" => $cumulAchats[0]->cumul_total ?? 0,
                        "cumul_collectif" => $cumulAchats[0]->cumul_collectif ?? 0, //($cumulAchats[0]->new ?? 0)
                        "id_distrib_parent" => $parent->distributeur_id ?? 0,
                        "nom_parent" => $parent->nom_distributeur ?? 0,
                        "pnom_parent" => $parent->pnom_distributeur ?? 0
                    );
                    $response[] = $this->array_flatten($tabfinal);
                }
            }
        }

        //return $tabChildren[0]['distributeur_id'];
        return view('layouts.network.pdf', [
            "distributeurs" => $response,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        return 'layouts.network.index';
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

    }
}
