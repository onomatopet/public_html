<?php

namespace App\Http\Controllers\Distributor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DistributorProfileController extends Controller
{
    public function show()
    {
        $user = Auth::user();
        $distributeur = $user->distributeur;

        if (!$distributeur) {
            return redirect()->route('dashboard')->with('error', 'Profil distributeur non trouvé.');
        }

        // Charger les relations nécessaires
        $distributeur->load(['parrain', 'levelCurrent', 'achats' => function($query) {
            $query->latest()->limit(10);
        }]);

        return view('distributor.profile', compact('distributeur'));
    }

    public function update(Request $request)
    {
        $user = Auth::user();
        $distributeur = $user->distributeur;

        if (!$distributeur) {
            return redirect()->route('dashboard')->with('error', 'Profil distributeur non trouvé.');
        }

        // Validation des données
        $validated = $request->validate([
            'telephone' => 'nullable|string|max:20',
            'adresse' => 'nullable|string|max:255',
            'ville' => 'nullable|string|max:100',
            'code_postal' => 'nullable|string|max:10',
        ]);

        $distributeur->update($validated);

        return redirect()->route('distributor.profile.show')
            ->with('success', 'Profil mis à jour avec succès.');
    }
}
