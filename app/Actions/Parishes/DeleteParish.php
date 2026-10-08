<?php

namespace App\Actions\Parishes;

use App\Models\Parish;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteParish
{
    /**
     * Delete a parish that no act refers to any more. An act added concurrently is caught by the
     * foreign key (SQLSTATE 23503), which yields the same error as the count check.
     */
    public function handle(Parish $parish): void
    {
        try {
            DB::transaction(function () use ($parish): void {
                $locked = Parish::query()->whereKey($parish->id)->lockForUpdate()->firstOrFail();

                if ($locked->sources()->exists()) {
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
     * The error shown when the parish is still in use.
     */
    private function inUseException(): ValidationException
    {
        return ValidationException::withMessages(['parish' => __('Parafia jest używana — scal ją z inną zamiast usuwać.')]);
    }
}
