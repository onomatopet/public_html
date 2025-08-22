<?php

namespace App\Http\Controllers\Distributor;

use App\Http\Controllers\Controller;
use App\Models\Distributeur;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;

class DistributorProfileController extends Controller
{
    /**
     * Affiche le profil du distributeur
     */
    public function show()
    {
        $user = Auth::user();
        $distributeur = $user->distributeur;

        if (!$distributeur) {
            return redirect()->route('distributor.dashboard')
                ->with('error', 'Profil distributeur non trouvé.');
        }

        // Charger les relations nécessaires
        $distributeur->load(['parent', 'children']);

        // Statistiques du profil
        $stats = $this->getProfileStats($distributeur);

        // Historique des grades
        $gradeHistory = $this->getGradeHistory($distributeur);

        return view('distributor.profile.show', compact(
            'user',
            'distributeur',
            'stats',
            'gradeHistory'
        ));
    }

    /**
     * Affiche le formulaire d'édition
     */
    public function edit()
    {
        $user = Auth::user();
        $distributeur = $user->distributeur;

        if (!$distributeur) {
            return redirect()->route('distributor.dashboard')
                ->with('error', 'Profil distributeur non trouvé.');
        }

        return view('distributor.profile.edit', compact('user', 'distributeur'));
    }

    /**
     * Met à jour le profil
     */
    public function update(Request $request)
    {
        $user = Auth::user();
        $distributeur = $user->distributeur;

        $validated = $request->validate([
            // Informations utilisateur
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,

            // Informations distributeur
            'tel_distributeur' => 'nullable|string|max:20',
            'adress_distributeur' => 'nullable|string|max:255',
            'ville' => 'nullable|string|max:100',
            'code_postal' => 'nullable|string|max:10',
            'pays' => 'nullable|string|max:100',

            // Photo de profil
            'avatar' => 'nullable|image|max:2048'
        ]);

        // Mise à jour utilisateur
        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email']
        ]);

        // Mise à jour distributeur
        if ($distributeur) {
            $distributeur->update([
                'tel_distributeur' => $validated['tel_distributeur'],
                'adress_distributeur' => $validated['adress_distributeur'],
                'ville' => $validated['ville'] ?? null,
                'code_postal' => $validated['code_postal'] ?? null,
                'pays' => $validated['pays'] ?? null
            ]);

            // Gestion de l'avatar
            if ($request->hasFile('avatar')) {
                // Supprimer l'ancien avatar s'il existe
                if ($distributeur->avatar) {
                    Storage::delete($distributeur->avatar);
                }

                // Stocker le nouveau
                $path = $request->file('avatar')->store('avatars', 'public');
                $distributeur->update(['avatar' => $path]);
            }
        }

        return redirect()->route('distributor.profile.show')
            ->with('success', 'Profil mis à jour avec succès.');
    }

    /**
     * Affiche le formulaire de changement de mot de passe
     */
    public function passwordEdit()
    {
        return view('distributor.profile.password');
    }

    /**
     * Met à jour le mot de passe
     */
    public function passwordUpdate(Request $request)
    {
        $validated = $request->validate([
            'current_password' => 'required|current_password',
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        return redirect()->route('distributor.profile.show')
            ->with('success', 'Mot de passe mis à jour avec succès.');
    }

    /**
     * Affiche les paramètres de notification
     */
    public function notifications()
    {
        $user = Auth::user();
        $settings = $user->notification_settings ?? $this->getDefaultNotificationSettings();

        return view('distributor.profile.notifications', compact('settings'));
    }

    /**
     * Met à jour les paramètres de notification
     */
    public function updateNotifications(Request $request)
    {
        $validated = $request->validate([
            'email_new_member' => 'boolean',
            'email_bonus_received' => 'boolean',
            'email_grade_change' => 'boolean',
            'email_monthly_summary' => 'boolean',
            'sms_new_member' => 'boolean',
            'sms_bonus_received' => 'boolean',
            'sms_grade_change' => 'boolean'
        ]);

        $user = Auth::user();
        $user->notification_settings = $validated;
        $user->save();

        return redirect()->route('distributor.profile.notifications')
            ->with('success', 'Paramètres de notification mis à jour.');
    }

    /**
     * Affiche les informations bancaires
     */
    public function banking()
    {
        $distributeur = Auth::user()->distributeur;

        return view('distributor.profile.banking', compact('distributeur'));
    }

    /**
     * Met à jour les informations bancaires
     */
    public function updateBanking(Request $request)
    {
        $validated = $request->validate([
            'bank_name' => 'required|string|max:100',
            'iban' => 'required|string|max:34|regex:/^[A-Z]{2}[0-9]{2}[A-Z0-9]+$/',
            'bic' => 'required|string|max:11',
            'account_holder' => 'required|string|max:255'
        ]);

        $distributeur = Auth::user()->distributeur;
        $distributeur->update([
            'bank_name' => $validated['bank_name'],
            'iban' => $validated['iban'],
            'bic' => $validated['bic'],
            'account_holder' => $validated['account_holder']
        ]);

        return redirect()->route('distributor.profile.banking')
            ->with('success', 'Informations bancaires mises à jour.');
    }

    /**
     * Obtient les statistiques du profil
     */
    protected function getProfileStats(Distributeur $distributeur): array
    {
        return [
            'membre_depuis' => $distributeur->created_at->diffInDays(now()),
            'total_achats' => $distributeur->achats()->sum('montant_total_ligne'),
            'total_bonus' => $distributeur->bonus()->where('status', 'paid')->sum('montant'),
            'equipe_directe' => $distributeur->children()->count(),
            'grade_actuel' => $distributeur->etoiles_id,
            'statut' => $distributeur->statut_validation_periode ? 'Actif' : 'Inactif'
        ];
    }

    /**
     * Obtient l'historique des grades
     */
    protected function getGradeHistory(Distributeur $distributeur): array
    {
        return \App\Models\AvancementHistory::where('distributeur_id', $distributeur->id)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($history) {
                return [
                    'date' => $history->created_at,
                    'ancien_grade' => $history->ancien_grade,
                    'nouveau_grade' => $history->nouveau_grade,
                    'type' => $history->nouveau_grade > $history->ancien_grade ? 'promotion' : 'retrogradation'
                ];
            })
            ->toArray();
    }

    /**
     * Paramètres de notification par défaut
     */
    protected function getDefaultNotificationSettings(): array
    {
        return [
            'email_new_member' => true,
            'email_bonus_received' => true,
            'email_grade_change' => true,
            'email_monthly_summary' => true,
            'sms_new_member' => false,
            'sms_bonus_received' => false,
            'sms_grade_change' => true
        ];
    }
}
