<?php

namespace App\Actions\Parishes;

use App\Models\Parish;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MergeParish
{
    /**
     * Move every act of the duplicate to the target, delete the duplicate and fill the target's empty fields from
     * it, atomically. Each empty one of seat, religion and dedication is filled on its own, so a target that has a
     * seat can end up with the duplicate's religion and dedication; the three are saved together, and only when the
     * resulting key is free. The former name moves on its own. The kind of the target never changes. Both parishes
     * must belong to the same tree.
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
                throw ValidationException::withMessages(['target_id' => __('Wybierz parafię / urząd ze spisu.')]);
            }

            $this->moveInto($lockedDuplicate, $lockedTarget);
        });

        return $target->refresh();
    }

    /**
     * The core of a merge, without its own transaction or locks (the caller holds both): move the acts to the
     * target, delete the duplicate and fill the target's empty fields from it.
     */
    public function moveInto(Parish $duplicate, Parish $target): void
    {
        $duplicate->sources()->update(['parish_id' => $target->id]);

        $duplicateName = $duplicate->name;
        $key = [
            'locality_id' => $target->locality_id ?? $duplicate->locality_id,
            'religion' => $target->religion ?? $duplicate->religion,
            'dedication' => $target->dedication ?? $duplicate->dedication,
        ];

        $duplicate->delete();

        $keyFields = [];

        if ($key['locality_id'] !== $target->locality_id || $key['religion'] !== $target->religion || $key['dedication'] !== $target->dedication) {
            if ($this->keyFree($target, $key['locality_id'], $key['religion']?->value, $key['dedication'])) {
                $keyFields = $key;
            }
        }

        $nameFields = $target->name === null && $duplicateName !== null ? ['name' => $duplicateName] : [];

        $this->fillTarget($target, $keyFields, $nameFields);
    }

    /**
     * Fill the target's empty fields. A parish created or changed concurrently to the resulting key makes the
     * savepoint fail; the key fields then stay as they were, as with a detected collision.
     *
     * @param  array<string, mixed>  $keyFields
     * @param  array<string, mixed>  $nameFields
     */
    private function fillTarget(Parish $target, array $keyFields, array $nameFields): void
    {
        $fields = [...$keyFields, ...$nameFields];

        if ($fields === []) {
            return;
        }

        try {
            DB::transaction(fn () => $target->update($fields));
        } catch (UniqueConstraintViolationException) {
            $target->refresh();

            if ($nameFields !== []) {
                $target->update($nameFields);
            }
        }
    }

    /**
     * Whether no other parish of the tree has the given seat, the target's kind, religion and dedication (letter
     * case of the dedication ignored). Without a seat the key is not enforced.
     */
    private function keyFree(Parish $target, ?int $localityId, ?string $religion, ?string $dedication): bool
    {
        if ($localityId === null) {
            return true;
        }

        return ! Parish::query()
            ->where('tree_id', $target->tree_id)
            ->whereKeyNot($target->id)
            ->where('locality_id', $localityId)
            ->where('kind', $target->kind->value)
            ->whereRaw("coalesce(religion, '') = ?", [$religion ?? ''])
            ->whereRaw("lower(coalesce(dedication, '')) = lower(?)", [$dedication ?? ''])
            ->exists();
    }
}
