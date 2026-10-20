<?php

namespace App\Actions\Localities;

use App\Models\Locality;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteLocality
{
    /**
     * Delete a locality that nothing refers to any more. A reference added concurrently is caught by the
     * foreign key (SQLSTATE 23503), which yields the same error as the count check.
     */
    public function handle(Locality $locality): void
    {
        try {
            DB::transaction(function () use ($locality): void {
                $locked = Locality::query()->whereKey($locality->id)->lockForUpdate()->firstOrFail();

                $references = $locked->parishes()->count()
                    + $locked->events()->count()
                    + $locked->residences()->count()
                    + $locked->cemeteries()->count()
                    + $locked->cemeterySources()->count();

                if ($references > 0) {
                    throw $this->inUseException();
                }

                $locked->delete();
            });
        } catch (QueryException $exception) {
            if ($exception->getCode() !== '23503') {
                throw $exception;
            }

            throw $this->inUseException();
        }
    }

    /**
     * The error shown when the locality is still in use.
     */
    private function inUseException(): ValidationException
    {
        return ValidationException::withMessages(['locality' => __('Miejscowość jest używana — scal ją z inną zamiast usuwać.')]);
    }
}
