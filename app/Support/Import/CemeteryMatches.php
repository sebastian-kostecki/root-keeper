<?php

namespace App\Support\Import;

final readonly class CemeteryMatches
{
    /**
     * @param  array<string, CemeteryVerdict>  $matches  keyed by {@see PlaceDecisions::cemeteryKey()}
     */
    public function __construct(
        public array $matches = [],
    ) {}
}
