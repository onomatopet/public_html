<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsDistributor
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        // Vérifier si l'utilisateur a un profil distributeur
        if (!$user->distributeur) {
            return redirect()->route('home')
                ->with('error', 'Vous devez être enregistré comme distributeur pour accéder à cette zone.');
        }

        // Vérifier si le distributeur est actif
        if (!$user->distributeur->statut_validation_periode) {
            return redirect()->route('home')
                ->with('warning', 'Votre compte distributeur est inactif. Contactez votre parrain ou l\'administration.');
        }

        return $next($request);
    }
}
