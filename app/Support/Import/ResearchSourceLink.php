<?php

namespace App\Support\Import;

final readonly class ResearchSourceLink
{
    /**
     * @param  string  $id  repo id of the source, `ZR-NNNN`
     * @param  string|null  $entry  the entry number in the record, e.g. `15/1837`
     */
    public function __construct(
        public string $id,
        public ?string $entry = null,
    ) {}
}
