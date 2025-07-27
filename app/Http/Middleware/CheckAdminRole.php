<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAdminRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Vérifier que l'utilisateur est connecté
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        // Vérifier que l'utilisateur a les permissions admin
        if (!auth()->user()->hasPermission('access_admin')) {
            abort(403, 'Accès non autorisé. Vous devez être administrateur pour accéder à cette section.');
        }

        // Mettre à jour la dernière connexion si l'utilisateur accède à l'admin
        if (method_exists(auth()->user(), 'updateLastLogin')) {
            auth()->user()->updateLastLogin();
        }

        return $next($request);
    }
}
