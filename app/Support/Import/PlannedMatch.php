<?php

namespace App\Support\Import;

/**
 * A repo person matched to a person of the tree: what the import adds to that person and where it stays silent.
 */
final readonly class PlannedMatch
{
    /**
     * @param  string|null  $note  the "Z repo badawczego" block to append under the person's note
     * @param  string|null  $birthRepoId  repo id of the birth, set on the existing birth or on the created one; null when the repo has no `urodzenie`
     * @param  string|null  $deathRepoId  the same for the death
     * @param  bool  $createBirth  the repo has a birth and the person has none
     * @param  bool  $createDeath  the repo has a death and the person has none
     * @param  list<PlannedFill>  $fills
     * @param  list<ValueConflict>  $conflicts
     */
    public function __construct(
        public ResearchPerson $person,
        public int $personId,
        public ?string $note,
        public ?string $birthRepoId,
        public ?string $deathRepoId,
        public bool $createBirth,
        public bool $createDeath,
        public array $fills,
        public array $conflicts,
    ) {}
}
