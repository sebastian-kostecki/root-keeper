<?php

namespace App\Support\Import;

final readonly class ImportIssue
{
    /**
     * @param  string|null  $repoId  the repo id of the entry the issue belongs to, when the entry names a valid one
     */
    public function __construct(
        public string $file,
        public string $message,
        public ?string $repoId = null,
    ) {}
}
