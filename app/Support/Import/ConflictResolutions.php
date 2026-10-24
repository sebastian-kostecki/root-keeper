<?php

namespace App\Support\Import;

/**
 * The author's answers to the value conflicts of a plan: for each conflict key either the value from the repo or the one
 * from the app. A conflict without an answer keeps the value from the app.
 */
final readonly class ConflictResolutions
{
    public const FROM_REPO = 'repo';

    public const FROM_APP = 'app';

    /**
     * @param  array<string, string>  $choices  conflict key => `FROM_REPO` or `FROM_APP`
     */
    public function __construct(public array $choices = []) {}

    public function takesRepo(string $key): bool
    {
        return ($this->choices[$key] ?? self::FROM_APP) === self::FROM_REPO;
    }
}
