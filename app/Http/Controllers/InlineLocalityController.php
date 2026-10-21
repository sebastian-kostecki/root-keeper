<?php

namespace App\Http\Controllers;

use App\Actions\Localities\StoreLocality;
use App\Http\Requests\LocalityInlineStoreRequest;
use App\Models\Locality;
use App\Models\Tree;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class InlineLocalityController extends Controller
{
    /**
     * Find the locality of the user's tree by name (any letter case) or add it (name only)
     * without an Inertia visit, and return it: 200 when it already existed, 201 when created.
     */
    public function store(LocalityInlineStoreRequest $request, StoreLocality $storeLocality): JsonResponse
    {
        $tree = $request->user()->tree;
        $name = $request->string('name')->toString();

        $existing = $this->findByName($tree, $name);

        if ($existing !== null) {
            return $this->respond($existing, 200);
        }

        try {
            $locality = $storeLocality->handle($tree, [
                'name' => $name,
                'description' => null,
                'kind' => null,
                'latitude' => null,
                'longitude' => null,
                'historical_affiliation' => null,
                'is_defunct' => false,
                'defunct_note' => null,
            ]);
        } catch (ValidationException $exception) {
            $existing = $this->findByName($tree, $name);

            if ($existing === null) {
                throw $exception;
            }

            return $this->respond($existing, 200);
        }

        return $this->respond($locality, 201);
    }

    private function findByName(Tree $tree, string $name): ?Locality
    {
        return $tree->localities()->whereRaw('lower(name) = lower(?)', [$name])->first();
    }

    private function respond(Locality $locality, int $status): JsonResponse
    {
        return response()->json(['id' => $locality->id, 'name' => $locality->name, 'is_defunct' => $locality->is_defunct], $status);
    }
}
