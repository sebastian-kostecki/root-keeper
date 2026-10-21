<?php

namespace App\Http\Controllers;

use App\Actions\Cemeteries\StoreCemetery;
use App\Http\Requests\CemeteryStoreRequest;
use App\Http\Resources\CemeteryResource;
use Illuminate\Http\JsonResponse;

class InlineCemeteryController extends Controller
{
    /**
     * Add a cemetery to the user's tree without an Inertia visit and return it.
     */
    public function store(CemeteryStoreRequest $request, StoreCemetery $storeCemetery): JsonResponse
    {
        $cemetery = $storeCemetery->handle($request->user()->tree, $request->cemeteryDetails());

        return CemeteryResource::make($cemetery->load('locality'))->response()->setStatusCode(201);
    }
}
