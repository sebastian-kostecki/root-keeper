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
    ) {}

    /**
     * @param  list<ImportIssue>  $warnings
     */
    public static function withoutBlock(string $file, array $warnings = []): self
    {
        return new self($file, null, null, $warnings);
    }

    public static function skipped(string $file, string $reason): self
    {
        return new self($file, null, $reason, []);
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
