<?php

namespace App\Support\Import;

use App\Enums\Gender;

/**
 * A value the repo changed since the last import in a field nobody touched in the app: the plan takes it unless the
 * author rejects it.
 */
final readonly class PlannedChange
{
    /**
     * @param  string  $key  stable key of the question, e.g. "O-0042/urodzenie/data" or "O-0042/notatka"
     * @param  string  $person  who it is about, e.g. "Jan Nowak (O-0042)"
     * @param  string  $field  first_name, last_name, gender, note, birth_date, death_date or marriage_date
     * @param  string  $label  the Polish name of the field
     * @param  string  $appLabel  the value in the app as shown to the author, "—" for an empty field
     * @param  string  $repoLabel  the value in the repo as shown to the author, "—" when the repo cleared the field
     * @param  string|Gender|ResearchDate|null  $repoValue  the repo value, what is written when the author takes it; `null` clears the field
     */
    public function __construct(
        public string $key,
        public string $person,
        public string $field,
        public string $label,
        public string $appLabel,
        public string $repoLabel,
        public string|Gender|ResearchDate|null $repoValue,
    ) {}
}
