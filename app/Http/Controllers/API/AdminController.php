<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\bien;
use App\Models\contrat;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        if ($guard = $this->guardAdmin($request)) {
            return $guard;
        }

        $pendingUsers = User::query()->where('statut', 'inactif')->count();
        $pendingBiens = bien::query()->where('statut', 'inactif')->count();
        $bailleurs = User::query()->where('role', 'bailleur')->count();
        $locataires = User::query()->where('role', 'locataire')->count();

        return response()->json([
            'pending_users_count' => $pendingUsers,
            'pending_biens_count' => $pendingBiens,
            'bailleurs_count' => $bailleurs,
            'locataires_count' => $locataires,
        ]);
    }

    public function pendingUsers(Request $request): JsonResponse
    {
        if ($guard = $this->guardAdmin($request)) {
            return $guard;
        }

        $users = User::query()
            ->where('statut', 'inactif')
            ->orderByDesc('created_at')
            ->get();

        return response()->json($users);
    }

    public function validateUser(Request $request, int $id): JsonResponse
    {
        if ($guard = $this->guardAdmin($request)) {
            return $guard;
        }

        $user = User::query()->findOrFail($id);
        $user->update(['statut' => 'actif']);

        return response()->json([
            'message' => 'Utilisateur valide avec succes.',
            'user' => $user->fresh(),
        ]);
    }

    public function deleteUser(Request $request, int $id): JsonResponse
    {
        if ($guard = $this->guardAdmin($request)) {
            return $guard;
        }

        $actor = $request->user();

        if ((int) $actor?->id === $id) {
            return response()->json([
                'message' => 'Vous ne pouvez pas supprimer votre propre compte administrateur.',
            ], 422);
        }

        $user = User::query()->findOrFail($id);

        if ($user->role === 'bailleur' && $this->bailleurHasEverHadTenant((int) $user->id)) {
            return response()->json([
                'message' => 'Suppression impossible: ce bailleur a deja eu au moins un locataire.',
            ], 422);
        }

        if ($user->role === 'locataire' && $this->locataireHasEverRented((int) $user->id)) {
            return response()->json([
                'message' => 'Suppression impossible: ce locataire a deja loue un bien.',
            ], 422);
        }

        $user->delete();

        return response()->json([
            'message' => 'Utilisateur supprime avec succes.',
        ]);
    }

    public function pendingBiens(Request $request): JsonResponse
    {
        if ($guard = $this->guardAdmin($request)) {
            return $guard;
        }

        $biens = bien::query()
            ->with(['bailleur', 'images', 'usages'])
            ->where('statut', 'inactif')
            ->orderByDesc('created_at')
            ->get();

        return response()->json($biens);
    }

    public function allBiens(Request $request): JsonResponse
    {
        if ($guard = $this->guardAdmin($request)) {
            return $guard;
        }

        $biens = bien::query()
            ->with(['bailleur', 'images', 'usages', 'conversations.user', 'conversations.contrat'])
            ->orderByDesc('created_at')
            ->get()
            ->map(function (bien $item) {
                $signedConversation = $item->conversations
                    ->filter(fn ($conversation) => $conversation->contrat && $conversation->contrat->etat === 'signe')
                    ->sortByDesc(fn ($conversation) => $conversation->contrat?->date_signature)
                    ->first();

                return [
                    'bien' => $item,
                    'has_ever_been_rented' => $this->bienHasEverBeenRented((int) $item->id),
                    'occupied_by' => $signedConversation?->user,
                    'current_contrat' => $signedConversation?->contrat,
                ];
            })
            ->values();

        return response()->json($biens);
    }

    public function validateBien(Request $request, int $id): JsonResponse
    {
        if ($guard = $this->guardAdmin($request)) {
            return $guard;
        }

        $bien = bien::query()->findOrFail($id);
        $bien->update(['statut' => 'actif']);

        return response()->json([
            'message' => 'Bien valide avec succes.',
            'bien' => $bien->fresh()->load(['bailleur', 'images', 'usages']),
        ]);
    }

    public function deleteBien(Request $request, int $id): JsonResponse
    {
        if ($guard = $this->guardAdmin($request)) {
            return $guard;
        }

        $bien = bien::query()->findOrFail($id);

        if ($this->bienHasEverBeenRented((int) $bien->id)) {
            return response()->json([
                'message' => 'Suppression impossible: ce bien a deja ete loue.',
            ], 422);
        }

        $bien->delete();

        return response()->json([
            'message' => 'Bien supprime avec succes.',
        ]);
    }

    public function bailleursWithBiens(Request $request): JsonResponse
    {
        if ($guard = $this->guardAdmin($request)) {
            return $guard;
        }

        $bailleurs = User::query()
            ->where('role', 'bailleur')
            ->with([
                'biens.images',
                'biens.usages',
                'biens.conversations.contrat',
            ])
            ->orderBy('nom')
            ->get()
            ->map(function (User $bailleur) {
                $hasEverHadTenant = $this->bailleurHasEverHadTenant((int) $bailleur->id);

                return [
                    'bailleur' => $bailleur,
                    'biens' => $bailleur->biens,
                    'has_ever_had_tenant' => $hasEverHadTenant,
                ];
            })
            ->values();

        return response()->json($bailleurs);
    }

    public function locatairesWithOccupiedBien(Request $request): JsonResponse
    {
        if ($guard = $this->guardAdmin($request)) {
            return $guard;
        }

        $locataires = User::query()
            ->where('role', 'locataire')
            ->with([
                'conversations.bien.bailleur',
                'conversations.contrat',
            ])
            ->orderBy('nom')
            ->get()
            ->map(function (User $locataire) {
                $occupiedConversation = $locataire->conversations
                    ->filter(fn ($conversation) => $conversation->contrat && $conversation->contrat->etat === 'signe')
                    ->sortByDesc(fn ($conversation) => $conversation->contrat?->date_signature)
                    ->first();

                return [
                    'locataire' => $locataire,
                    'occupied_bien' => $occupiedConversation?->bien,
                    'current_contrat' => $occupiedConversation?->contrat,
                    'has_ever_rented' => $this->locataireHasEverRented((int) $locataire->id),
                ];
            })
            ->values();

        return response()->json($locataires);
    }

    private function guardAdmin(Request $request): ?JsonResponse
    {
        $user = $request->user();

        if (! $user || $user->role !== 'administrateur') {
            return response()->json([
                'message' => 'Acces reserve aux administrateurs.',
            ], 403);
        }

        return null;
    }

    private function bienHasEverBeenRented(int $bienId): bool
    {
        return contrat::query()
            ->whereHas('conversation', function ($query) use ($bienId): void {
                $query->where('bien_id', $bienId);
            })
            ->exists();
    }

    private function bailleurHasEverHadTenant(int $bailleurId): bool
    {
        return contrat::query()
            ->whereHas('conversation.bien', function ($query) use ($bailleurId): void {
                $query->where('bailleur_id', $bailleurId);
            })
            ->exists();
    }

    private function locataireHasEverRented(int $locataireId): bool
    {
        return contrat::query()
            ->whereHas('conversation', function ($query) use ($locataireId): void {
                $query->where('user_id', $locataireId);
            })
            ->exists();
    }
}
