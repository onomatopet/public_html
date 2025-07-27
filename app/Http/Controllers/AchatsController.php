<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
use App\Models\Pointvaleur;
use App\Models\Distributeur;
use App\Models\Level;
use App\Models\Level_History;
use App\Models\Achat;
use App\Models\Level_current_test;
use App\Services\RealtimePurchaseService; // Importez le service
use Carbon\Carbon;
use Illuminate\Support\Facades\Redirect;
use App\Services\EternalHelper;
use Illuminate\Http\Request; // Important: Importer Request
use App\Services\DistributorRankService;
use App\Services\GradeCalculator;
use Illuminate\Http\RedirectResponse;

class AchatsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    protected RealtimePurchaseService $purchaseService;

    public function __construct(RealtimePurchaseService $purchaseService)
    {
        $this->purchaseService = $purchaseService;
        // Ajouter middlewares d'authentification etc. si nécessaire
    }
    public function index()
    {

        $achatInfos = [];
        $achats = Achat::orderBy('created_at', 'DESC')->get();
        foreach ($achats as $key => $value) {
            $infosDistrib = Distributeur::where('distributeur_id', $value->distributeur_id)->first();
            $infosProduct = Product::where('id', $value->products_id)->first();
            $achatInfos[] = array(
                'id' => $value->id,
                'period' => $value->period,
                'distributeur_id' => $infosDistrib->distributeur_id,
                'nom_distributeur' => $infosDistrib->nom_distributeur,
                'pnom_distributeur' => $infosDistrib->pnom_distributeur,
                'etoiles' => $infosDistrib->etoiles_id ?? 0,
                'code_product' => $infosProduct->code_product ?? 0,
                'nom_produit' => $infosProduct->nom_produit ?? 0,
                'prix_product' => $infosProduct->prix_product ?? 0,
                'pointvaleur' => $value->pointvaleur / $value->qt ?? 0,
                'pvtotal' => $value->pointvaleur,
                'montanttotal' => $value->montant,
                'qt' => $value->qt ?? 0,
                'montant' => $value->montant ?? 0,
                'created_at' => $value->created_at ?? 0
            );
        }

        //return $achatInfos;

        return view('layouts.achats.index',[
            'achats' => $achatInfos,
        ]);
        //, ["distributeurs" => $distributeurs,"distribparents" => $distribparents
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $distributeurs = Distributeur::get('distributeur_id', 'id');
        $products = Product::orderby('created_at', 'DESC')->get();
        $prixproducts = Product::select('prix_product')->groupBy('prix_product')->get();
        $categories = Category::pluck('name', 'id');
        $pointvaleurs = Pointvaleur::select('numbers','id')->groupBy('numbers', 'id')->get();
        //return $categories->keys();
        return view('layouts.achats.create')->with([
            "products" => $products,
            "categories" => $categories,
            "distributeurs" => $distributeurs,
            "prixproducts" => $prixproducts,
            "pointvaleurs" => $pointvaleurs
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        // --- VALIDATION ---
        $validatedData = $request->validate([
            'distributeur_id' => 'required|min:7|max:7|exists:distributeurs,distributeur_id', // Vérifie que le matricule existe dans la table distributeurs
            'value_pv' => 'required|min:1',
            'idproduit' => 'required|min:1',
            'Qt' => 'required|min:1',
            'value' => 'required|min:2',
            'prix_product' => 'required|min:2|max:120',
            'pointvaleur_id' => 'required|min:1',
            'created_at' => 'required|date_format:d/m/Y'
            //'period' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'], // Format YYYY-MM
        ]);

        $rank = new DistributorRankService();
        $calculator = new GradeCalculator();

        $distribInfos = Distributeur::where('distributeur_id', $request->distributeur_id)->first();
        if($distribInfos)
        {
            $RequestedDate =  Carbon::createFromFormat('d/m/Y', $request->created_at)->format('d-m-Y');
            $period = Carbon::createFromFormat('d/m/Y', $request->created_at)->format('Y-m');

            $products = new Achat();
            $products->id_distrib_parent = $distribInfos->id_distrib_parent;
            $products->distributeur_id = $request->distributeur_id;
            $products->period = $period;
            $products->pointvaleur = $request->value_pv;
            $products->products_id = $request->idproduit;
            $products->qt = $request->Qt;
            $products->montant = $request->value;
            $products->created_at = Carbon::parse($RequestedDate);
            $products->save();
        }

       // --- Appel du service ---
        $result = $this->purchaseService->processPurchase(
            $validatedData['distributeur_id'],
            $validatedData['value_pv'],
            $period
        );

        $level = Level_current_test::where('distributeur_id', $request->distributeur_id)->first();
        $countChildren = $rank->checkMultiLevelQualificationSeparateCountsMatricule($level->distributeur_id, $level->etoiles);
        $countChildrenpass1 = $countChildren['level_n_qualified_count'];
        $countChildrenpass2 = $countChildren['level_n_minus_1_qualified_count'];
        $newPotentialLevel = $calculator->calculatePotentialGrade($level->etoiles, $level->cumul_individuel, $level->cumul_collectif, $countChildrenpass1,  $countChildrenpass2);

        if($newPotentialLevel > $level->etoiles)
        {
            $distribInfos->etoiles_id = $newPotentialLevel;
            $level->etoiles = $newPotentialLevel;
            $level->update();
            $distribInfos->update();
        }

        // --- Redirection avec message ---
        if ($result['success']) {
            flash(message: 'action executer avec succes')->success();
            return Redirect::to(route('achats.index'));
        } else {
            return Redirect()->back()->with('error', $result['message'])->withInput();
        }
    }

    public function show(string $id)
    {
        return view('layouts.achats.niveau');
    }

    public function created()
    {
        /*
        $distributeurs = Distributeur::get('distributeur_id', 'id');
        $products = Product_ancien::orderby('created_at', 'DESC')->get();
        $prixproducts = Product_ancien::select('prix_product')->groupBy('prix_product')->get();
        $pointvaleurs = Pointvaleur_ancien::select('numbers','id')->groupBy('numbers', 'id')->get();
        */
        $distributeurs = Distributeur::get('distributeur_id', 'id');
        $products = Product::orderby('created_at', 'DESC')->get();
        $prixproducts = Product::select('prix_product')->groupBy('prix_product')->get();
        $categories = Category::pluck('name', 'id');
        $pointvaleurs = Pointvaleur::select('numbers','id')->groupBy('numbers', 'id')->get();

        return view('layouts.achats.created')->with([
            "distributeurs" => $distributeurs,
            "products" => $products,
            "prixproducts" => $prixproducts,
            "pointvaleurs" => $pointvaleurs
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $achats = Achat::where('id', $id)->first();
        $distributeurs = Distributeur::get('distributeur_id', 'id');
        $products = Product::orderby('created_at', 'DESC')->get();
        $prixproducts = Product::select('prix_product')->groupBy('prix_product')->get();
        $categories = Category::pluck('name', 'id');
        $pointvaleurs = Pointvaleur::select('numbers','id')->groupBy('numbers', 'id')->get();
        //return $categories->keys();
        return view('layouts.achats.edit')->with([
            "achats" => $achats,
            "products" => $products,
            "categories" => $categories,
            "distributeurs" => $distributeurs,
            "prixproducts" => $prixproducts,
            "pointvaleurs" => $pointvaleurs
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $this->validate($request, [
            'distributeur_id' => 'required|min:7|max:7',
            'value_pv' => 'required|min:1',
            'idproduit' => 'required|min:1',
            'Qt' => 'required|min:1',
            'value' => 'required|min:2',
            'prix_product' => 'required|min:2|max:120',
            'pointvaleur_id' => 'required|min:1',
            'created_at' => 'required|date_format:d/m/Y'
        ]);

        //return $request;

        $distribInfos = Distributeur::where('distributeur_id', $request->distributeur_id)->first();
        $RequestedDate =  Carbon::createFromFormat('d/m/Y', $request->created_at)->format('d-m-Y');
        $period = Carbon::createFromFormat('d/m/Y', $request->created_at)->format('Y-m');

        $products = Achat::where('id', $id)->first();
        $dividende = $request->value_pv - $products->pointvaleur;
        if($dividende < 0){
            $operand = false;
            $dividende = $dividende * -1;
        } else {
            $operand = true;
            $dividende = $dividende;
        }
        //return [$operand, $dividende];
        $products->id_distrib_parent = $distribInfos->id_distrib_parent;
        $products->distributeur_id = $request->distributeur_id;
        $products->period = $period;
        $products->pointvaleur = $request->value_pv;
        $products->products_id = $request->idproduit;
        $products->qt = $request->Qt;
        $products->montant = $request->value;
        $products->created_at = Carbon::parse($RequestedDate);
        $products->update();

        $eternalhelpers = new EternalHelper();
        if($operand) {
            $addnewcumul = $eternalhelpers->addNewCumul($request->distributeur_id, $dividende, $period);
        } else {
            $addnewcumul = $eternalhelpers->subNewCumul($request->distributeur_id, $dividende, $period);
        }

        $levelInsert = Level_current_test::where('distributeur_id', $request->distributeur_id)->where('period', $period)->first();
        $etoiles = $eternalhelpers->avancementGrade($request->distributeur_id, 1, $levelInsert->cumul_individuel, $levelInsert->cumul_collectif);
        //return $etoiles;

        if($etoiles)
        {
            $levelInsert->etoiles = $etoiles;
            $levelInsert->update();
        }

        if($operand) {
            $addcumultoparain = $eternalhelpers->addCumulToParainsDebug($levelInsert->id_distrib_parent, $dividende, $period);
        } else {
            $addcumultoparain = $eternalhelpers->subCumulToParainsDebug($levelInsert->id_distrib_parent, $dividende, $period);
        }

        //return $addcumultoparain;

        flash(message: 'action executer avec succes')->success();
        return Redirect::to(route('achats.index'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $products = Achat::where('id', $id)->first();
        $eternalhelpers = new EternalHelper();

        if($products->online == 'off')
        {
            $lastAchat = Achat::orderBy('period', 'DESC')->first();
            $lastAchatPeriod = Carbon::createFromFormat('Y-m', $lastAchat->period);
            $diffmonths = $lastAchatPeriod->diffInMonths(Carbon::createFromFormat('Y-m', $products->period));
            $periodCarbon = Carbon::createFromFormat('Y-m', $products->period);

            $subNewCumul = $eternalhelpers->subNewCumul($products->distributeur_id, $products->pointvaleur, $products->period);
            $level = Level_current_test::where('distributeur_id', $products->distributeur_id)->where('period', $products->period)->first();
            $etoiles = $eternalhelpers->avancementGrade($products->distributeur_id, 1, $level->cumul_individuel, $level->cumul_collectif);

            if($etoiles)
            {
                $level->etoiles = $etoiles;
                $level->update();
            }

            $subcumultoparain = $eternalhelpers->subCumulToParainsDebug($level->id_distrib_parent, $products->pointvaleur, $products->period);

            for($i=1; $i<=$diffmonths; $i++)
            {
                $mois = $periodCarbon->addMonth();
                $periodup = $mois->format('Y-m');
                $subNewCumulDiffere = $eternalhelpers->subNewCumulDiffere($products->distributeur_id, $products->pointvaleur, $periodup);
                $levelInsert = Level_current_test::where('distributeur_id', $products->distributeur_id)->where('period', $periodup)->first();
                $etoile = $eternalhelpers->avancementGrade($products->distributeur_id, $levelInsert->etoiles, $levelInsert->cumul_individuel, $levelInsert->cumul_collectif);

                if($etoile)
                {
                    $levelInsert->etoiles = $etoile;
                    $levelInsert->update();
                }

                $addcumultoparain = $eternalhelpers->subCumulToParainsDebugDiffere($levelInsert->id_distrib_parent, $products->pointvaleur, $periodup);
            }
        }
        else {

            $addnewcumul = $eternalhelpers->subNewCumul($products->distributeur_id, $products->pointvaleur, $products->period);

            $levelInsert = Level_current_test::where('distributeur_id', $products->distributeur_id)->where('period', $products->period)->first();
            $etoiles = $eternalhelpers->avancementGrade($products->distributeur_id, 1, $levelInsert->cumul_individuel, $levelInsert->cumul_collectif);
            //return $etoiles;

            if($etoiles)
            {
                $levelInsert->etoiles = $etoiles;
                $levelInsert->update();
            }

            $addcumultoparain = $eternalhelpers->subCumulToParainsDebug($levelInsert->id_distrib_parent, $products->pointvaleur, $products->period);

        }

        $products->delete();

        flash(message: 'action executer avec succes')->success();
        return Redirect::to(route('achats.index'));

    }

    public function stored(Request $request)
    {
        $this->validate($request, [
            'distributeur_id' => 'required|min:7|max:7',
            'value_pv' => 'required|min:1',
            'idproduit' => 'required|min:1',
            'Qt' => 'required|min:1',
            'value' => 'required|min:2',
            'prix_product' => 'required|min:2|max:120',
            'pointvaleur_id' => 'required|min:1',
            'created_at' => 'required|date_format:d/m/Y'
        ]);

        $eternalhelpers = new EternalHelper();

        $distribInfos = Distributeur::where('distributeur_id', $request->distributeur_id)->first();
        $requestperiod = Carbon::createFromFormat('d/m/Y', $request->created_at);
        $products = Achat::orderBy('period', 'DESC')->first();
        $anterior = $requestperiod->lessThanOrEqualTo(Carbon::createFromFormat('d/m/Y', '02/01/2024'));

        if($anterior)
        {
            $period = '2024-02';
            $periodCarbon = Carbon::parse($period);
        }
        else {
            $period = $requestperiod->format('Y-m');
            $periodCarbon = Carbon::parse($period);
        }

        $diffmonths = $periodCarbon->diffInMonths($products->period);

        $products = new Achat();
        $products->id_distrib_parent = $distribInfos->id_distrib_parent;
        $products->distributeur_id = $request->distributeur_id;
        $products->period = $period;
        $products->pointvaleur = $request->value_pv;
        $products->products_id = $request->idproduit;
        $products->qt = $request->Qt;
        $products->montant = $request->value;
        $products->online = 'off';
        $products->created_at = Carbon::createFromFormat('d/m/Y', $request->created_at)->format('d-m-Y');
        $products->save();

        $addnewcumul = $eternalhelpers->addNewCumul($request->distributeur_id, $request->value_pv, $period);
        $etoiles = $eternalhelpers->avancementGrade($request->distributeur_id, $addnewcumul[0]['etoiles'], $addnewcumul[0]['cumul_individuel'], $addnewcumul[0]['cumul_collectif']);

        $level = Level_current_test::where('distributeur_id', $request->distributeur_id)->where('period', $period)->first();
        $distribut = Distributeur::where('distributeur_id', $request->distributeur_id)->first();

        if($etoiles > $level->etoiles)
        {
            $level->etoiles = $etoiles;
            $level->update();
            $distribut->etoiles_id = $etoiles;
            $distribut->update();
        }

        $addcumultoparain = $eternalhelpers->addCumulToParainsDebug($level->id_distrib_parent, $request->value_pv, $period);

        for($i=1; $i<=$diffmonths; $i++)
        {
            $mois = $periodCarbon->addMonth();
            $periodup = $mois->format('Y-m');
            $addnewcumuldiffere = $eternalhelpers->addNewCumulDiffere($request->distributeur_id, $request->value_pv, $periodup);
            $etoiles = $eternalhelpers->avancementGrade($request->distributeur_id, $addnewcumuldiffere[0]['etoiles'], $addnewcumuldiffere[0]['cumul_individuel'], $addnewcumuldiffere[0]['cumul_collectif']);

            $levelInsert = Level_current_test::where('distributeur_id', $request->distributeur_id)->where('period', $periodup)->first();
            $distrib = Distributeur::where('distributeur_id', $request->distributeur_id)->first();

            if($etoiles > $levelInsert->etoiles)
            {
                $levelInsert->etoiles = $etoiles;
                $levelInsert->update();
                $distrib->etoiles_id = $etoiles;
                $distrib->update();
            }

            $addcumultoparaindiffere[] = $eternalhelpers->addCumulToParainsDebugDiffere($levelInsert->id_distrib_parent, $request->value_pv, $periodup);

            $mois = $mois;
        }

        flash(message: 'action executer avec succes')->success();
        return back();

    }

    public function getSubdistribids($disitributeurId, $rang)
    {
        $children = [];

        $children[] = $disitributeurId;
        $parentDistributeurs = Distributeur::where('distributeur_id', $disitributeurId)->select('etoiles_id')->get();
        return $parentDistributeurs;
            foreach ($parentDistributeurs as $parent)
            {
                $children[] = $this->getChilrenDistrib($parent->distributeur_id, $children);
            }
        return $children;
    }

    /**
     * Display the specified resource.
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

    public function replaceMe($me)
    {
        return preg_replace('/\$([0-9.-])/', '$1', $me);
    }

    public function reseauCheckLevel($distrib, $collectif, $nbE, $direct)
    {
        $nb_level = array('3', '4', '5', '6', '7', '8', '9', '10', '11');
        //return $nbE;
        if (in_array($nbE, $nb_level)) {
            $reseauCheckLevel = Level::where('etoiles', '>=', $nbE)->where('id_distrib_parent', $distrib)->get();

            if($reseauCheckLevel->isNotEmpty())
            {
                switch($nbE)
                {
                    case 3:

                        if(count($reseauCheckLevel) == 2)
                        {
                            $nb_etoiles = ($collectif >= 2500) ? 4 : 3;
                        }
                        elseif(count($reseauCheckLevel) >= 3)
                        {
                            $nb_etoiles = ($collectif >= 1250) ? 4 : 3;
                        }

                    break;
                    case 4:

                        if(count($reseauCheckLevel) == 2)
                        {
                            $nb_etoiles = ($collectif >= 2500) ? 4 : 3;
                        }
                        elseif(count($reseauCheckLevel) >= 3)
                        {
                            $nb_etoiles = ($collectif >= 1250) ? 4 : 3;
                        }

                    break;
                    case 5:

                        $nb_etoiles = 5;

                    break;

                    case 6:
                        $nb_etoiles = 6;
                    break;

                    case 7:

                        if($reseauCheckLevel->isNotEmpty())
                        {
                            if(count($reseauCheckLevel) == 2)
                            {
                                $nb_etoiles = ($collectif >= 640000) ? 'par défaut 8' : 7;
                            }
                            elseif(count($reseauCheckLevel) >= 2)
                            {
                                $nb_etoiles = ($collectif >= 320000) ? 'par défaut 8' : 7;
                            }
                            else{
                                $nb_etoiles = 7;
                            }
                        }
                        else
                        {
                            //$nb_etoiles = ($collectif >= $cumul) ? 'effort personnel 8' : 7;
                        }

                    break;

                    case 8:
                        $nb_etoiles = 8;
                    break;

                    case 9:
                        $nb_etoiles = 9;
                    break;

                    case 10:
                        $nb_etoiles = 10;
                    break;

                    case 11:
                        $nb_etoiles = 11;
                    break;
                }
            }
            else
            {
                $nb_etoiles = 3;//($collectif >= $cumul) ? 4 : 3;
            }
            return $nb_etoiles;
        }
    }
    /**
     * Store a newly created resource in storage.
     */

    //

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
    }

    public function getChilrenDistrib($disitributeurId)
    {
        $children = [];

        $children[] = $disitributeurId;
        $parentDistributeurs = Distributeur::where('id_distrib_parent', $disitributeurId)->select('distributeur_id')->get();

            foreach ($parentDistributeurs as $parent)
            {
                $children[] = $this->getChilrenDistrib($parent->distributeur_id, $children);
            }
        return $children;
    }

    public function CalculCumlTotal($disitributeurId, $childDistributeurs, $date)
    {
        $reste = [];
        $currentMonth = Carbon::parse($date)->format('m');
        $currentYear = Carbon::parse($date)->format('Y');

        /*
        $childDistributeurs = Level_History::where('id_distrib_parent', $disitributeurId)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') AS new_date, sum(cumul_total) as cumul_total, distributeur_id, id_distrib_parent, created_at")
            ->groupBy('new_date')
            ->get();
        */

        $nbrChildren = count($childDistributeurs);
        if($nbrChildren !=0)
        {
            $pcumul = Level_History::where('distributeur_id', $disitributeurId)
                ->whereMonth('created_at', $currentMonth)
                ->whereYear('created_at', $currentYear)
                ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') AS new_date")
                ->selectRaw("sum(cumul_total) as cumul_total, etoiles, rang, id_distrib_parent, distributeur_id,  created_at, updated_at")
                ->selectRaw("sum(new_cumul) as new_cumul")
                ->selectRaw("sum(cumul_individuel) as cumul_individuel")
                ->selectRaw("sum(cumul_collectif) as cumul_collectif")
                ->groupBy('distributeur_id')
                ->orderBy('new_date', 'ASC')
                ->get();
            $reste[] = $pcumul;
            foreach ($childDistributeurs as $key => $value) {
                //$children[] = $value->distributeur_id;
                $children[] = $this->getChilrenDistrib($value->distributeur_id);
            }
            $childs = $this->array_flatten($children);
            foreach ($childs as $key => $child) {
                $ccumul = Level_History::where('distributeur_id', $child)
                    ->whereMonth('created_at', $currentMonth)
                    ->whereYear('created_at', $currentYear)
                    ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') AS new_date")
                    ->selectRaw("sum(cumul_total) as cumul_total, etoiles, rang, id_distrib_parent, distributeur_id,  created_at, updated_at")
                    ->selectRaw("sum(new_cumul) as new_cumul")
                    ->selectRaw("sum(cumul_individuel) as cumul_individuel")
                    ->selectRaw("sum(cumul_collectif) as cumul_collectif")
                    ->groupBy('distributeur_id')
                    ->orderBy('new_date', 'ASC')
                    ->get();

                $reste[] = $ccumul;
            }
            return $reste;
        }
        else {
            $pcumul = Level_History::where('distributeur_id', $disitributeurId)
                ->whereMonth('created_at', $currentMonth)
                ->whereYear('created_at', $currentYear)
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
    }
}
