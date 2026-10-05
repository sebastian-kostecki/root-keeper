<?php

namespace App\Actions\Sources;

use App\Enums\SourceKind;
use App\Models\Source;

class UpdateSource
{
    /**
     * Update the kind and description of a source. Only a metrical record carries
     * a record type, an entry number and a parish, so all three are cleared for any other kind.
     * The file, its thumbnail and the event links are never touched.
     *
     * @param  array{name?: string|null, record_type?: string|null, entry_number?: string|null, parish_id?: int|null, year?: int|null, archive?: string|null, url?: string|null, note?: string|null}  $description
     */
    public function handle(Source $source, SourceKind $kind, array $description): Source
    {
        $attributes = [
            'name' => null,
            'record_type' => null,
            'entry_number' => null,
            'parish_id' => null,
            'year' => null,
            'archive' => null,
            'url' => null,
            'note' => null,
            ...$description,
            'kind' => $kind,
        ];

        if ($kind !== SourceKind::MetricalRecord) {
            $attributes['record_type'] = null;
            $attributes['entry_number'] = null;
            $attributes['parish_id'] = null;
        }

        $source->update($attributes);

        return $source;
    }
}
