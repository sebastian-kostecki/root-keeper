<?php

namespace App\Actions\Cemeteries;

use App\Enums\CemeteryManager;
use App\Enums\Religion;
use App\Models\Cemetery;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateCemetery
{
    /**
     * Update a cemetery; an empty field is cleared. A burial at the cemetery keeps lying in the cemetery's
     * locality, so moving the cemetery moves its burials in the same transaction. A concurrent request can pass
     * validation with the same name in the same locality, so the unique index has the last word.
     *
     * @param  array{name: ?string, locality_id: int, manager: ?CemeteryManager, religion: ?Religion, description: ?string}  $details
     */
    public function handle(Cemetery $cemetery, array $details): Cemetery
    {
        try {
            DB::transaction(function () use ($cemetery, $details): void {
                $cemetery->update($details);

                $cemetery->burials()->update(['locality_id' => $cemetery->locality_id]);
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => __('W tej miejscowości jest już cmentarz o tej nazwie.')]);
        }

        return $cemetery;
    }
}
