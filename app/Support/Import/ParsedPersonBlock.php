<?php

namespace App\Support\Import;

final readonly class ParsedPersonBlock
{
    /**
     * @param  list<ImportIssue>  $warnings
     */
    private function __construct(
        public string $file,
        public ?ResearchPerson $person,
        public ?string $skipReason,
        public array $warnings,
        public ?string $repoId = null,
    ) {}

    /**
     * @param  list<ImportIssue>  $warnings
     */
    public static function withoutBlock(string $file, array $warnings = []): self
    {
        return new self($file, null, null, $warnings);
    }

    /**
     * @param  string|null  $repoId  the id of the skipped entry, when the block names a valid one
     */
    public static function skipped(string $file, string $reason, ?string $repoId = null): self
    {
        return new self($file, null, $reason, [], $repoId);
    }

    /**
     * @param  list<ImportIssue>  $warnings
     */
    public static function person(ResearchPerson $person, array $warnings = []): self
    {
        return new self($person->file, $person, null, $warnings);
    }

    public function hasBlock(): bool
    {
        return $this->person !== null || $this->skipReason !== null;
    }
}
