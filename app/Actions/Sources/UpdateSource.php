<?php

namespace App\Actions\Sources;

use App\Enums\SourceKind;
use App\Models\Source;

class UpdateSource
{
    /**
     * Update the kind and description of a source. Only a metrical record carries
     * a record type and an entry number, so both are cleared for any other kind.
     * The file, its thumbnail and the event links are never touched.
     *
     * @param  array{name?: string|null, record_type?: string|null, entry_number?: string|null, year?: int|null, provenance?: string|null, url?: string|null, note?: string|null}  $description
     */
    public function handle(Source $source, SourceKind $kind, array $description): Source
    {
        $attributes = [
            'name' => null,
            'record_type' => null,
            'entry_number' => null,
            'year' => null,
            'provenance' => null,
            'url' => null,
            'note' => null,
            ...$description,
            'kind' => $kind,
        ];

        if ($kind !== SourceKind::MetricalRecord) {
            $attributes['record_type'] = null;
            $attributes['entry_number'] = null;
        }

        $source->update($attributes);

        return $source;
    }
}
