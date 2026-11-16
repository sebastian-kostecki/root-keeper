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
     * @param  list<string>  $droppedMarriageKeys  repo keys of weddings between two valid persons that the reading dropped with a warning (the files disagree or the pair is written twice in one file); they are still in the repo, only unreadable
     * @param  array<string, ResearchSource>  $sources  the sources with a scan, keyed by repo id
     * @param  list<ImportIssue>  $skippedSources  scans (or whole files under zrodla/) left out, with the file and the reason; the file is relative to the repo root
     * @param  int  $sourceFilesScanned  the .md files under zrodla/
     * @param  list<string>  $sourceFilesWithoutBlock
     * @param  int  $sourceScanEntries  the scan entries the blocks list, read or skipped
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
        public array $droppedMarriageKeys = [],
        public array $sources = [],
        public array $skippedSources = [],
        public int $sourceFilesScanned = 0,
        public array $sourceFilesWithoutBlock = [],
        public int $sourceScanEntries = 0,
    ) {}

    /**
     * The repo ids of source entries that were skipped, so that their source is not a removed one. Sorted.
     *
     * @return list<string>
     */
    public function skippedSourceIds(): array
    {
        $ids = [];

        foreach ($this->skippedSources as $issue) {
            if ($issue->repoId !== null) {
                $ids[$issue->repoId] = true;
            }
        }

        $ids = array_map(strval(...), array_keys($ids));
        sort($ids);

        return $ids;
    }

    /**
     * Whether some skipped source entry, or a whole file under zrodla/, has no readable id: then it may be any source.
     */
    public function hasSkippedSourceWithoutId(): bool
    {
        foreach ($this->skippedSources as $issue) {
            if ($issue->repoId === null) {
                return true;
            }
        }

        return false;
    }

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
