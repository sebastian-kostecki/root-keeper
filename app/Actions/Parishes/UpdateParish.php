<?php

namespace App\Actions\Parishes;

use App\Enums\ParishKind;
use App\Enums\Religion;
use App\Models\Parish;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateParish
{
    /**
     * Update the seat, kind, religion and dedication of a parish or office; a missing religion or dedication is
     * cleared. The former name is read-only: it is cleared only when `clears_name` asks for it. A concurrent request
     * can pass validation with the same key, so the unique index has the last word.
     *
     * @param  array{locality_id: int, kind: ParishKind, religion: ?Religion, dedication: ?string, clears_name: bool}  $details
     */
    public function handle(Parish $parish, array $details): Parish
    {
        $fields = [
            'locality_id' => $details['locality_id'],
            'kind' => $details['kind'],
            'religion' => $details['religion'],
            'dedication' => $details['dedication'],
        ];

        if ($details['clears_name']) {
            $fields['name'] = null;
        }

        try {
            DB::transaction(fn (): bool => $parish->update($fields));
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['dedication' => __('Ta parafia / urząd już istnieje.')]);
        }

        return $parish;
    }
}
