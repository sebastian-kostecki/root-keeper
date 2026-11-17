<?php

namespace App\Support\Import;

/**
 * The files an import copied into the storage of the app during a write, so that a rolled back write can delete them.
 *
 * A failure after the body of the transaction finished (a commit whose outcome is unknown, a callback after the
 * commit) may leave rows that point at the files, so the files are deleted only when the body itself failed: an
 * orphaned file is better than a source without its scan.
 */
final class CopiedFiles
{
    /** @var list<string> */
    private array $paths = [];

    private bool $bodyFinished = false;

    public function add(?string ...$paths): void
    {
        foreach ($paths as $path) {
            if ($path !== null) {
                $this->paths[] = $path;
            }
        }
    }

    /**
     * @return list<string>
     */
    public function paths(): array
    {
        return $this->paths;
    }

    /**
     * Note that the body of the transaction wrote everything; what fails after that may already be committed.
     */
    public function bodyFinished(): void
    {
        $this->bodyFinished = true;
    }

    /**
     * Whether the write failed inside the body of the transaction, so that the transaction surely rolled back.
     */
    public function rolledBack(): bool
    {
        return ! $this->bodyFinished;
    }
}
