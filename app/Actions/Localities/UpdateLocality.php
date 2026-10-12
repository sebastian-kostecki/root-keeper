<?php

namespace App\Actions\Localities;

use App\Models\Locality;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateLocality
{
    /**
     * Update the name and description of a locality; a missing description is cleared.
     * A concurrent request can pass validation with the same name, so the unique index has the last word.
     */
    public function handle(Locality $locality, string $name, ?string $description): Locality
    {
        try {
            DB::transaction(fn (): bool => $locality->update([
                'name' => $name,
                'description' => $description,
            ]));
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => __('Miejscowość o tej nazwie jest już w spisie.')]);
        }

        return $locality;
    }
}
