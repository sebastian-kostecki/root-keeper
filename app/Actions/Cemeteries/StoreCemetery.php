<?php

namespace App\Actions\Cemeteries;

use App\Enums\CemeteryManager;
use App\Enums\Religion;
use App\Models\Cemetery;
use App\Models\Tree;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StoreCemetery
{
    /**
     * Add a cemetery to the tree. A concurrent request can pass validation with the same name in the same
     * locality, so the unique index has the last word.
     *
     * @param  array{name: ?string, locality_id: int, manager: ?CemeteryManager, religion: ?Religion, description: ?string}  $details
     */
    public function handle(Tree $tree, array $details): Cemetery
    {
        try {
            return DB::transaction(fn (): Cemetery => $tree->cemeteries()->create($details));
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => __('W tej miejscowości jest już cmentarz o tej nazwie.')]);
        }
    }
}
