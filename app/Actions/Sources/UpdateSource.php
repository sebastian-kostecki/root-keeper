<?php

namespace App\Actions\Sources;

use App\Enums\SourceKind;
use App\Models\Source;
use Illuminate\Support\Facades\DB;

class UpdateSource
{
    public function __construct(private FillBurialPlacesFromGravestone $fillBurialPlacesFromGravestone) {}

    /**
     * Update the kind and description of a source. Only a metrical record carries
     * a record type and a parish, and only a gravestone a cemetery and a list of
     * buried people (replaced as a whole), so those are cleared for any other kind.
     * The file, its thumbnail and the event links are never touched. Saving a
     * gravestone fills the empty burial places of its deceased people.
     *
     * @param  array{name?: string|null, record_type?: string|null, parish_id?: int|null, cemetery_locality_id?: int|null, buried_person_ids?: list<int>|null, year?: int|null, archive?: string|null, url?: string|null, note?: string|null}  $description
     */
    public function handle(Source $source, SourceKind $kind, array $description): Source
    {
        $buriedPersonIds = $description['buried_person_ids'] ?? [];
        unset($description['buried_person_ids']);

        $attributes = [
            'name' => null,
            'record_type' => null,
            'parish_id' => null,
            'cemetery_locality_id' => null,
            'year' => null,
            'archive' => null,
            'url' => null,
            'note' => null,
            ...$description,
            'kind' => $kind,
        ];

        if ($kind !== SourceKind::MetricalRecord) {
            $attributes['record_type'] = null;
            $attributes['parish_id'] = null;
        }

        if ($kind !== SourceKind::Gravestone) {
            $attributes['cemetery_locality_id'] = null;
            $buriedPersonIds = [];
        }

        return DB::transaction(function () use ($source, $attributes, $buriedPersonIds): Source {
            $source->update($attributes);
            $source->buriedPeople()->sync($buriedPersonIds);

            $this->fillBurialPlacesFromGravestone->handle($source);

            return $source;
        });
    }
}
