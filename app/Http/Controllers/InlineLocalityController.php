<?php

namespace App\Http\Controllers;

use App\Actions\Localities\StoreLocality;
use App\Http\Requests\LocalityStoreRequest;
use Illuminate\Http\JsonResponse;

class InlineLocalityController extends Controller
{
    /**
     * Add a locality (name only) to the user's tree without an Inertia visit and return it.
     */
    public function store(LocalityStoreRequest $request, StoreLocality $storeLocality): JsonResponse
    {
        $locality = $storeLocality->handle(
            $request->user()->tree,
            $request->string('name')->toString(),
            null,
        );

        return response()->json(['id' => $locality->id, 'name' => $locality->name], 201);
    }
}
