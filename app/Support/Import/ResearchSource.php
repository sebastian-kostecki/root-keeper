<?php

namespace App\Support\Import;

use App\Enums\SourceKind;
use App\Enums\SourceRecordType;

final readonly class ResearchSource
{
    /**
     * @param  string  $id  repo id, `ZR-NNNN`
     * @param  string  $file  the file with the block, relative to the repo root
     * @param  string  $scanPath  the scan as the block writes it, relative to the repo root
     * @param  string  $scanAbsolutePath  the real path of the scan, inside the repo root
     * @param  string  $scanHash  sha256 of the scan
     */
    public function __construct(
        public string $id,
        public string $file,
        public string $scanPath,
        public string $scanAbsolutePath,
        public string $scanHash,
        public SourceKind $kind,
        public ?SourceRecordType $recordType = null,
        public ?ResearchParish $parish = null,
        public ?string $name = null,
        public ?int $year = null,
        public ?string $archive = null,
        public ?string $signature = null,
        public ?string $url = null,
        public ?string $note = null,
    ) {}
}
