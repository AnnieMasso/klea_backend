<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class authController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'prenom' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in(['administrateur', 'bailleur', 'locataire'])],
            'mot_de_passe' => ['required', 'string', 'min:8'],
            'statut' => ['nullable', Rule::in(['actif', 'inactif'])],
            'cni_recto' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp,application/pdf', 'max:10240'],
            'cni_verso' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp,application/pdf', 'max:10240'],
            'assurance_habitation' => ['nullable', 'required_if:role,locataire', 'file', 'mimetypes:image/jpeg,image/png,image/webp,application/pdf', 'max:10240'],
        ]);

        if (! isset($data['statut'])) {
            $data['statut'] = $data['role'] === 'administrateur' ? 'actif' : 'inactif';
        }
        $data['cni_recto'] = $this->storeFileAsPublicUrl($request, 'cni_recto', 'documents/cni');
        $data['cni_verso'] = $this->storeFileAsPublicUrl($request, 'cni_verso', 'documents/cni');
        $data['assurance_habitation'] = $this->storeFileAsPublicUrl($request, 'assurance_habitation', 'documents/assurances');

        $user = User::query()->create($data);
        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'mot_de_passe' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['mot_de_passe'], $user->mot_de_passe)) {
            return response()->json([
                'message' => 'Identifiants invalides.',
            ], 422);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }

    public function confirmIdentity(Request $request): JsonResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (! $user || ! Hash::check($data['password'], $user->mot_de_passe)) {
            return response()->json([
                'success' => false,
                'message' => 'Mot de passe incorrect.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Identite confirmee.',
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'nom' => ['sometimes', 'required', 'string', 'max:255'],
            'prenom' => ['nullable', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
        ]);

        $user?->update($data);

        return response()->json($user?->fresh());
    }

    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = $request->user();

        if (! $user || ! Hash::check($data['current_password'], $user->mot_de_passe)) {
            return response()->json([
                'message' => 'Le mot de passe actuel est incorrect.',
            ], 422);
        }

        $user->update([
            'mot_de_passe' => $data['new_password'],
        ]);

        return response()->json([
            'message' => 'Mot de passe mis a jour avec succes.',
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Deconnexion effectuee avec succes.',
        ]);
    }

    private function storeFileAsPublicUrl(Request $request, string $field, string $directory): ?string
    {
        if (! $request->hasFile($field)) {
            return null;
        }

        $path = $request->file($field)->store($directory, 'public');

        return asset('storage/'.$path);
    }
}
