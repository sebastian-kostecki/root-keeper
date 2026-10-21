<?php

namespace App\Actions\Localities;

use App\Enums\LocalityKind;
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
     *
     * @param  array{name: string, description: ?string, kind: ?LocalityKind, latitude: ?float, longitude: ?float, historical_affiliation: ?string, is_defunct: bool, defunct_note: ?string}  $details
     */
    public function handle(Tree $tree, array $details): Locality
    {
        try {
            return DB::transaction(fn (): Locality => $tree->localities()->create([
                ...$details,
                'defunct_note' => $details['is_defunct'] ? $details['defunct_note'] : null,
            ]));
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => __('Miejscowość o tej nazwie jest już w spisie.')]);
        }
    }
}
