<?php

namespace App\Http\Controllers\API;

use App\Models\image;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class imageController extends ApiCrudController
{
    protected string $modelClass = image::class;

    protected array $relations = ['bien'];

    protected array $storeRules = [
        'bien_id' => ['required', 'integer', 'exists:biens,id'],
        'slug' => ['nullable', 'string', 'max:255'],
        'fichier' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:10240'],
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

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->storeRules);
        unset($data['fichier']);

        if ($request->hasFile('fichier')) {
            $path = $request->file('fichier')->store('biens/images', 'public');
            $data['slug'] = asset('storage/'.$path);
        }

        $item = $this->modelClass::query()->create($data);
        $item->load($this->relations);

        return response()->json($item, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate($this->rulesForUpdate());
        unset($data['fichier']);

        $item = $this->resolveModel($id);

        if ($request->hasFile('fichier')) {
            $this->deletePublicFileFromUrl($item->slug);
            $path = $request->file('fichier')->store('biens/images', 'public');
            $data['slug'] = asset('storage/'.$path);
        }

        $item->update($data);
        $item->load($this->relations);

        return response()->json($item);
    }

    public function destroy(int $id): JsonResponse
    {
        $item = $this->resolveModel($id);
        $this->deletePublicFileFromUrl($item->slug);
        $item->delete();

        return response()->json([
            'message' => 'Suppression effectuee avec succes.',
        ]);
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
