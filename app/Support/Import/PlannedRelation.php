<?php

namespace App\Support\Import;

final readonly class PlannedRelation
{
    public function __construct(
        public string $parentId,
        public string $childId,
        public string $repoId,
    ) {}
}
