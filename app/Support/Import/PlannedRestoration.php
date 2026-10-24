<?php

namespace App\Support\Import;

use App\Enums\RepoKeyKind;

/**
 * A record the author deleted in the app and the repo still has, which the author chose to bring back from the repo values.
 *
 * A restored person or event is created from here. A restored relation or wedding is also in the plan's `relations` or
 * `marriages`, where the write takes it from, so that it meets the same rules as a new one.
 */
final readonly class PlannedRestoration
{
    /**
     * @param  string  $label  what comes back, e.g. "Jan Nowak (O-0001)" or "Zgon: Jan Nowak (O-0001)"
     * @param  list<string>  $memberKeys  the other keys of the person that are decided with it
     * @param  PlannedPerson|null  $person  a restored person, created like a new one
     * @param  int|null  $personId  the tree person that gets a restored birth or death
     * @param  ResearchEvent|null  $event  the repo birth or death to create
     */
    public function __construct(
        public string $key,
        public RepoKeyKind $kind,
        public string $label,
        public array $memberKeys = [],
        public ?PlannedPerson $person = null,
        public ?int $personId = null,
        public ?ResearchEvent $event = null,
        public ?PlannedRelation $relation = null,
        public ?PlannedMarriage $marriage = null,
    ) {}
}
