<?php

namespace App\Support\Import;

final readonly class ParishMatches
{
    /**
     * @param  array<string, ParishVerdict>  $matches  keyed by {@see PlaceDecisions::parishKey()}
     */
    public function __construct(
        public array $matches = [],
    ) {}
}
