<?php

namespace App\Http\Controllers\API;

use App\Models\sinistre;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class sinistreController extends ApiCrudController
{
    protected string $modelClass = sinistre::class;

    protected array $relations = ['contrat'];

    protected array $storeRules = [
        'contrat_id' => ['required', 'integer', 'exists:contrats,id'],
        'date_sinistre' => ['required', 'date'],
        'description' => ['required', 'string'],
        'img1' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:10240'],
        'img2' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:10240'],
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
        $data['img1'] = $this->storeFileAsPublicUrl($request, 'img1', 'sinistres');
        $data['img2'] = $this->storeFileAsPublicUrl($request, 'img2', 'sinistres');

        $item = $this->modelClass::query()->create($data);
        $item->load($this->relations);

        return response()->json($item, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate($this->rulesForUpdate());

        $item = $this->resolveModel($id);

        if ($request->hasFile('img1')) {
            $this->deletePublicFileFromUrl($item->img1);
            $data['img1'] = $this->storeFileAsPublicUrl($request, 'img1', 'sinistres');
        }

        if ($request->hasFile('img2')) {
            $this->deletePublicFileFromUrl($item->img2);
            $data['img2'] = $this->storeFileAsPublicUrl($request, 'img2', 'sinistres');
        }

        $item->update($data);
        $item->load($this->relations);

        return response()->json($item);
    }

    public function destroy(int $id): JsonResponse
    {
        $item = $this->resolveModel($id);
        $this->deletePublicFileFromUrl($item->img1);
        $this->deletePublicFileFromUrl($item->img2);
        $item->delete();

        return response()->json([
            'message' => 'Suppression effectuee avec succes.',
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

    private function deletePublicFileFromUrl(?string $url): void
    {
        if (! $url) {
            return;
        }

        $storagePrefix = asset('storage/');
        $path = str_replace($storagePrefix, '', $url);

        if ($path !== '' && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
