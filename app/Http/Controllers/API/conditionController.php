<?php

namespace App\Http\Controllers\API;

use App\Models\condition;
use Illuminate\Database\Eloquent\Builder;

class conditionController extends ApiCrudController
{
    protected string $modelClass = condition::class;

    protected array $relations = ['contrat'];

    protected array $storeRules = [
        'contrat_id' => ['required', 'integer', 'exists:contrats,id'],
        'description' => ['required', 'string'],
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
}
