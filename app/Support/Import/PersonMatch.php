<?php

namespace App\Support\Import;

use App\Enums\MatchVerdict;

final readonly class PersonMatch
{
    /**
     * @param  list<MatchCandidate>  $candidates  in order of the tree person id
     */
    public function __construct(
        public ResearchPerson $person,
        public MatchVerdict $verdict,
        public array $candidates,
    ) {}
}
