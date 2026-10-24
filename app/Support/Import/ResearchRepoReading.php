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
     * @param  list<ImportIssue>  $unrecognizedBlocks  the warnings of files without a recognized block, i.e. whose block does not start on the first line
     */
    public function __construct(
        public array $persons,
        public array $marriages,
        public array $skipped,
        public array $warnings,
        public array $filesWithoutBlock,
        public int $scannedFiles,
        public int $parentChildRelations,
        public array $unrecognizedBlocks = [],
    ) {}

    /**
     * The repo ids of entries that were skipped or lost their block to a misplaced start, so that their file is not a
     * removed one. Sorted.
     *
     * @return list<string>
     */
    public function skippedRepoIds(): array
    {
        $ids = [];

        foreach ([...$this->skipped, ...$this->unrecognizedBlocks] as $issue) {
            if ($issue->repoId !== null) {
                $ids[$issue->repoId] = true;
            }
        }

        $ids = array_map(strval(...), array_keys($ids));
        sort($ids);

        return $ids;
    }

    /**
     * Whether some skipped entry, or a file with a misplaced block, has no readable id: then it may be any person.
     */
    public function hasSkippedWithoutId(): bool
    {
        foreach ([...$this->skipped, ...$this->unrecognizedBlocks] as $issue) {
            if ($issue->repoId === null) {
                return true;
            }
        }

        return false;
    }
}
