<?php

namespace App\Actions\Localities;

use App\Enums\LocalityKind;
use App\Models\Locality;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateLocality
{
    /**
     * Update the details of a locality; a missing detail is cleared, and so is the defunct note
     * when the locality is not marked as defunct.
     * A concurrent request can pass validation with the same name, so the unique index has the last word.
     *
     * @param  array{name: string, description: ?string, kind: ?LocalityKind, latitude: ?float, longitude: ?float, historical_affiliation: ?string, is_defunct: bool, defunct_note: ?string}  $details
     */
    public function handle(Locality $locality, array $details): Locality
    {
        try {
            DB::transaction(fn (): bool => $locality->update([
                ...$details,
                'defunct_note' => $details['is_defunct'] ? $details['defunct_note'] : null,
            ]));
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => __('Miejscowość o tej nazwie jest już w spisie.')]);
        }

        return $locality;
    }
}
