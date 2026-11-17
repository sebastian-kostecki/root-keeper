<?php

namespace App\Support\Import;

final readonly class SourceWriteResult
{
    /**
     * @param  int  $created  new sources added with a copy of their scan; restored ones count among the restored records
     * @param  int  $merged  manual sources that became the source of the repo
     * @param  int  $changed  known sources that got a value of the description or a new scan
     * @param  int  $replaced  scans replaced by a new file of the repo
     */
    public function __construct(
        public int $created = 0,
        public int $merged = 0,
        public int $changed = 0,
        public int $replaced = 0,
    ) {}
}
