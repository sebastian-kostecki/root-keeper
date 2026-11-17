<?php

namespace App\Support\Import;

use App\Models\Event;
use App\Models\Source;

/**
 * The manual sources of the tree that may hold the same scan as one new source of the repo.
 */
final readonly class SourceMatch
{
    /**
     * @param  list<Source>  $candidates  in order of the source id
     * @param  list<Event>  $events  the events of the tree the repo source is cited on, in order of the event id
     */
    public function __construct(
        public ResearchSource $source,
        public array $candidates,
        public array $events = [],
    ) {}
}
