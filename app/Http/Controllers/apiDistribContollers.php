<?php

namespace App\Http\Controllers;

use App\Models\Distributeur;
use Exception;
use Illuminate\Http\Request;

class apiDistribContollers extends Controller
{
    //
    public function index()
    {
        try {

            $distributeurs = Distributeur::join('levels', 'levels.distributeur_id', '=', 'distributeurs.distributeur_id')
            ->where('distributeurs.id_distrib_parent', 2292123)
            ->get('distributeurs.*', 'levels.*');

            //$distribparents = Distributeur::select('nom_distributeur', 'distributeur_id', 'id', 'id_parent','pnom_distributeur')->get();
            return $this->dataResponse($distributeurs);

            }
            catch (Exception $e) {
                //throw $e;
                return response()->json($e);
            }
    }

    public function dataResponse($data = null)
    {
        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    public function succesResponse($message = null, $data = null)
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $data,
        ]);
    }

    public function errorResponse($message = null, $statusCode = 400)
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
        ], $statusCode);
    }

    public function arrayResponse(array $data)
    {
        return response()->json($data);
    }

}

