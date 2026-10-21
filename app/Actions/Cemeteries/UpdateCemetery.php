<?php

namespace App\Actions\Cemeteries;

use App\Enums\CemeteryManager;
use App\Enums\Religion;
use App\Models\Cemetery;
use Illuminate\Database\QueryException;
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
            return DB::transaction(function () use ($cemetery, $details): Cemetery {
                $locked = Cemetery::query()->whereKey($cemetery->id)->lockForUpdate()->firstOrFail();

                $locked->update($details);

                $locked->burials()->update(['locality_id' => $locked->locality_id]);

                return $locked;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => __('W tej miejscowości jest już cmentarz o tej nazwie.')]);
        } catch (QueryException $exception) {
            if ($exception->getCode() !== '23503') {
                throw $exception;
            }

            throw ValidationException::withMessages(['locality_id' => __('Wybierz miejscowość ze spisu.')]);
        }
    }
}
