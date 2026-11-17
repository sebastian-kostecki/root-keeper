<?php

namespace App\Support\Import;

/**
 * What the author decided about the identity of every repo person: matched to a tree person, added as new or skipped.
 * The three sets must together cover each repo person exactly once; the planner rejects anything else.
 */
final readonly class IdentityDecisions
{
    /**
     * @param  array<string, int>  $matched  repo id => id of the tree person it is
     * @param  list<string>  $skipped  repo ids left out of this import
     * @param  list<string>  $new  repo ids added as new persons
     */
    public function __construct(
        public array $matched = [],
        public array $skipped = [],
        public array $new = [],
    ) {}

    /**
     * Every repo person becomes a new person, as for an empty tree.
     */
    public static function allNew(ResearchRepoReading $reading): self
    {
        return new self(new: array_map(strval(...), array_keys($reading->persons)));
    }

    public function isSkipped(string $repoId): bool
    {
        return in_array($repoId, $this->skipped, true);
    }
}
