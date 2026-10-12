<?php

namespace App\Actions\Localities;

use App\Models\Locality;
use App\Models\Residence;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MergeLocality
{
    /**
     * Move every reference of the duplicate to the target, drop the moved residences the target already has
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

            $movedResidenceIds = $lockedDuplicate->residences()->get(['id'])->map(fn (Residence $residence): int => $residence->id)->values()->all();

            $lockedDuplicate->parishes()->update(['locality_id' => $lockedTarget->id]);
            $lockedDuplicate->events()->update(['locality_id' => $lockedTarget->id]);
            $lockedDuplicate->residences()->update(['locality_id' => $lockedTarget->id]);
            $lockedDuplicate->cemeterySources()->update(['cemetery_locality_id' => $lockedTarget->id]);

            $this->dropMovedResidencesAlreadyAtTarget($lockedTarget, $movedResidenceIds);

            $lockedDuplicate->delete();

            $description = $this->mergedDescription($lockedTarget->description, $lockedDuplicate->description);

            if ($description !== $lockedTarget->description) {
                $lockedTarget->update(['description' => $description]);
            }
        });

        return $target->refresh();
    }

    /**
     * Delete the moved residences that repeat one already at the target (same person and years); of identical
     * moved rows the lowest id survives. Residences that were already at the target are never touched.
     *
     * @param  array<int, int>  $movedIds
     */
    private function dropMovedResidencesAlreadyAtTarget(Locality $target, array $movedIds): void
    {
        if ($movedIds === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($movedIds), '?'));

        DB::delete(<<<SQL
            DELETE FROM residences r
            WHERE r.id IN ($placeholders)
              AND EXISTS (
                SELECT 1 FROM residences keep
                WHERE keep.locality_id = ? AND keep.person_id = r.person_id AND keep.id <> r.id
                  AND (keep.id < r.id OR keep.id NOT IN ($placeholders))
                  AND keep.year_from IS NOT DISTINCT FROM r.year_from
                  AND keep.year_to IS NOT DISTINCT FROM r.year_to
              )
            SQL, [...$movedIds, $target->id, ...$movedIds]);
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
