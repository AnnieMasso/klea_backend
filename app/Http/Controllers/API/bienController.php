<?php

namespace App\Http\Controllers\API;

use App\Models\bien;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class bienController extends ApiCrudController
{
    protected string $modelClass = bien::class;

    protected array $relations = ['bailleur', 'images', 'usages'];

    protected array $storeRules = [
        'bailleur_id' => ['nullable', 'integer', 'exists:users,id'],
        'titre' => ['required', 'string', 'max:255'],
        'type' => ['required', 'string', 'max:100'],
        'description' => ['required', 'string'],
        'montant' => ['required', 'integer', 'min:0'],
        'modalité_paiement' => ['required', 'string', 'max:100'],
        'statut' => ['nullable', 'in:actif,inactif'],
        'superficie' => ['required', 'integer', 'min:1'],
        'nb_chambres' => ['required', 'integer', 'min:0'],
        'nb_douches' => ['required', 'integer', 'min:0'],
        'ammeublement' => ['required', 'boolean'],
        'ville' => ['required', 'string', 'max:150'],
        'quartier' => ['required', 'string', 'max:150'],
        'lieu_dit' => ['required', 'string', 'max:255'],
        'latitude' => ['nullable', 'string', 'max:80'],
        'longitude' => ['nullable', 'string', 'max:80'],
        'images' => ['required', 'array', 'min:1', 'max:3'],
        'images.*' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:10240'],
        'usages' => ['required', 'array', 'min:1'],
        'usages.*' => ['required', 'string', 'max:255'],
    ];

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = (int) $request->integer('per_page', 15);
        $perPage = max(1, min($perPage, 100));

        $query = $this->modelClass::query()->with([...$this->relations, 'conversations.contrat']);

        if ($user?->role === 'administrateur') {
            // Les administrateurs peuvent voir tous les biens.
        } elseif ($user?->role === 'bailleur') {
            $query->where('bailleur_id', $user->id);
        } else {
            // Les utilisateurs non proprietaires ne voient que les biens valides.
            $query->where('statut', 'actif');
        }

        $items = $query->paginate($perPage)->through(fn (bien $item) => $this->serializeBien($item));

        return response()->json($items);
    }

    public function show(int $id): JsonResponse
    {
        $item = $this->resolveModel($id);
        $user = request()->user();

        if (! $this->canViewBien($item, $user)) {
            return response()->json([
                'message' => 'Ce bien n est pas accessible.',
            ], 403);
        }

        return response()->json($this->serializeBien($item));
    }

    public function store(Request $request): JsonResponse
    {
        $this->normalizeBienPayload($request);
        $data = $request->validate($this->storeRules);
        $user = $request->user();

        $images = $data['images'];
        $usages = $data['usages'];

        unset($data['images'], $data['usages']);

        if ($user?->role === 'bailleur') {
            $data['bailleur_id'] = $user->id;
            // Un bien cree par un bailleur reste en attente jusqu a validation admin.
            $data['statut'] = 'inactif';
        } else {
            $data['statut'] = $data['statut'] ?? 'inactif';
        }

        $item = DB::transaction(function () use ($data, $images, $usages): bien {
            $bien = $this->modelClass::query()->create($data);

            foreach ($images as $uploadedImage) {
                $path = $uploadedImage->store('biens/images', 'public');

                $bien->images()->create([
                    'slug' => asset('storage/'.$path),
                ]);
            }

            foreach ($usages as $nomUsage) {
                $bien->usages()->create([
                    'nom_usage' => $nomUsage,
                ]);
            }

            return $bien;
        });

        $item->load($this->relations);

        return response()->json($item, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->normalizeBienPayload($request);

        $item = $this->resolveModel($id);
        $user = $request->user();

        if (! $this->canManageBien($item, $user)) {
            return response()->json([
                'message' => 'Action non autorisee sur ce bien.',
            ], 403);
        }

        $data = $request->validate($this->rulesForUpdate());

        // Les champs geres dans des endpoints dedies ne sont pas modifiables ici.
        unset($data['images'], $data['usages']);

        if ($user?->role === 'bailleur') {
            unset($data['bailleur_id'], $data['statut']);
        }

        $item->update($data);
        $item->load($this->relations);

        return response()->json($item);
    }

    public function destroy(int $id): JsonResponse
    {
        $item = $this->resolveModel($id);
        $user = request()->user();

        if (! $this->canManageBien($item, $user)) {
            return response()->json([
                'message' => 'Action non autorisee sur ce bien.',
            ], 403);
        }

        $item->delete();

        return response()->json([
            'message' => 'Suppression effectuee avec succes.',
        ]);
    }

    private function normalizeBienPayload(Request $request): void
    {
        $payload = [];

        if ($request->filled('modalite_paiement') && ! $request->filled('modalité_paiement')) {
            $payload['modalité_paiement'] = $request->input('modalite_paiement');
        }

        if ($request->filled('modalitePaiement') && ! $request->filled('modalité_paiement')) {
            $payload['modalité_paiement'] = $request->input('modalitePaiement');
        }

        if ($request->filled('surface') && ! $request->filled('superficie')) {
            $payload['superficie'] = $request->input('surface');
        }

        if ($request->filled('nbSalles') && ! $request->filled('nb_douches')) {
            $payload['nb_douches'] = $request->input('nbSalles');
        }

        if ($request->filled('lieuDit') && ! $request->filled('lieu_dit')) {
            $payload['lieu_dit'] = $request->input('lieuDit');
        }

        if ($request->filled('pays') && ! $request->filled('quartier')) {
            $payload['quartier'] = $request->input('pays');
        }

        if ($payload !== []) {
            $request->merge($payload);
        }
    }

    private function canViewBien(bien $item, ?User $user): bool
    {
        if ($user?->role === 'administrateur') {
            return true;
        }

        if ($item->statut === 'actif') {
            return true;
        }

        return (int) $item->bailleur_id === (int) ($user?->id ?? 0);
    }

    private function canManageBien(bien $item, ?User $user): bool
    {
        if ($user?->role === 'administrateur') {
            return true;
        }

        return $user?->role === 'bailleur' && (int) $item->bailleur_id === (int) $user->id;
    }

    private function serializeBien(bien $item): array
    {
        $signedContractExists = $item->conversations
            ->contains(fn ($conversation) => $conversation->contrat && $conversation->contrat->etat === 'signe');

        return [
            ...$item->toArray(),
            'validation_statut' => $item->statut === 'actif' ? 'verifie' : 'en_attente',
            'occupation_statut' => $signedContractExists ? 'en_location' : 'libre',
        ];
    }
}
