<?php

namespace App\Support\Import;

/**
 * A spelling of the repo that the author chose to remember as a variant of a locality of the tree.
 */
final readonly class PlannedAlias
{
    /**
     * @param  list<int>  $holders  ids of the localities whose name or variant had the key of the spelling at planning
     */
    public function __construct(
        public string $name,
        public int $localityId,
        public array $holders = [],
    ) {}
}
