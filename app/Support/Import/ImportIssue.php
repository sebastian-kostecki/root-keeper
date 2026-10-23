<?php

namespace App\Support\Import;

final readonly class ImportIssue
{
    public function __construct(
        public string $file,
        public string $message,
    ) {}
}
