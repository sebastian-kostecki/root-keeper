<?php

namespace App\Support\Import;

/**
 * What the author decided about the new sources of the repo that have candidates: a source of the repo is added as a
 * new source unless it is merged into a manual one. One manual source is merged into one source of the repo at most;
 * the planner checks that and that every id is a candidate of the tree.
 */
final readonly class SourceDecisions
{
    /**
     * @param  array<string, int>  $merges  repo id (`ZR-NNNN`) => id of the manual source it joins; no key means a new source
     */
    public function __construct(
        public array $merges = [],
    ) {}

    /**
     * Whether two sources of the repo join the same manual source.
     */
    public function hasDuplicateTargets(): bool
    {
        return count(array_unique($this->merges)) !== count($this->merges);
    }
}
