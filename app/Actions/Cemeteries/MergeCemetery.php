<?php

namespace App\Actions\Cemeteries;

use App\Models\Cemetery;
use App\Support\MergedText;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MergeCemetery
{
    /**
     * Move every gravestone and burial of the duplicate to the target, delete the duplicate and fill the target's
     * empty manager and religion from it, atomically. The target keeps its name and locality; descriptions are
     * joined. Both cemeteries must belong to the same tree, and may lie in different localities.
     */
    public function handle(Cemetery $duplicate, Cemetery $target): Cemetery
    {
        DB::transaction(function () use ($duplicate, $target): void {
            $lockedIds = [$duplicate->id, $target->id];
            sort($lockedIds);

            $locked = Cemetery::query()
                ->whereKey($lockedIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $lockedDuplicate = $locked->get($duplicate->id);
            $lockedTarget = $locked->get($target->id);

            if ($lockedDuplicate === null || $lockedTarget === null
                || $lockedDuplicate->id === $lockedTarget->id
                || $lockedDuplicate->tree_id !== $lockedTarget->tree_id) {
                throw ValidationException::withMessages(['target_id' => __('Wybierz cmentarz ze spisu.')]);
            }

            $this->moveInto($lockedDuplicate, $lockedTarget);
        });

        return $target->refresh();
    }

    /**
     * The core of a merge, without its own transaction or locks (the caller holds both): move the gravestones and
     * the burials (with the target's locality) to the target, fill its empty manager and religion, join the
     * descriptions and delete the duplicate.
     */
    public function moveInto(Cemetery $duplicate, Cemetery $target): void
    {
        $duplicate->sources()->update(['cemetery_id' => $target->id]);
        $duplicate->burials()->update(['cemetery_id' => $target->id, 'locality_id' => $target->locality_id]);

        $manager = $duplicate->manager;
        $religion = $duplicate->religion;
        $description = $duplicate->description;

        $duplicate->delete();

        $target->update([
            'manager' => $target->manager ?? $manager,
            'religion' => $target->religion ?? $religion,
            'description' => MergedText::of($target->description, $description),
        ]);
    }
}
