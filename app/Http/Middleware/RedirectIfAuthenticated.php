<?php

namespace App\Http\Middleware;

use App\Providers\RouteServiceProvider;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RedirectIfAuthenticated
{
    public function handle(Request $request, Closure $next, ...$guards)
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                $user = Auth::user();

                if ($user->hasPermission('access_admin')) {
                    return redirect()->route('admin.dashboard');
                }

                if ($user->distributeur) {
                    return redirect()->route('distributor.dashboard');
                }

                return redirect()->route('dashboard');
            }
        }

        return $next($request);
    }
}
