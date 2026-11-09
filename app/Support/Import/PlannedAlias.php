<?php

namespace App\Support\Import;

/**
 * A spelling of the repo that the author chose to remember as a variant of a locality of the tree.
 */
final readonly class PlannedAlias
{
    public function __construct(
        public string $name,
        public int $localityId,
    ) {}
}
