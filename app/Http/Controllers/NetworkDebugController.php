<?php

namespace App\Http\Controllers;

use App\Models\Distributeur; // Assurez-vous que le chemin est correct
use Illuminate\Http\Request;

class NetworkController extends Controller // Exemple de contrôleur
{
    public function showNetwork(int $distributorId)
    {
        // Trouver le distributeur de départ (optionnel, si vous n'avez besoin que de l'ID)
        $distributor = Distributeur::findOrFail($distributorId);

        // Appeler la méthode optimisée sur l'instance ou statiquement si adaptée
        // Ici, on suppose qu'elle est sur l'instance pour accéder à getTable(), etc.
        // mais elle pourrait être rendue statique.
        $networkData = $distributor->getChildrenNetworkOptimized($distributorId);

        // Retourner les données (par exemple en JSON pour une API)
        // return response()->json($networkData);

        // Ou passer à une vue Blade
        return view('layout.network.show', ['network' => $networkData, 'rootDistributor' => $distributor]);
    }
}
