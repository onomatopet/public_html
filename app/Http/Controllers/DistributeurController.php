<?php

namespace App\Http\Controllers;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\Distributeur;
use App\Models\Level;
use App\Models\Achat;
use App\Models\Level_current;
use App\Models\Level_current_test;
use App\Models\Level_History;
use App\Services\EternalHelper;
use Illuminate\Support\Arr;

use function PHPUnit\Framework\isNull;

class DistributeurController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //$distributeurs = Distributeur::orderby('created_at', 'ASC')->get();
        //$distributeurs = Distributeur::join('level_current_tests', 'level_current_tests.distributeur_id', '=', 'distributeurs.distributeur_id')->where('level_current_tests.period', '2025-01')->get();
        $distributeurs = Distributeur::all();
            //->paginate(15);
            //return $distributeurs;

        //$distribparents = Distributeur::select('nom_distributeur', 'distributeur_id', 'id', 'id_parent','pnom_distributeur')->get();
        return view('layouts.distrib.index', [
            "distributeurs" => $distributeurs,
            //"distribparents" => $distribparents
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $distributeurs = Distributeur::pluck('distributeur_id', 'id');
        //return $categories->keys();
        return view('layouts.distrib.create',[
            "distributeurs" => $distributeurs
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Validation

        $this->validate($request, [
            'distributeur_id' => 'required|min:7|max:7|',
            'nom_distributeur' => 'required|min:2|max:50',
            'pnom_distributeur' => 'required|min:2|max:50',
            'tel_distributeur',
            'adress_distributeur',
            'etoiles_id' => 'required',
            'new_cumul' => 'required',
            'cumul_individuel' => 'required',
            'id_parent' => 'required',
            'date_ins' => 'required|date_format:d/m/Y'
        ]);

        $date_ins = Carbon::createFromFormat('d/m/Y', $request->date_ins);
        $nbturn =  Carbon::now()->diffInMonths($date_ins);
        $lastDateAchat = Level_current_test::latest('id')->first();

        //$helpers = new EternalHelper();
        //return $helpers->addCumulFromChildrenDebug($request->distributeur_id);

        $child = Level_current_test::selectRaw('SUM(cumul_collectif) as collectif')->where('id_distrib_parent', $request->distributeur_id)->where('period', $lastDateAchat->period)->get();

        if($child){
            $cumul_individuel = $request->cumul_collectif - $child[0]->collectif;
        }
        else {
            $cumul_individuel = $request->cumul_collectif;
        }

        if($nbturn >= 1)
        {
            for($i=0; $i<=$nbturn; $i++)
            {
                $date_incre = Carbon::createFromFormat('d/m/Y', $request->date_ins)->addMonths($i);
                $period = $date_incre->format('Y-m');

                if($i < $nbturn) {
                    $new_cumul = $request->new_cumul ?? 0;
                    $cumul_total = $request->cumul_total ?? 0;
                }
                else {
                    $new_cumul = 0;
                    $cumul_total = 0;
                }

                $tab[] = array(
                    'i' => $i,
                    'date' => $date_incre,
                    'period' => $period,
                    'distributeur_id' => $request->distributeur_id,
                    'etoiles' => $request->etoiles_id,
                    'cumul_individuel' => $cumul_individuel,
                    'new_cumul' => $new_cumul,
                    'cumul_total' => $cumul_total,
                    'cumul_collectif' => $request->cumul_collectif,
                    'id_distrib_parent' => ($request->id_parent)
                );

                $level_currents = new Level_current_test();
                $level_currents->distributeur_id = $request->distributeur_id;
                $level_currents->period = $period ?? 0;
                $level_currents->etoiles = $request->etoiles_id ?? 1;
                $level_currents->cumul_individuel = $cumul_individuel ?? 0;
                $level_currents->new_cumul = $new_cumul ?? 0;
                $level_currents->cumul_total = $cumul_total ?? 0;
                $level_currents->cumul_collectif = $request->cumul_collectif ?? 0;
                $level_currents->id_distrib_parent = ($request->id_parent);
                $level_currents->created_at = $date_ins;
                $level_currents->save();
            }
        }
        else {
            $level_current = new Level_current_test();
            $level_current->distributeur_id = $request->distributeur_id;
            $level_current->period = $period ?? 0;
            $level_current->new_cumul = $request->new_cumul ?? 0;
            $level_current->etoiles = $request->etoiles_id ?? 1;
            $level_current->cumul_individuel = $cumul_individuel ?? 0;
            $level_current->cumul_collectif = $request->cumul_collectif ?? 0;
            $level_current->id_distrib_parent = ($request->id_parent);
            $level_current->created_at = $date_ins;
            $level_current->save();
        }

        $distributeurs = new Distributeur();
        $distributeurs->etoiles_id = $request->etoiles_id ?? 1;
        $distributeurs->distributeur_id = $request->distributeur_id;
        $distributeurs->nom_distributeur = $request->nom_distributeur;
        $distributeurs->pnom_distributeur = $request->pnom_distributeur;
        $distributeurs->tel_distributeur = $request->tel_distributeur ?? null;
        $distributeurs->adress_distributeur = $request->adress_distributeur ?? null;
        $distributeurs->id_distrib_parent = $request->id_parent;
        $distributeurs->created_at = $date_ins;
        $distributeurs->save();

        flash(message: 'action executer avec succes')->success();
        return back();
    }


    public function getSubdistribids($disitributeurId)
    {
        $children = [];

        $children[] = $disitributeurId;
        $parentDistributeurs = Distributeur::where('id_distrib_parent', $disitributeurId)->select('distributeur_id')->get();

            foreach ($parentDistributeurs as $parent)
            {
                $children[] = $this->getSubdistribids($parent->distributeur_id, $children);
            }
        return $children;
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

    public function bonusDirect($iddistrib)
    {
        $pv = Level::where('distributeur_id', $iddistrib)->get();
        dd($pv);
        if($pv[0]->etoiles_id != 0)
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
        return $bonus;
    }

    public function bonusIndirect($iddistrib)
    {
        $children = [];

        //$children[] = $disitributeurId;

        // ON SELECTIONNE LES INFORMATIONS CONCERNANT LE DISTRIBUTEUR A AFFICHER
        $pv = Distributeur::where('distributeur_id', '=', $iddistrib)->get('etoiles_id');

        // ON RECHERCHE DANS SON NETWORK LES EVENTUELS FIEULS
        $parentDistributeurs = Distributeur::join('levels', 'levels.distributeur_id', '=', 'distributeurs.distributeur_id')
            ->where('distributeurs.id_distrib_parent', $iddistrib)
            ->get(['distributeurs.*', 'levels.*']);

        // ON VERIFIE SI SON NETWORK EST PEUPLE OU NULL
        if(isNull($parentDistributeurs)){
            // SI NULL ON RENVOIE LA REPONSE COMME QUOI IL N'A PAS DE FIEUL
            // LE CALCUL DU BONUS INDIRECT NE PEUT PAS SE POURSUIVRE
            return 'Aucun fieul';
        }
            // LE CAS CONTRAIRE LA REPONSE TROUVEE EST POSITIVE
            // ON CHERCHE A DETERMINER LES FIEULS TROUVES
        else {
            //POUR TOUS LES FIEULS TROUVES ON RECHERCHE DANS LEUR NETWORK
            //
            foreach ($parentDistributeurs as $cles => $parent)
            {
                if($parent->id_distrib_parent == $iddistrib)
                {
                    $children[] = $parentDistributeurs[$cles];
                    $children[] = $this->bonusIndirect($parent->distributeur_id);
                }
                //$children[] = $this->bonusCalculate($parent->distributeur_id);
            }
            if(isNull($children)){
                return 'Aucun fieuil';
            }
            else {

                return $parentDistributeurs;
                $indirect = $this->array_flatten($children);
                $results = [];
                switch($pv[0]->etoiles_id)
                {
                    case 2:
                        foreach ($indirect as $key => $valu) {
                            if($valu->etoiles_id < $pv[0]->etoiles_id)
                            {
                                $results[] = array('rang' => $valu->etoiles_id, 'bonus' => ($valu->new_cumul * 6)/100);
                            }else {
                                exit();
                            }
                        }
                    break;
                    case 3:
                        foreach ($indirect as $key => $valu) {
                            if($valu->etoiles_id < $pv[0]->etoiles_id)
                            {
                                $results[] = array('rang' => $valu->etoiles_id, 'bonus' => ($valu->new_cumul * 16)/100);
                            }else {
                                exit();
                            }
                        }
                    break;
                    case 4:
                        foreach ($indirect as $key => $valu) {
                            if($valu->etoiles_id < $pv[0]->etoiles_id)
                            {
                                $results[] = array('rang' => $valu->etoiles_id, 'bonus' => ($valu->new_cumul * 4)/100);
                            }else {
                                exit();
                            }
                        }
                    break;
                    case 5:
                        foreach ($indirect as $key => $valu) {
                            if($valu->etoiles_id < $pv[0]->etoiles_id)
                            {
                                $results[] = array('rang' => $valu->etoiles_id, 'bonus' => ($valu->new_cumul * 4)/100);
                            }else {
                                exit();
                            }
                        }
                    break;
                    case 6:
                        foreach ($indirect as $key => $valu) {
                            if($valu->etoiles_id < $pv[0]->etoiles_id)
                            {
                                $results[] = array('rang' => $valu->etoiles_id, 'bonus' => ($valu->new_cumul * 4)/100);
                            }else {
                                exit();
                            }
                        }
                    break;
                    case 7:
                        foreach ($indirect as $key => $valu) {
                            if($valu->etoiles_id < $pv[0]->etoiles_id)
                            {
                                $results[] = array('rang' => $valu->etoiles_id, 'bonus' => ($valu->new_cumul * 6)/100);
                            }else {
                                exit();
                            }
                        }
                    break;
                    case 8:
                        foreach ($indirect as $key => $valu) {
                            if($valu->etoiles_id < $pv[0]->etoiles_id)
                            {
                                $results[] = array('rang' => $valu->etoiles_id, 'bonus' => ($valu->new_cumul * 3)/100);
                            }else {
                                exit();
                            }
                        }
                    break;
                    case 9:

                        foreach ($indirect as $key => $valu) {
                            if($valu->etoiles_id < $pv[0]->etoiles_id)
                            {
                                $results[] = array('rang' => $valu->etoiles_id, 'bonus' => ($valu->new_cumul * 2)/100);
                            }else {
                                exit();
                            }
                        }
                    default:
                    break;
                }

                $indirect = array_sum($results);
                return $indirect;
            }
        }


    }

    /**
     * Display the specified resource.
     */

    public function show(Request $request, string $id)
    {
        global $result;
        $distrib = [];
        $enfants = $this->getSubdistribids($request->keys());
        $enfants = $this->array_flatten($enfants);

        foreach ($enfants as $value) {
            $result = Achat::join('products', 'products.id','=','achats.products_id')
            ->where('achats.period', '2024-06')
            ->where('achats.distributeur_id', $value)
            ->get(['period', 'achats.distributeur_id', 'products.code_product','nom_produit', 'qt', 'achats.created_at']);
            if(count($result) > 0) $distrib[] = $result;
        }

        return $result;

        $pv = Level_current::where('distributeur_id', '=', $request->keys())->first();
        //return $achats_mois;
        //return $this->bonusDirect($enfants);

        for($i=0; $i < count($enfants); $i++)
        {
            $distri[] = Distributeur::where('id_distrib_parent', $enfants[$i])->get();
            $level[] = Level_current::where('distributeur_id', $enfants[$i])->get(['new_cumul', 'distributeur_id','cumul_individuel','cumul_collectif']);
        }
        $getid = $request->keys();
        $getid = $getid[0];

        $bonusInd = $this->bonusIndirect($enfants);
        $distribut = (object) Arr::flatten($distri);
        $levelel = (object) Arr::flatten($level);
        $bonus = $this->bonusDirect($request->keys());
        return view('layouts.distrib.arbo',compact('distributeurs', 'pv', 'bonus', 'bonusInd', 'distribut', 'levelel'));
            //"distrib" => $distrib->values();
        /*
        $distributeurs = Distributeur::findOrFail($id);
        $pv_mensuel = Achat::where('distributeur_id', '=', $request->keys());
        $pv_total = Achat::where('distributeur_id', '=', $request->keys())->get();
        $pv_mensuel = $pv_mensuel->sum('pointvaleur');
        $enfants = $this->getSubdistribids($request->keys());
        //return $enfants;
        //$enfants = $this->array_flatten($enfants);
        */
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $lastAchat = Achat::orderBy('period', 'DESC')->first();

        $distributeurs = Distributeur::join('level_current_tests', 'level_current_tests.distributeur_id', '=', 'distributeurs.distributeur_id')
            ->where('distributeurs.distributeur_id', $id)
            ->where('level_current_tests.period', $lastAchat->period)
            ->get();
        //return $distributeurs;
        return view('layouts.distrib.edit', [
            "distributeurs" => $distributeurs,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //return [$id, $request->nom_distributeur, $request->pnom_distributeur, $request->tel_distributeur, $request->adress_distributeur];
        // Validation
        $this->validate($request, [
            'distributeur_id' => 'required|min:7|max:7',
            'id_distrib_parent' => 'required|min:7|max:7',
            'nom_distributeur' => 'required|min:2|max:50',
            'pnom_distributeur' => 'required|min:2|max:50',
            'tel_distributeur',
            'adress_distributeur',
        ]);

        $distributeurs = Distributeur::where('distributeur_id', $id);
        $distributeurs->update([
            'nom_distributeur' => $request->nom_distributeur,
            'pnom_distributeur' => $request->pnom_distributeur,
            'tel_distributeur' => $request->tel_distributeur,
            'adress_distributeur' => $request->adress_distributeur,
            'id_distrib_parent' => $request->id_distrib_parent
        ]);

        $Level_current = Level_current_test::where('distributeur_id', $id);
        $Level_current->update([
            'id_distrib_parent' => $request->id_distrib_parent
        ]);

        $Level = Level::where('distributeur_id', $id);
        $Level->update([
            'id_distrib_parent' => $request->id_distrib_parent
        ]);

        flash('Le Produit a été mise à jour')->success();
        return redirect()->route(route: 'distrib.index');
    }

    /**
     * Update the specified resource in storage.
     */
    public function arborescence(Request $request, string $id)
    {
        $distributeurs = Distributeur::findOrFail($id);
        $distrib = Distributeur::all();
        /*
        //$distribparents = Distributeur::where('id_parent','=',$id)->get();
        //$achats = Achat::where('distributeur_id', '=', $request->keys())->get();
        //return $distributeurs;
        //$achats = Achat::all();
        return view('layouts.distrib.arbo', [
            "distrib" => json($distrib),
        ]);*/
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $category = Distributeur::findOrFail($id);
        $category->destroy($id);

        flash( message: 'Le Distributeur a bien été supprimeé')->success();
        return redirect()->route(route: 'distrib.index');
    }
}
