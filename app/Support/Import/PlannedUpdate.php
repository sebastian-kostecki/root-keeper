<?php

namespace App\Support\Import;

/**
 * A person of the tree that an earlier import already took from the repo: what this import changes in it.
 */
final readonly class PlannedUpdate
{
    /**
     * @param  list<PlannedChange>  $changes  repo changes nobody touched in the app
     * @param  list<PlannedFill>  $fills  values the repo adds to empty fields
     * @param  list<ValueConflict>  $conflicts
     * @param  int  $keptManual  fields changed by hand in the app while the repo did not move, left alone
     * @param  string|null  $birthRepoId  repo key set on the birth: on the one the person has without it, or on the created one; null when nothing is set
     * @param  string|null  $deathRepoId  the same for the death
     * @param  bool  $createBirth  the repo has a birth and the person has none
     * @param  bool  $createDeath  the repo has a death and the person has none
     */
    public function __construct(
        public ResearchPerson $person,
        public int $personId,
        public array $changes,
        public array $fills,
        public array $conflicts,
        public int $keptManual,
        public ?string $birthRepoId,
        public ?string $deathRepoId,
        public bool $createBirth,
        public bool $createDeath,
    ) {}
}
