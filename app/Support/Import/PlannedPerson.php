<?php

namespace App\Support\Import;

final readonly class PlannedPerson
{
    public function __construct(
        public ResearchPerson $person,
        public ?string $note,
        public ?string $birthRepoId,
        public ?string $deathRepoId,
    ) {}
}
