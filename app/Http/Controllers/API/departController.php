<?php

namespace App\Http\Controllers\API;

use App\Models\depart;
use App\Models\contrat;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class departController extends ApiCrudController
{
    protected string $modelClass = depart::class;

    protected array $relations = ['contrat'];

    protected array $storeRules = [
        'contrat_id' => ['required', 'integer', 'exists:contrats,id'],
        'date_depart_prevue' => ['required', 'date'],
        'date_confirmation' => ['nullable', 'date'],
        'statut' => ['nullable', 'boolean'],
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
            return $query->whereHas('contrat.conversation.bien', fn (Builder $builder) => $builder->where('bailleur_id', $user->id));
        }

        return $query->whereHas('contrat.conversation', fn (Builder $builder) => $builder->where('user_id', $user->id));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->storeRules);

        $alreadyExists = depart::query()
            ->where('contrat_id', $data['contrat_id'])
            ->exists();

        if ($alreadyExists) {
            return response()->json([
                'message' => 'Un depart existe deja pour ce contrat.',
            ], 422);
        }

        $item = $this->modelClass::query()->create([
            'contrat_id' => $data['contrat_id'],
            'date_depart_prevue' => $data['date_depart_prevue'],
            'date_confirmation' => null,
            'statut' => false,
        ]);
        $item->load($this->relations);

        return response()->json($item, 201);
    }

    public function confirmer(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'date_confirmation' => ['nullable', 'date'],
        ]);

        $depart = $this->resolveModel($id);
        $contrat = contrat::query()->findOrFail($depart->contrat_id);

        if ((bool) $depart->statut === true) {
            return response()->json([
                'message' => 'Ce depart est deja confirme.',
            ], 422);
        }

        $depart->update([
            'statut' => true,
            'date_confirmation' => $data['date_confirmation'] ?? Carbon::now()->toDateString(),
        ]);

        if ($contrat->etat !== 'resilie') {
            $contrat->update([
                'etat' => 'resilie',
                'date_resiliation' => $data['date_confirmation'] ?? Carbon::now()->toDateString(),
            ]);
        }

        return response()->json($depart->fresh()->load($this->relations));
    }
}
