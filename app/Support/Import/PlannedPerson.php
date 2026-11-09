<?php

namespace App\Support\Import;

final readonly class PlannedPerson
{
    /**
     * @param  PlannedPlace|null  $birthPlace  where the created birth stands, `null` for no place
     * @param  PlannedPlace|null  $deathPlace  the same for the death
     * @param  string|null  $burialRepoId  repo key of the burial to create; `null` when the person gets none (the repo has none or its cemetery is not recognized)
     * @param  PlannedPlace|null  $burialPlace  the cemetery of the created burial
     */
    public function __construct(
        public ResearchPerson $person,
        public ?string $note,
        public ?string $birthRepoId,
        public ?string $deathRepoId,
        public ?PlannedPlace $birthPlace = null,
        public ?PlannedPlace $deathPlace = null,
        public ?string $burialRepoId = null,
        public ?PlannedPlace $burialPlace = null,
    ) {}
}
