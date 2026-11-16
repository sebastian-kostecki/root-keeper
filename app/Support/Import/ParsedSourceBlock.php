<?php

namespace App\Support\Import;

final readonly class ParsedSourceBlock
{
    /**
     * @param  list<ResearchSource>  $sources  the entries that were read
     * @param  list<ImportIssue>  $skipped  entries (or the whole file) that were left out, with the reason
     * @param  list<ImportIssue>  $warnings
     * @param  int  $entries  how many entries the block lists
     */
    private function __construct(
        public string $file,
        public bool $hasBlock,
        public array $sources,
        public array $skipped,
        public array $warnings,
        public int $entries = 0,
    ) {}

    public static function withoutBlock(string $file): self
    {
        return new self($file, false, [], [], []);
    }

    /**
     * The whole file is skipped, e.g. for a YAML error.
     */
    public static function skippedFile(string $file, string $reason): self
    {
        return new self($file, true, [], [new ImportIssue($file, $reason)], []);
    }

    /**
     * @param  list<ResearchSource>  $sources
     * @param  list<ImportIssue>  $skipped
     * @param  list<ImportIssue>  $warnings
     */
    public static function parsed(string $file, array $sources, array $skipped, array $warnings, int $entries): self
    {
        return new self($file, true, $sources, $skipped, $warnings, $entries);
    }
}
