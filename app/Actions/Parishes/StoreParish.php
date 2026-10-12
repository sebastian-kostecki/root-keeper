<?php

namespace App\Actions\Parishes;

use App\Enums\Religion;
use App\Models\Parish;
use App\Models\Tree;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StoreParish
{
    /**
     * Add a parish to the tree's register. A concurrent request can pass validation
     * with the same name and religion, so the unique index has the last word.
     */
    public function handle(Tree $tree, string $name, ?Religion $religion, ?int $localityId): Parish
    {
        try {
            return DB::transaction(fn (): Parish => $tree->parishes()->create([
                'name' => $name,
                'religion' => $religion,
                'locality_id' => $localityId,
            ]));
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => __('Parafia o tej nazwie i wyznaniu już istnieje.')]);
        }
    }
}
