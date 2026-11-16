<?php

namespace App\Support\Import;

/**
 * The files an import copied into the storage of the app during a write, so that a rolled back write can delete them.
 */
final class CopiedFiles
{
    /** @var list<string> */
    private array $paths = [];

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
}
