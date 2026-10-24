<?php

namespace App\Support\Import;

final readonly class PersonMatches
{
    /**
     * @param  array<string, PersonMatch>  $matches  keyed by repo id
     * @param  string|null  $blockedReason  set when the tree cannot take a first import; nothing else is filled then
     */
    public function __construct(
        public array $matches = [],
        public ?string $blockedReason = null,
    ) {}
}
