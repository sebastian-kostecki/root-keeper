<?php

namespace App\Support\Import;

/**
 * One event of a repo person that names a place.
 */
final readonly class PlaceUse
{
    public function __construct(
        public string $repoId,
        public string $file,
        public string $eventKey,
    ) {}
}
