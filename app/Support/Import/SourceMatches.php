<?php

namespace App\Support\Import;

final readonly class SourceMatches
{
    /**
     * @param  array<string, SourceMatch>  $matches  keyed by repo id; only sources that have a candidate
     */
    public function __construct(
        public array $matches = [],
    ) {}
}
