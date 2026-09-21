<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureValidatedBailleur
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role, ['bailleur', 'locataire'], true)) {
            return $next($request);
        }

        if ($user->statut === 'actif') {
            return $next($request);
        }

        if ($this->isAllowedForPendingUser($request, $user->role)) {
            return $next($request);
        }

        return $this->forbiddenResponse();
    }

    private function isAllowedForPendingUser(Request $request, string $role): bool
    {
        if ($request->is('api/auth/*')) {
            return true;
        }

        if ($request->routeIs('biens.index') || $request->routeIs('biens.show')) {
            return true;
        }

        if ($role === 'bailleur' && $request->routeIs('biens.store')) {
            return true;
        }

        return false;
    }

    private function forbiddenResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'Votre compte bailleur est en attente de validation administrateur. Action non autorisee.',
        ], 403);
    }
}
