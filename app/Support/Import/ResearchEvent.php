<?php

namespace App\Support\Import;

use App\Enums\ConfidenceLevel;

final readonly class ResearchEvent
{
    public function __construct(
        public ?ResearchDate $date = null,
        public ?string $place = null,
        public ?ConfidenceLevel $level = null,
        public ?string $note = null,
        public ?string $cemetery = null,
    ) {}
}
