<?php

namespace App\Http\Controllers\API;

use App\Models\usage;
use Illuminate\Database\Eloquent\Builder;

class usageController extends ApiCrudController
{
    protected string $modelClass = usage::class;

    protected array $relations = ['bien'];

    protected array $storeRules = [
        'bien_id' => ['required', 'integer', 'exists:biens,id'],
        'nom_usage' => ['required', 'string', 'max:255'],
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

        return $query->whereHas('bien', fn (Builder $builder) => $builder->where('statut', 'actif'));
    }
}
