<?php

namespace App\Support\Import;

use App\Enums\MatchElement;
use App\Models\Person;

final readonly class MatchCandidate
{
    /**
     * @param  list<MatchElement>  $agreeing  independent elements, besides the name, on which the repo and the tree person agree
     * @param  list<string>  $contradictions  human-readable descriptions, e.g. "rok urodzenia: repo 1801, drzewo 1830"
     * @param  list<array{0: string, 1: string, 2: string}>  $rows  field, repo value, tree value
     */
    public function __construct(
        public Person $person,
        public array $agreeing,
        public array $contradictions,
        public array $rows,
    ) {}
}
