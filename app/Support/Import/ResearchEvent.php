<?php

namespace App\Support\Import;

use App\Enums\ConfidenceLevel;

final readonly class ResearchEvent
{
    /**
     * @param  list<ResearchSourceLink>  $sources  the sources that confirm the event, sorted by repo id
     */
    public function __construct(
        public ?ResearchDate $date = null,
        public ?string $place = null,
        public ?ConfidenceLevel $level = null,
        public ?string $note = null,
        public ?string $cemetery = null,
        public array $sources = [],
    ) {}

    /**
     * @param  list<ResearchSourceLink>  $sources
     */
    public function withSources(array $sources): self
    {
        return new self($this->date, $this->place, $this->level, $this->note, $this->cemetery, $sources);
    }
}
