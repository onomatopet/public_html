<?php

namespace App\Http\Controllers\Distributor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DistributorNetworkController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $distributeur = $user->distributeur;

        if (!$distributeur) {
            return redirect()->route('dashboard')->with('error', 'Profil distributeur non trouvé.');
        }

        // Récupérer les filleuls directs avec leurs statistiques
        $filleuls = $distributeur->filleuls()
            ->with(['levelCurrent', 'filleuls'])
            ->paginate(20);

        $stats = [
            'total_filleuls_directs' => $distributeur->filleuls()->count(),
            'total_reseau' => $this->getNetworkSize($distributeur),
            'grade_moyen' => $this->getAverageGrade($distributeur),
        ];

        return view('distributor.network.index', compact('distributeur', 'filleuls', 'stats'));
    }

    public function tree()
    {
        $user = Auth::user();
        $distributeur = $user->distributeur;

        if (!$distributeur) {
            return redirect()->route('dashboard')->with('error', 'Profil distributeur non trouvé.');
        }

        // Construire l'arbre du réseau
        $networkTree = $this->buildNetworkTree($distributeur, 3); // Limiter à 3 niveaux

        return view('distributor.network.tree', compact('distributeur', 'networkTree'));
    }

    private function getNetworkSize($distributeur)
    {
        // TODO: Implémenter le calcul récursif de la taille du réseau
        return $distributeur->filleuls()->count();
    }

    private function getAverageGrade($distributeur)
    {
        return $distributeur->filleuls()->avg('grade') ?? 0;
    }

    private function buildNetworkTree($distributeur, $maxDepth = 3, $currentDepth = 0)
    {
        if ($currentDepth >= $maxDepth) {
            return null;
        }

        $filleuls = $distributeur->filleuls()->with('levelCurrent')->get();

        return $filleuls->map(function($filleul) use ($maxDepth, $currentDepth) {
            return [
                'distributeur' => $filleul,
                'children' => $this->buildNetworkTree($filleul, $maxDepth, $currentDepth + 1)
            ];
        });
    }
}
