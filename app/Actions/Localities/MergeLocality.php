<?php

namespace App\Actions\Localities;

use App\Models\Locality;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MergeLocality
{
    /**
     * Move every reference of the duplicate to the target, drop the residences the target already has
     * (same person and years), delete the duplicate and fill the target's description from it, atomically.
     * Both localities must belong to the same tree.
     */
    public function handle(Locality $duplicate, Locality $target): Locality
    {
        DB::transaction(function () use ($duplicate, $target): void {
            $lockedIds = [$duplicate->id, $target->id];
            sort($lockedIds);

            $locked = Locality::query()
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
                throw ValidationException::withMessages(['target_id' => __('Wybierz miejscowość ze spisu.')]);
            }

            $lockedDuplicate->parishes()->update(['locality_id' => $lockedTarget->id]);
            $lockedDuplicate->events()->update(['locality_id' => $lockedTarget->id]);
            $lockedDuplicate->residences()->update(['locality_id' => $lockedTarget->id]);
            $lockedDuplicate->cemeterySources()->update(['cemetery_locality_id' => $lockedTarget->id]);

            DB::delete(<<<'SQL'
                DELETE FROM residences r USING residences keep
                WHERE r.locality_id = ? AND keep.locality_id = ?
                  AND keep.person_id = r.person_id AND keep.id < r.id
                  AND keep.year_from IS NOT DISTINCT FROM r.year_from
                  AND keep.year_to IS NOT DISTINCT FROM r.year_to
                SQL, [$lockedTarget->id, $lockedTarget->id]);

            $lockedDuplicate->delete();

            $description = $this->mergedDescription($lockedTarget->description, $lockedDuplicate->description);

            if ($description !== $lockedTarget->description) {
                $lockedTarget->update(['description' => $description]);
            }
        });

        return $target->refresh();
    }

    /**
     * The target's description, extended by the duplicate's one when the target has none or they differ.
     */
    private function mergedDescription(?string $targetDescription, ?string $duplicateDescription): ?string
    {
        $targetText = trim((string) $targetDescription);
        $duplicateText = trim((string) $duplicateDescription);

        if ($duplicateText === '' || $targetText === $duplicateText) {
            return $targetDescription;
        }

        if ($targetText === '') {
            return $duplicateDescription;
        }

        return $targetText."\n\n".$duplicateText;
    }
}
