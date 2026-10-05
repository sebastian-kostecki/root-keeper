<?php

namespace App\Actions\Parishes;

use App\Enums\Religion;
use App\Models\Parish;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateParish
{
    /**
     * Update the name, religion and seat of a parish; a missing religion or seat is cleared.
     * A concurrent request can pass validation with the same name and religion, so the unique index has the last word.
     */
    public function handle(Parish $parish, string $name, ?Religion $religion, ?int $localityId): Parish
    {
        try {
            DB::transaction(fn (): bool => $parish->update([
                'name' => $name,
                'religion' => $religion,
                'locality_id' => $localityId,
            ]));
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => __('Parafia o tej nazwie i wyznaniu już istnieje.')]);
        }

        return $parish;
    }
}
