<?php

namespace App\Actions\Cemeteries;

use App\Models\Cemetery;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteCemetery
{
    /**
     * Delete a cemetery that no gravestone and no burial refers to any more. A reference added concurrently is
     * caught by the foreign key (SQLSTATE 23503), which yields the same error as the existence check.
     */
    public function handle(Cemetery $cemetery): void
    {
        try {
            DB::transaction(function () use ($cemetery): void {
                $locked = Cemetery::query()->whereKey($cemetery->id)->lockForUpdate()->firstOrFail();

                if ($locked->sources()->exists() || $locked->burials()->exists()) {
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
     * The error shown when the cemetery is still in use.
     */
    private function inUseException(): ValidationException
    {
        return ValidationException::withMessages(['cemetery' => __('Cmentarz jest używany — scal go z innym zamiast usuwać.')]);
    }
}
