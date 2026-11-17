<?php

namespace App\Support\Import;

/**
 * A cemetery the import adds: its own name (empty for none) and the locality it stands in.
 */
final readonly class PlannedCemetery
{
    /**
     * @param  string|null  $name  the spelling of the cemetery name
     * @param  int|null  $localityId  a locality of the tree
     * @param  string|null  $newLocality  the key of the name of a new locality, when the locality is new
     */
    public function __construct(
        public ?string $name,
        public ?int $localityId = null,
        public ?string $newLocality = null,
    ) {}
}
