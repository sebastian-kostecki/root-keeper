<?php

namespace App\Actions\Localities;

use App\Actions\Cemeteries\MergeCemetery;
use App\Models\Cemetery;
use App\Models\Locality;
use App\Models\Residence;
use App\Support\MergedText;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MergeLocality
{
    /**
     * Move every reference of the duplicate to the target, drop the moved residences the target already has
     * (same person and years), delete the duplicate and fill the target's details from it, atomically.
     * The target's own values win: its empty kind and coordinates (as a pair) are taken from the duplicate,
     * descriptions and historical affiliations are joined, and the target is defunct if either one was; when both
     * have a different defunct note, the target keeps its own and the duplicate's one is added to the description.
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

            $this->mergeCollidingCemeteries($lockedDuplicate, $lockedTarget);
            $lockedDuplicate->cemeteries()->update(['locality_id' => $lockedTarget->id]);

            $lockedDuplicate->parishes()->update(['locality_id' => $lockedTarget->id]);
            $lockedDuplicate->events()->update(['locality_id' => $lockedTarget->id]);
            $lockedDuplicate->residences()->update(['locality_id' => $lockedTarget->id]);

            $this->dropMovedResidencesAlreadyAtTarget($lockedTarget, $movedResidenceIds);

            $lockedDuplicate->delete();

            $this->fillTargetFromDuplicate($lockedTarget, $lockedDuplicate);
        });

        return $target->refresh();
    }

    /**
     * Merge the duplicate's cemeteries that have a counterpart at the target (same name, letter case ignored, a
     * missing name equals a missing name) into it, so that moving the rest cannot break the unique index.
     */
    private function mergeCollidingCemeteries(Locality $duplicate, Locality $target): void
    {
        $targetCemeteries = $target->cemeteries()->get()
            ->keyBy(fn (Cemetery $cemetery): string => mb_strtolower((string) $cemetery->name));

        foreach ($duplicate->cemeteries()->get() as $cemetery) {
            $counterpart = $targetCemeteries->get(mb_strtolower((string) $cemetery->name));

            if ($counterpart !== null) {
                app(MergeCemetery::class)->moveInto($cemetery, $counterpart);
            }
        }
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

        $description = MergedText::of($target->description, $duplicate->description);
        $targetNote = trim((string) $target->defunct_note);
        $duplicateNote = trim((string) $duplicate->defunct_note);

        if ($targetNote !== '' && $duplicateNote !== '' && $targetNote !== $duplicateNote) {
            $description = MergedText::of($description, __('Opis zaniku z „:name”: :note', ['name' => $duplicate->name, 'note' => $duplicateNote]));
        }

        $target->update([
            'description' => $description,
            'historical_affiliation' => MergedText::of($target->historical_affiliation, $duplicate->historical_affiliation),
            'kind' => $target->kind ?? $duplicate->kind,
            'latitude' => $takesCoordinates ? $duplicate->latitude : $target->latitude,
            'longitude' => $takesCoordinates ? $duplicate->longitude : $target->longitude,
            'is_defunct' => $isDefunct,
            'defunct_note' => $isDefunct ? ($target->defunct_note ?? $duplicate->defunct_note) : null,
        ]);
    }
}
