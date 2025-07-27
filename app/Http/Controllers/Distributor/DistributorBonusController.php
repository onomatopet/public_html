<?php

namespace App\Http\Controllers\Distributor;

use App\Http\Controllers\Controller;
use App\Models\Bonus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DistributorBonusController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $distributeur = $user->distributeur;

        if (!$distributeur) {
            return redirect()->route('dashboard')->with('error', 'Profil distributeur non trouvé.');
        }

        $bonuses = Bonus::where('distributeur_id', $distributeur->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $stats = [
            'total_bonus' => $bonuses->sum('montant'),
            'bonus_moyen' => $bonuses->avg('montant'),
            'dernier_bonus' => $bonuses->first()?->montant ?? 0,
        ];

        return view('distributor.bonuses.index', compact('distributeur', 'bonuses', 'stats'));
    }

    public function show(Bonus $bonus)
    {
        $user = Auth::user();
        $distributeur = $user->distributeur;

        if (!$distributeur) {
            return redirect()->route('dashboard')->with('error', 'Profil distributeur non trouvé.');
        }

        // Vérifier que le bonus appartient bien au distributeur
        if ($bonus->distributeur_id !== $distributeur->id) {
            abort(403, 'Accès non autorisé à ce bonus.');
        }

        return view('distributor.bonuses.show', compact('distributeur', 'bonus'));
    }
}
