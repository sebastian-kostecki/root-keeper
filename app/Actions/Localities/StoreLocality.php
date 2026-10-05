<?php

namespace App\Actions\Localities;

use App\Models\Locality;
use App\Models\Tree;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StoreLocality
{
    /**
     * Add a locality to the tree's gazetteer. A concurrent request can pass validation
     * with the same name, so the unique index has the last word.
     */
    public function handle(Tree $tree, string $name, ?string $description): Locality
    {
        try {
            return DB::transaction(fn (): Locality => $tree->localities()->create([
                'name' => $name,
                'description' => $description,
            ]));
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => __('Miejscowość o tej nazwie jest już w spisie.')]);
        }
    }
}
