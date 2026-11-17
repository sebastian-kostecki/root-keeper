<?php

namespace App\Support\Import;

use App\Enums\RepoKeyKind;

/**
 * A record the repo no longer has and what the author decided about it.
 */
final readonly class PlannedRemoval
{
    /**
     * @param  int|null  $recordId  id of the record of the tree
     * @param  string  $decision  `ImportDecisions::DELETE` or `ImportDecisions::DETACH`
     * @param  RemovalImpact|null  $impact  what disappears with the record; only for a removal, a detach loses nothing
     * @param  list<string>  $memberKeys  the other keys of the person that go the same way with it
     */
    public function __construct(
        public string $key,
        public RepoKeyKind $kind,
        public ?int $recordId,
        public string $decision,
        public ?RemovalImpact $impact,
        public array $memberKeys,
    ) {}
}
