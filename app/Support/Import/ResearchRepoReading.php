<?php

namespace App\Support\Import;

final readonly class ResearchRepoReading
{
    /**
     * @param  array<string, ResearchPerson>  $persons  keyed by repo id
     * @param  list<ResearchMarriage>  $marriages
     * @param  list<ImportIssue>  $skipped
     * @param  list<ImportIssue>  $warnings
     * @param  list<string>  $filesWithoutBlock
     */
    public function __construct(
        public array $persons,
        public array $marriages,
        public array $skipped,
        public array $warnings,
        public array $filesWithoutBlock,
        public int $scannedFiles,
        public int $parentChildRelations,
    ) {}
}
