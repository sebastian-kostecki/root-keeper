<?php

namespace App\Actions\Parishes;

use App\Enums\ParishKind;
use App\Enums\Religion;
use App\Models\Parish;
use App\Models\Tree;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StoreParish
{
    /**
     * Add a parish or office to the tree's register. A concurrent request can pass validation with the same seat,
     * kind, religion and dedication, so the unique index has the last word.
     *
     * @param  array{locality_id: int, kind: ParishKind, religion: ?Religion, dedication: ?string, clears_name?: bool}  $details
     */
    public function handle(Tree $tree, array $details): Parish
    {
        try {
            return DB::transaction(fn (): Parish => $tree->parishes()->create([
                'locality_id' => $details['locality_id'],
                'kind' => $details['kind'],
                'religion' => $details['religion'],
                'dedication' => $details['dedication'],
            ]));
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['dedication' => __('Ta parafia / urząd już istnieje.')]);
        }
    }
}
