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
     * A gravestone has no year, archive and signature either. Changing another kind into a
     * gravestone moves the stored year, archive, signature and the entry numbers of its event links
     * to the end of the submitted note and clears them, so no value is lost.
     * The file, its thumbnail and the event links are never touched. Saving a
     * gravestone fills the empty burial places of its deceased people.
     *
     * @param  array{name?: string|null, record_type?: string|null, parish_id?: int|null, cemetery_id?: int|null, buried_person_ids?: list<int>|null, year?: int|null, archive?: string|null, signature?: string|null, url?: string|null, note?: string|null}  $description
     */
    public function handle(Source $source, SourceKind $kind, array $description): Source
    {
        $buriedPersonIds = $description['buried_person_ids'] ?? [];
        unset($description['buried_person_ids']);

        $attributes = [
            'name' => null,
            'record_type' => null,
            'parish_id' => null,
            'cemetery_id' => null,
            'year' => null,
            'archive' => null,
            'signature' => null,
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
            $attributes['cemetery_id'] = null;
            $buriedPersonIds = [];
        }

        $becomesGravestone = $kind === SourceKind::Gravestone && $source->kind !== SourceKind::Gravestone;

        if ($kind === SourceKind::Gravestone) {
            $attributes['year'] = null;
            $attributes['archive'] = null;
            $attributes['signature'] = null;
        }

        return DB::transaction(function () use ($source, $attributes, $buriedPersonIds, $becomesGravestone): Source {
            if ($becomesGravestone) {
                $attributes['note'] = $this->noteWithMovedValues($source, $attributes['note']);
                DB::table('event_source')->where('source_id', $source->id)->update(['entry_number' => null]);
            }

            $source->update($attributes);
            $source->buriedPeople()->sync($buriedPersonIds);

            $this->fillBurialPlacesFromGravestone->handle($source);

            return $source;
        });
    }

    /**
     * Append the stored year, archive, signature and entry numbers (in link order) to the note as lines.
     */
    private function noteWithMovedValues(Source $source, ?string $note): ?string
    {
        $lines = [];

        if ($source->year !== null) {
            $lines[] = "Rok: {$source->year}";
        }

        if (filled($source->archive) && trim($source->archive) !== '') {
            $lines[] = 'Archiwum: '.trim($source->archive);
        }

        if (filled($source->signature) && trim($source->signature) !== '') {
            $lines[] = 'Sygnatura: '.trim($source->signature);
        }

        $entryNumbers = DB::table('event_source')->where('source_id', $source->id)->orderBy('id')->lockForUpdate()->pluck('entry_number');

        foreach ($entryNumbers as $entryNumber) {
            if (filled($entryNumber) && trim((string) $entryNumber) !== '') {
                $lines[] = 'Numer wpisu: '.trim((string) $entryNumber);
            }
        }

        if ($lines === []) {
            return $note;
        }

        $block = implode("\n", $lines);

        return filled($note) ? $note."\n\n".$block : $block;
    }
}
