<?php

namespace App\Support\Import;

final readonly class PersonMatches
{
    /**
     * @param  array<string, PersonMatch>  $matches  keyed by repo id
     */
    public function __construct(
        public array $matches = [],
    ) {}
}
