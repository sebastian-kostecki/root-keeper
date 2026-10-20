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
     * (same person and years), delete the duplicate and fill the target's details from it, atomically.
     * The target's own values win: its empty kind and coordinates (as a pair) are taken from the duplicate,
     * descriptions and historical affiliations are joined, and the target is defunct if either one was.
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

            $this->fillTargetFromDuplicate($lockedTarget, $lockedDuplicate);
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
     * Update the target with what the duplicate adds, without overwriting what the target already has.
     */
    private function fillTargetFromDuplicate(Locality $target, Locality $duplicate): void
    {
        $isDefunct = $target->is_defunct || $duplicate->is_defunct;
        $takesCoordinates = ($target->latitude === null || $target->longitude === null)
            && $duplicate->latitude !== null && $duplicate->longitude !== null;

        $target->update([
            'description' => $this->mergedText($target->description, $duplicate->description),
            'historical_affiliation' => $this->mergedText($target->historical_affiliation, $duplicate->historical_affiliation),
            'kind' => $target->kind ?? $duplicate->kind,
            'latitude' => $takesCoordinates ? $duplicate->latitude : $target->latitude,
            'longitude' => $takesCoordinates ? $duplicate->longitude : $target->longitude,
            'is_defunct' => $isDefunct,
            'defunct_note' => $isDefunct ? ($target->defunct_note ?? $duplicate->defunct_note) : null,
        ]);
    }

    /**
     * The target's text, extended by the duplicate's one when the target has none or they differ.
     */
    private function mergedText(?string $targetValue, ?string $duplicateValue): ?string
    {
        $targetText = trim((string) $targetValue);
        $duplicateText = trim((string) $duplicateValue);

        if ($duplicateText === '' || $targetText === $duplicateText) {
            return $targetValue;
        }

        if ($targetText === '') {
            return $duplicateValue;
        }

        return $targetText."\n\n".$duplicateText;
    }
}
