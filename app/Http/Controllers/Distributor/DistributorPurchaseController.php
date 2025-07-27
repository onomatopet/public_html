<?php

namespace App\Http\Controllers\Distributor;

use App\Http\Controllers\Controller;
use App\Models\Achat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DistributorPurchaseController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $distributeur = $user->distributeur;

        if (!$distributeur) {
            return redirect()->route('dashboard')->with('error', 'Profil distributeur non trouvé.');
        }

        $achats = Achat::where('distributeur_id', $distributeur->id)
            ->with('product')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $stats = [
            'total_achats' => $achats->count(),
            'total_points' => $achats->sum('point_achat'),
            'achat_moyen' => $achats->avg('point_achat'),
        ];

        return view('distributor.purchases.index', compact('distributeur', 'achats', 'stats'));
    }

    public function show(Achat $purchase)
    {
        $user = Auth::user();
        $distributeur = $user->distributeur;

        if (!$distributeur) {
            return redirect()->route('dashboard')->with('error', 'Profil distributeur non trouvé.');
        }

        // Vérifier que l'achat appartient bien au distributeur
        if ($purchase->distributeur_id !== $distributeur->id) {
            abort(403, 'Accès non autorisé à cet achat.');
        }

        $purchase->load('product');

        return view('distributor.purchases.show', compact('distributeur', 'purchase'));
    }
}
