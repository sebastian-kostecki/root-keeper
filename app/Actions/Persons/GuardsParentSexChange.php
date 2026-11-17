<?php

namespace App\Actions\Persons;

use App\Enums\Gender;
use App\Models\Person;
use App\Support\ParentChildGraph;
use Illuminate\Validation\ValidationException;

/**
 * The rule on changing a parent's sex, shared by the person form and the research import so that they cannot diverge.
 */
trait GuardsParentSexChange
{
    /**
     * Changing the sex to a known one must not give any child of the person
     * a second parent of that sex. Clearing the sex or keeping it never
     * checks relations, so older inconsistent pairs do not block other edits.
     *
     * @throws ValidationException
     */
    private function guardParentSexChange(Person $person, Gender|string|null $newGender): void
    {
        $new = is_string($newGender) ? Gender::tryFrom($newGender) : $newGender;
        $stored = $person->tree->people()->whereKey($person->id)->lockForUpdate()->firstOrFail()->gender;

        if ($new === null || $new === $stored) {
            return;
        }

        $graph = ParentChildGraph::forTree($person->tree);

        $otherParentIds = collect($graph->childrenOf($person->id))
            ->flatMap(fn (int $childId): array => $graph->parentsOf($childId))
            ->reject(fn (int $parentId): bool => $parentId === $person->id)
            ->unique()
            ->all();

        $conflicts = $person->tree->people()
            ->whereIn('id', $otherParentIds)
            ->where('gender', $new)
            ->exists();

        if ($conflicts) {
            throw ValidationException::withMessages([
                'gender' => $new === Gender::Male
                    ? 'Ta osoba jest rodzicem dziecka, które ma już ojca.'
                    : 'Ta osoba jest rodzicem dziecka, które ma już matkę.',
            ]);
        }
    }
}
