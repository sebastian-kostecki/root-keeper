<?php

namespace App\Support\Import;

final readonly class PlannedRelation
{
    /**
     * @param  string  $parentId  repo id
     * @param  string  $childId  repo id
     * @param  int|null  $existingId  id of the relation already in the tree, which only gets the repo id; null for a new one
     */
    public function __construct(
        public string $parentId,
        public string $childId,
        public string $repoId,
        public ?int $existingId = null,
    ) {}
}
