<?php

namespace App\Actions\Parishes;

use App\Models\Parish;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MergeParish
{
    /**
     * Move every act of the duplicate to the target, delete the duplicate and fill the target's empty seat and
     * religion from it, atomically. The religion moves only when no other parish of the tree already has the
     * target's name with that religion. Both parishes must belong to the same tree.
     */
    public function handle(Parish $duplicate, Parish $target): Parish
    {
        DB::transaction(function () use ($duplicate, $target): void {
            $lockedIds = [$duplicate->id, $target->id];
            sort($lockedIds);

            $locked = Parish::query()
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
                throw ValidationException::withMessages(['target_id' => __('Wybierz parafię ze spisu.')]);
            }

            $lockedDuplicate->sources()->update(['parish_id' => $lockedTarget->id]);

            $duplicateReligion = $lockedDuplicate->religion;
            $duplicateLocalityId = $lockedDuplicate->locality_id;

            $lockedDuplicate->delete();

            $fields = [];

            if ($lockedTarget->locality_id === null && $duplicateLocalityId !== null) {
                $fields['locality_id'] = $duplicateLocalityId;
            }

            if ($lockedTarget->religion === null && $duplicateReligion !== null && ! $this->nameAndReligionTaken($lockedTarget, $duplicateReligion->value)) {
                $fields['religion'] = $duplicateReligion;
            }

            if ($fields !== []) {
                $lockedTarget->update($fields);
            }
        });

        return $target->refresh();
    }

    /**
     * Whether another parish of the target's tree already has the target's name (letter case ignored) with the given religion.
     */
    private function nameAndReligionTaken(Parish $target, string $religion): bool
    {
        return Parish::query()
            ->where('tree_id', $target->tree_id)
            ->whereKeyNot($target->id)
            ->whereRaw('lower(name) = lower(?)', [$target->name])
            ->whereRaw("coalesce(religion, '') = ?", [$religion])
            ->exists();
    }
}
