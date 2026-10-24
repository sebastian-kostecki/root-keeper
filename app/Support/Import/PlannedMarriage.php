<?php

namespace App\Support\Import;

final readonly class PlannedMarriage
{
    public function __construct(
        public ResearchMarriage $marriage,
        public string $repoId,
    ) {}
}
