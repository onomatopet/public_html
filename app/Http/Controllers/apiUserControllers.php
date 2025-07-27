<?php

namespace App\Http\Controllers;

use App\Http\Requests\LogUserRequest;
use App\Http\Requests\RegisterUserRequest;
use App\Http\Requests\tokenControllerRequest;
use App\Models\Achat;
use App\Models\Bonuse;
use App\Models\Distributeur;
use App\Models\Level_current_test;
use App\Models\Product;
use App\Models\User;
use App\Services\EternalHelper;
use Illuminate\Support\Str;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;

class apiUserControllers extends Controller
{
    //
    public function register(RegisterUserRequest $request)
    {
        try {
            $request->validated();
            $distrib = Distributeur::where('distributeur_id', $request->id)->first();
            //!Hash::check($request->password, $user->password
            if(!$distrib)
            {
                return response([
                    'message' => 'Distributeur introuvable'
                ], 422);
            }

            $user = new user();
            $user->name = $distrib->nom_distributeur.' '.$distrib->pnom_distributeur;
            $user->email = $distrib->nom_distributeur.Str::random($length = 10).'@gmail.com';
            $user->password = Hash::make($request->password, ['rounds' => 12]);
            $user->distributeur_id = $distrib->distributeur_id;
            $user->save();

            $token = Hash::make(Str::random($length = 10), ['rounds' => 12]);

            return response([
                'success' => true,
                'user' => $distrib,
                'token' => $token
            ], 200);

        }
        catch (Exception $e) {
            //throw $e;
            return response()->json($e);
        }
    }

    public function login(LogUserRequest $request)
    {
        try {
            //dd('ça marche');
            //return response()->json('login');
            //return response()->json($user);
            $request->validated();
            $user = User::where('distributeur_id', $request->id)->first();

            if(!$user || !Hash::check($request->password, $user->password))
            {
                return response([
                'success' => false,
                'status_code' => 422,
                'message' => 'ID or password incorrect'
                ], 422);
            }
            /*
            $token = Hash::make(Str::random($length = 10), ['rounds' => 12]);

            $user->remember_token = $token;
            $user->update();
            */
            return response()->json([
                'success' => true,
                'token' => $user->remember_token,
                'user' => $user
            ], 200);

        }
        catch (Exception $e) {
            //throw $e;
            return response()->json($e);
        }
    }

    public function datarequest(tokenControllerRequest $request)
    {
        try {
            //https://eternal-mobile-apps-yztfgl.flutterflow.app/
            //dd('ça marche');
            //return response()->json('login');
            //return response()->json($user);
            $request->validated();
            $user = User::where('distributeur_id', $request->id)->where('remember_token', $request->rememberToken)->first();

            if(!$user)
            {
                return response([
                'success' => false,
                'status_code' => 422,
                'message' => 'Required or incorrect Token'
                ], 422);
            }

            $token = $user->remember_token;
            $date = Achat::select('period')->distinct('period')->first();
            $datefin = Achat::latest('created_at')->select('period')->distinct('period')->first();
            $periodebut = $date->period;
            $period = $datefin->period;
            $helper = new EternalHelper();
            $request->validated();
            $user = User::where('distributeur_id', $request->id)->first();
            $achat = Achat::selectRaw('sum(pointvaleur) as totalachat')->where('distributeur_id', $request->id)->where('period', $period)->first();
            $level = Level_current_test::where('distributeur_id', $request->id)->where('period', $period)->first();
            $bonusList = Bonuse::where('distributeur_id', $request->id)->get();
            $bonusTotal = Bonuse::selectRaw('sum(bonus) as totalbonus')->where('distributeur_id', $request->id)->get();
            $isb = $helper->isBonusEligible($level->etoiles, $level->new_cumul);
            $tauxDirect = $helper->tauxDirectCalculator($level->etoiles);
            $bonusD = $level->new_cumul * $tauxDirect;
            $bonusI = $helper->bonusIndirect($request->id, $level->etoiles, $period);
            $children = $helper->getChilren($request->id, $period);
            $nbchildren = count($children);
            $balancetotal = round($level->new_cumul/$level->cumul_total, 2);
            $bonusActuel = Bonuse::where('distributeur_id', $request->id)->where('period', $period)->first();
            $bonusLast = Bonuse::where('distributeur_id', $request->id)->where('period', '2024-04')->first();


            $achatInfos = [];
            $achats = Achat::where('distributeur_id', $request->id)->where('period', $period)->get();
            foreach ($achats as $value) {
                $infosProduct = Product::where('id', $value->products_id)->first();
                $achatInfos[] = array(
                    'code_product' => $infosProduct->code_product ?? 0,
                    'nom_produit' => $infosProduct->nom_produit ?? 0,
                    'code_product' => $infosProduct->code_product ?? 0,
                    'prix_product' => $infosProduct->prix_product ?? 0,
                    'pointvaleur' => $value->pointvaleur ?? 0,
                    'pvtotal' => $value->pointvaleur * $value->qt,
                    'montanttotal' => $value->montant * $value->qt,
                    'qt' => $value->qt ?? 0,
                    'montant' => $value->montant ?? 0,
                    'created_at' => $value->created_at ?? 0
                );
            }

            //return $achatInfos;

            foreach ($children as $child) {
                if($child->new_cumul > 0)
                {
                    $newtworkAchat[] = $child;
                }
            }

            $diff = $bonusActuel->bonus - $bonusLast->bonus;
            if($diff < 0){
                $deficit = true;
                $activite = round(($diff * -100) / $bonusLast->bonus);
            }else{
                $deficit = false;
                $activite = round(($diff * 100) / $bonusLast->bonus);
            }

            $percentActivite = round($activite / 100, 2);

            $childpercent = $helper->getChildrenNetwork($children);
            $percent = $helper->getPercentCumulCollectif($children);

            //return ['total' => count($childpercent), 'percent' => $percent];
            $pourcentage = round(($percent * 100) / $childpercent, 0);
            $percentage = round($pourcentage / 100, 2);

            $monthPourcentage = round((count($newtworkAchat) * 100) / $nbchildren, 0);
            $monthPercent = $monthPourcentage / 100;

            $totalBonus = round($bonusD + $bonusI, 2);
            $totalxaf = $totalBonus * 500;

            $bonus = array(
                'periodebut' => $periodebut,
                'period' => $period,
                'nbchildren' => $nbchildren,
                'yearPourcentage' => $pourcentage.'%',
                'yearPercent' => $percentage,
                'monthPourcentage' => $monthPourcentage.'%',
                'monthPercent' => $monthPercent,
                'bonusD' => $bonusD,
                'bonusI' => $bonusI,
                'totalBonus' => $totalBonus,
                'deficit' => $deficit,
                'activite' => $activite,
                'percentActivite' => $percentActivite,
                'balancetotal' => $balancetotal,
                'totalxaf' => $totalxaf,
                'achat' => $achat
            );

            return response()->json([
                'success' => true,
                'remember_token' => $token,
                'bonus' => $bonus,
                'isb' => $isb,
                'user' => $user,
                'level' => $level,
                'achatInfos' => $achatInfos,
                'newtworkAchat' => $newtworkAchat,
                'bonusList' => $bonusList,
                'totalbonus' => round($bonusTotal[0]->totalbonus, 2),
                'children' => $children
            ], 200);

        }
        catch (Exception $e) {
            //throw $e;
            return response()->json($e);
        }
    }
}
