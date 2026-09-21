<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class ApiCrudController extends Controller
{
    protected string $modelClass;

    protected array $relations = [];

    protected array $storeRules = [];

    protected array $updateRules = [];

    protected function query(): Builder
    {
        return $this->modelClass::query()->with($this->relations);
    }

    protected function resolveModel(int|string $id): Model
    {
        return $this->query()->findOrFail($id);
    }

    protected function rulesForUpdate(): array
    {
        if ($this->updateRules !== []) {
            return $this->updateRules;
        }

        $rules = [];

        foreach ($this->storeRules as $field => $fieldRules) {
            $normalized = is_array($fieldRules) ? $fieldRules : explode('|', (string) $fieldRules);

            $normalized = array_values(array_filter($normalized, static fn ($rule) => $rule !== 'required'));
            array_unshift($normalized, 'sometimes');

            $rules[$field] = $normalized;
        }

        return $rules;
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 15);
        $perPage = max(1, min($perPage, 100));

        $items = $this->query()->paginate($perPage);

        return response()->json($items);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->storeRules);

        $item = $this->modelClass::query()->create($data);
        $item->load($this->relations);

        return response()->json($item, 201);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json($this->resolveModel($id));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate($this->rulesForUpdate());

        $item = $this->resolveModel($id);
        $item->update($data);
        $item->load($this->relations);

        return response()->json($item);
    }

    public function destroy(int $id): JsonResponse
    {
        $item = $this->resolveModel($id);
        $item->delete();

        return response()->json([
            'message' => 'Suppression effectuee avec succes.',
        ]);
    }
}
