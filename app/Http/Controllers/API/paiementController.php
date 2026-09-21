<?php

namespace App\Http\Controllers\API;

use App\Models\contrat;
use App\Models\paiement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class paiementController extends ApiCrudController
{
    protected string $modelClass = paiement::class;

    protected array $relations = ['contrat'];

    protected array $storeRules = [
        'contrat_id' => ['required', 'integer', 'exists:contrats,id'],
        'mode_paiement' => ['required', 'string', 'max:100'],
        'montant_paiement' => ['required', 'integer', 'min:0'],
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

        $contrat = contrat::query()->findOrFail($data['contrat_id']);

        if ($contrat->etat !== 'signe') {
            return response()->json([
                'message' => 'Le paiement est possible uniquement pour un contrat signe.',
            ], 422);
        }

        $item = $this->modelClass::query()->create($data);
        $item->load($this->relations);

        return response()->json($item, 201);
    }
}
