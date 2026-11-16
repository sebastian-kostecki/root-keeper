<?php

namespace App\Support\Import;

/**
 * A source of the repo joined to a source the author added by hand: the manual source keeps its scan and gets the repo
 * id and the values of the description it lacks.
 */
final readonly class PlannedSourceMerge
{
    /**
     * @param  int  $sourceId  the manual source of the tree
     * @param  list<PlannedFill>  $fills  the repo values for the fields of the description the manual source leaves empty
     * @param  PlannedPlace|null  $parish  the parish of the repo source, used when the fill list names `parish`
     * @param  array<string, string|null>  $entries  repo key of each event the repo links the source to => the entry number; the existing link of the manual source takes it only when its own number is empty
     */
    public function __construct(
        public ResearchSource $source,
        public int $sourceId,
        public array $fills = [],
        public ?PlannedPlace $parish = null,
        public array $entries = [],
    ) {}

    /**
     * @param  array<string, string|null>  $entries
     */
    public function withEntries(array $entries): self
    {
        return new self($this->source, $this->sourceId, $this->fills, $this->parish, $entries);
    }

    public function label(): string
    {
        return PlannedSourceUpdate::labelOf($this->source);
    }
}
