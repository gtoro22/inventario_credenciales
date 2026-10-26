<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Uso: ->middleware('permission:credentials.view') o 'permission:a|b' (cualquiera).
 */
class EnsurePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();
        $slugs = collect($permissions)->flatMap(fn ($p) => explode('|', $p));

        abort_unless($user && $slugs->contains(fn ($slug) => $user->hasPermission($slug)), 403, 'No tienes permiso para realizar esta acción.');

        return $next($request);
    }
}
