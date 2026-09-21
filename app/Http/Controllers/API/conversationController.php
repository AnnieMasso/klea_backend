<?php

namespace App\Http\Controllers\API;

use App\Models\bien;
use App\Models\conversation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class conversationController extends ApiCrudController
{
    protected string $modelClass = conversation::class;

    protected array $relations = ['user', 'bien', 'messages', 'contrat'];

    protected array $storeRules = [
        'user_id' => ['required', 'integer', 'exists:users,id'],
        'bien_id' => ['required', 'integer', 'exists:biens,id'],
    ];

    protected function query(): Builder
    {
        $user = request()->user();
        $query = $this->modelClass::query()->with($this->relations);

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->role === 'administrateur') {
            return $query;
        }

        if ($user->role === 'bailleur') {
            return $query->whereHas('bien', fn (Builder $builder) => $builder->where('bailleur_id', $user->id));
        }

        return $query->where('user_id', $user->id);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->storeRules);
        $user = $request->user();
        $bien = bien::query()->findOrFail($data['bien_id']);

        if ($user?->role === 'locataire') {
            $data['user_id'] = $user->id;
        }

        if ($user?->role === 'bailleur' && (int) $bien->bailleur_id !== (int) $user->id) {
            return response()->json([
                'message' => 'Vous ne pouvez creer une conversation que sur vos propres biens.',
            ], 403);
        }

        $canUsePendingBien = $user?->role === 'administrateur'
            || (int) $bien->bailleur_id === (int) ($user?->id ?? 0);

        if ($bien->statut !== 'actif' && ! $canUsePendingBien) {
            return response()->json([
                'message' => 'Ce bien est en attente de validation et n est pas encore accessible.',
            ], 422);
        }

        $item = $this->modelClass::query()->firstOrCreate([
            'user_id' => $data['user_id'],
            'bien_id' => $data['bien_id'],
        ]);
        $item->load($this->relations);

        return response()->json($item, $item->wasRecentlyCreated ? 201 : 200);
    }
}
