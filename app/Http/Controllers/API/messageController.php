<?php

namespace App\Http\Controllers\API;

use App\Models\conversation;
use App\Models\message;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class messageController extends ApiCrudController
{
    protected string $modelClass = message::class;

    protected array $relations = ['conversation', 'sender'];

    protected array $storeRules = [
        'conversation_id' => ['required', 'integer', 'exists:conversations,id'],
        'sender_id' => ['required', 'integer', 'exists:users,id'],
        'contenu' => ['required', 'string'],
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
            return $query->whereHas('conversation.bien', fn (Builder $builder) => $builder->where('bailleur_id', $user->id));
        }

        return $query->whereHas('conversation', fn (Builder $builder) => $builder->where('user_id', $user->id));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->storeRules);
        $user = $request->user();
        $conversation = conversation::query()->findOrFail($data['conversation_id']);

        if ($user?->role !== 'administrateur') {
            $data['sender_id'] = (int) $user?->id;
        }

        if ($user?->role === 'bailleur') {
            $isOwner = (int) $conversation->bien?->bailleur_id === (int) $user->id;

            if (! $isOwner) {
                return response()->json([
                    'message' => 'Vous ne pouvez envoyer des messages que dans vos conversations.',
                ], 403);
            }
        }

        if ($user?->role === 'locataire' && (int) $conversation->user_id !== (int) $user->id) {
            return response()->json([
                'message' => 'Vous ne pouvez envoyer des messages que dans vos conversations.',
            ], 403);
        }

        $item = $this->modelClass::query()->create($data);
        $item->load($this->relations);

        return response()->json($item, 201);
    }
}
