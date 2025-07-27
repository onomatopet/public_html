<?php

namespace App\Http\Controllers\Distributor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DistributorDashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $distributeur = $user->distributeur;

        if (!$distributeur) {
            return redirect()->route('dashboard')->with('error', 'Profil distributeur non trouvé.');
        }

        $stats = [
            'grade_actuel' => $distributeur->grade ?? 0,
            'cumul_personnel' => $distributeur->levelCurrent?->cumul_individuel ?? 0,
            'cumul_collectif' => $distributeur->levelCurrent?->cumul_collectif ?? 0,
            'equipe_directe' => $distributeur->filleuls()->count(),
            'total_equipe' => $this->getTotalTeamCount($distributeur),
        ];

        $recentPurchases = $distributeur->achats()->latest()->limit(5)->get();
        $recentBonuses = $distributeur->bonuses()->latest()->limit(5)->get();

        return view('distributor.dashboard', compact('distributeur', 'stats', 'recentPurchases', 'recentBonuses'));
    }

    private function getTotalTeamCount($distributeur)
    {
        // TODO: Implémenter le comptage récursif de l'équipe
        return $distributeur->filleuls()->count();
    }
}
