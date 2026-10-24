<?php

namespace App\Support\Import;

/**
 * Everything the author decides before the plan is made: who the new repo persons are, what happens to the records the
 * repo no longer has, and whether a record deleted in the app comes back.
 *
 * The planner checks that each set covers exactly the keys the classification asked about.
 */
final readonly class ImportDecisions
{
    /** Remove a record the repo no longer has. */
    public const DELETE = 'delete';

    /** Keep a record the repo no longer has and only forget its repo id. */
    public const DETACH = 'detach';

    /** Create again a record the author deleted in the app. */
    public const RESTORE = 'restore';

    /** Leave a record deleted in the app as it is. */
    public const KEEP = 'keep';

    /**
     * @param  array<string, string>  $removals  key of each record removed in the repo => `DELETE` or `DETACH` (a birth only `DETACH`)
     * @param  array<string, string>  $deletions  key of each record deleted in the app => `RESTORE` or `KEEP`
     */
    public function __construct(
        public IdentityDecisions $identity,
        public array $removals = [],
        public array $deletions = [],
    ) {}

    /**
     * A first import has nothing removed or deleted to decide about.
     */
    public static function forFirstImport(IdentityDecisions $identity): self
    {
        return new self($identity);
    }
}
