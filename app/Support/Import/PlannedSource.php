<?php

namespace App\Support\Import;

/**
 * A source of the repo the import adds as a new source of the tree, or brings back after the author deleted it in the app.
 */
final readonly class PlannedSource
{
    /**
     * @param  PlannedPlace|null  $parish  the parish of the source: one of the tree, or one the plan adds; `null` for none
     * @param  array<string, string|null>  $entries  repo key of each event the source is linked to => the entry number; filled for a restored source, whose links come back with it
     */
    public function __construct(
        public ResearchSource $source,
        public ?PlannedPlace $parish = null,
        public array $entries = [],
    ) {}

    /**
     * @param  array<string, string|null>  $entries
     */
    public function withEntries(array $entries): self
    {
        return new self($this->source, $this->parish, $entries);
    }

    /**
     * The source as shown to the author, e.g. "Akt urodzenia 15/1837 (ZR-0012)".
     */
    public function label(): string
    {
        return PlannedSourceUpdate::labelOf($this->source);
    }
}
