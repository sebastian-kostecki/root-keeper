<?php

namespace App\Support\Import;

/**
 * The author's answers to the changes the repo made in fields nobody touched in the app: a change is taken unless its
 * key is listed as rejected, which is also what "approve all" means.
 */
final readonly class ChangeApprovals
{
    /**
     * @param  list<string>  $rejected  keys of the changes the author rejected, e.g. "O-0042/urodzenie/data"
     */
    public function __construct(public array $rejected = []) {}

    public function rejects(string $key): bool
    {
        return in_array($key, $this->rejected, true);
    }
}
