<?php

namespace App\Support\Import;

use App\Enums\Gender;
use App\Enums\SourceKind;
use App\Enums\SourceRecordType;

/**
 * A field that has different values in the tree and in the repo, left to the author.
 */
final readonly class ValueConflict
{
    /**
     * @param  string  $key  stable key of the question, e.g. "O-0042/urodzenie/data" or "O-0001+O-0004/slub/data"
     * @param  string  $person  who it is about, e.g. "Jan Nowak (O-0042)"
     * @param  string  $field  first_name, last_name, gender, note, surname_variants, birth_date, death_date, burial_date, marriage_date, birth_place, death_place, burial_place, marriage_place, birth_sources, death_sources or marriage_sources; of a source kind, record_type, name, year, parish, archive, signature, url, note or file
     * @param  string  $label  the Polish name of the field
     * @param  string  $appLabel  the value in the app, as shown to the author, "—" for an empty field
     * @param  string  $repoLabel  the value in the repo, as shown to the author, "—" when the repo cleared the field
     * @param  string|Gender|ResearchDate|PlannedPlace|SurnameVariants|SourceLinks|SourceKind|SourceRecordType|null  $repoValue  the repo value, what is written when the author takes it; `null` clears the field
     * @param  string|null  $lastRepoLabel  what the repo held at the last import, "—" for an empty field; `null` without a memory of the field
     */
    public function __construct(
        public string $key,
        public string $person,
        public string $field,
        public string $label,
        public string $appLabel,
        public string $repoLabel,
        public string|Gender|ResearchDate|PlannedPlace|SurnameVariants|SourceLinks|SourceKind|SourceRecordType|null $repoValue,
        public ?string $lastRepoLabel = null,
    ) {}
}
