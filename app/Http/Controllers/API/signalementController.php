<?php

namespace App\Http\Controllers\API;

use App\Models\signalement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class signalementController extends ApiCrudController
{
    protected string $modelClass = signalement::class;

    protected array $relations = ['plaignant', 'accuse'];

    protected array $storeRules = [
        'plaignant_id' => ['required', 'integer', 'exists:users,id'],
        'accuse_id' => ['required', 'integer', 'different:plaignant_id', 'exists:users,id'],
        'motif' => ['required', 'string'],
        'preuve1' => ['nullable', 'string', 'max:255'],
        'preuve2' => ['nullable', 'string', 'max:255'],
        'preuve1_file' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/jpg,image/webp,application/pdf', 'max:10240'],
        'preuve2_file' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/jpg,image/webp,application/pdf', 'max:10240'],
    ];

    protected array $updateRules = [
        'plaignant_id' => ['sometimes', 'required', 'integer', 'exists:users,id'],
        'accuse_id' => ['sometimes', 'required', 'integer', 'different:plaignant_id', 'exists:users,id'],
        'motif' => ['sometimes', 'required', 'string'],
        'preuve1' => ['nullable', 'string', 'max:255'],
        'preuve2' => ['nullable', 'string', 'max:255'],
        'preuve1_file' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp,image/jpg,application/pdf', 'max:10240'],
        'preuve2_file' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/jpg,image/webp,application/pdf', 'max:10240'],
    ];

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->storeRules);

        $this->ensurePreuve1Provided($request, $data);

        $data['preuve1'] = $this->resolveProofValue($request, 'preuve1_file', $data['preuve1'] ?? null);
        $data['preuve2'] = $this->resolveProofValue($request, 'preuve2_file', $data['preuve2'] ?? null);
        unset($data['preuve1_file'], $data['preuve2_file']);

        $item = $this->modelClass::query()->create($data);
        $item->load($this->relations);

        return response()->json($item, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate($this->rulesForUpdate());

        $item = $this->resolveModel($id);

        if ($request->hasFile('preuve1_file')) {
            $this->deletePublicFileFromUrl($item->preuve1);
            $data['preuve1'] = $this->resolveProofValue($request, 'preuve1_file', null);
        }

        if ($request->hasFile('preuve2_file')) {
            $this->deletePublicFileFromUrl($item->preuve2);
            $data['preuve2'] = $this->resolveProofValue($request, 'preuve2_file', null);
        }

        unset($data['preuve1_file'], $data['preuve2_file']);

        if (array_key_exists('preuve1', $data) && $data['preuve1'] !== $item->preuve1) {
            $this->deletePublicFileFromUrl($item->preuve1);
        }

        if (array_key_exists('preuve2', $data) && $data['preuve2'] !== $item->preuve2) {
            $this->deletePublicFileFromUrl($item->preuve2);
        }

        $item->update($data);
        $item->load($this->relations);

        return response()->json($item);
    }

    public function destroy(int $id): JsonResponse
    {
        $item = $this->resolveModel($id);
        $this->deletePublicFileFromUrl($item->preuve1);
        $this->deletePublicFileFromUrl($item->preuve2);
        $item->delete();

        return response()->json([
            'message' => 'Suppression effectuee avec succes.',
        ]);
    }

    private function ensurePreuve1Provided(Request $request, array $data): void
    {
        if ($request->hasFile('preuve1_file') || filled($data['preuve1'] ?? null)) {
            return;
        }

        throw ValidationException::withMessages([
            'preuve1' => ['La premiere preuve est obligatoire.'],
        ]);
    }

    private function resolveProofValue(Request $request, string $fileField, ?string $textValue): ?string
    {
        if (! $request->hasFile($fileField)) {
            return $textValue;
        }

        $path = $request->file($fileField)->store('signalements/preuves', 'public');

        return asset('storage/'.$path);
    }

    private function deletePublicFileFromUrl(?string $url): void
    {
        if (! $url) {
            return;
        }

        $storagePrefix = asset('storage/');

        if (! str_starts_with($url, $storagePrefix)) {
            return;
        }

        $path = str_replace($storagePrefix, '', $url);

        if ($path !== '' && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
