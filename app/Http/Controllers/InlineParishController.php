<?php

namespace App\Http\Controllers;

use App\Actions\Parishes\StoreParish;
use App\Http\Requests\ParishStoreRequest;
use App\Http\Resources\ParishResource;
use Illuminate\Http\JsonResponse;

class InlineParishController extends Controller
{
    /**
     * Add a parish or office to the user's tree without an Inertia visit and return it.
     */
    public function store(ParishStoreRequest $request, StoreParish $storeParish): JsonResponse
    {
        $parish = $storeParish->handle(
            $request->user()->tree,
            $request->parishDetails(),
        );

        return ParishResource::make($parish->load('locality'))->response()->setStatusCode(201);
    }
}
