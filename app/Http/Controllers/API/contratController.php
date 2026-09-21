<?php

namespace App\Http\Controllers\API;

use App\Models\contrat;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class contratController extends ApiCrudController
{
    protected string $modelClass = contrat::class;

    protected array $relations = ['conversation', 'depart', 'sinistres', 'paiements', 'conditions'];

    protected array $storeRules = [
        'conversation_id' => ['required', 'integer', 'exists:conversations,id'],
        'type' => ['required', 'string', 'max:100'],
        'etat' => ['nullable', 'in:en attente,signe,annule,resilie'],
        'date_resiliation' => ['nullable', 'date'],
        'date_signature' => ['nullable', 'date'],
        'date_annulation' => ['nullable', 'date'],
        'montant_loyer' => ['required', 'integer', 'min:0'],
        'duree' => ['required', 'integer', 'min:1'],
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

        $alreadyExists = contrat::query()
            ->where('conversation_id', $data['conversation_id'])
            ->exists();

        if ($alreadyExists) {
            return response()->json([
                'message' => 'Un contrat existe deja pour cette conversation.',
            ], 422);
        }

        $item = $this->modelClass::query()->create($data);
        $item->load($this->relations);

        return response()->json($item, 201);
    }

    public function signer(int $id): JsonResponse
    {
        $contrat = $this->resolveModel($id);

        if ($contrat->etat === 'annule' || $contrat->etat === 'resilie') {
            return response()->json([
                'message' => 'Ce contrat ne peut plus etre signe.',
            ], 422);
        }

        $contrat->update([
            'etat' => 'signe',
            'date_signature' => $contrat->date_signature ?? Carbon::now()->toDateString(),
        ]);

        return response()->json($contrat->fresh()->load($this->relations));
    }

    public function annuler(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'date_annulation' => ['nullable', 'date'],
        ]);

        $contrat = $this->resolveModel($id);

        if ($contrat->etat === 'resilie') {
            return response()->json([
                'message' => 'Un contrat resilie ne peut pas etre annule.',
            ], 422);
        }

        $contrat->update([
            'etat' => 'annule',
            'date_annulation' => $data['date_annulation'] ?? Carbon::now()->toDateString(),
            'date_resiliation' => null,
        ]);

        return response()->json($contrat->fresh()->load($this->relations));
    }

    public function resilier(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'date_resiliation' => ['required', 'date'],
        ]);

        $contrat = $this->resolveModel($id);

        if ($contrat->etat !== 'signe') {
            return response()->json([
                'message' => 'Seul un contrat signe peut etre resilie.',
            ], 422);
        }

        $contrat->update([
            'etat' => 'resilie',
            'date_resiliation' => $data['date_resiliation'],
        ]);

        return response()->json($contrat->fresh()->load($this->relations));
    }
}
