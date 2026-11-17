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
     * @param  PlannedPlace|null  $birthPlace  where a created birth stands
     * @param  PlannedPlace|null  $deathPlace  where a created death stands
     * @param  string|null  $burialRepoId  repo key set on the existing burial or on the created one; null when the person gets none
     * @param  bool  $createBurial  the repo has a burial with a recognized cemetery and the person has none
     * @param  PlannedPlace|null  $burialPlace  the cemetery of a created burial
     * @param  list<ResearchSourceLink>  $birthSources  the links a created birth gets to sources of the repo
     * @param  list<ResearchSourceLink>  $deathSources  the same for a created death
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
        public ?PlannedPlace $birthPlace = null,
        public ?PlannedPlace $deathPlace = null,
        public ?string $burialRepoId = null,
        public bool $createBurial = false,
        public ?PlannedPlace $burialPlace = null,
        public array $birthSources = [],
        public array $deathSources = [],
    ) {}
}
