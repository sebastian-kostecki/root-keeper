<?php

use App\Models\Cemetery;
use App\Models\Event;
use App\Models\Locality;
use App\Models\Person;
use App\Models\Residence;
use App\Models\Source;
use App\Models\Tree;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    // The schema is fully migrated: step back to the shape before this migration (the test transaction undoes it).
    restoreLegacyColumns();
});

/**
 * @return array<string, bool>
 */
function legacyColumnsPresence(): array
{
    $presence = [];

    foreach (['people.residence', 'people.maiden_name', 'events.place', 'sources.entry_number', 'sources.provenance', 'sources.cemetery_locality_id'] as $column) {
        [$table, $name] = explode('.', $column);
        $presence[$column] = Schema::hasColumn($table, $name);
    }

    return $presence;
}

/**
 * Store one value of the given legacy column that has no copy in its new place.
 */
function arrangeUncopiedValue(string $column): void
{
    $tree = Tree::factory()->create();

    match ($column) {
        'people.residence' => DB::table('people')->where('id', Person::factory()->for($tree)->create()->id)
            ->update(['residence' => 'Kraków']),
        'people.maiden_name' => DB::table('people')->where('id', Person::factory()->for($tree)->create(['last_name' => 'Nowak'])->id)
            ->update(['maiden_name' => 'Kowalska']),
        'events.place' => DB::table('events')->where('id', Event::factory()->for($tree)->create()->id)
            ->update(['place' => 'Kraków']),
        'sources.entry_number' => (function () use ($tree): void {
            $source = Source::factory()->for($tree)->create();
            Event::factory()->for($tree)->create()->sources()->attach($source, ['entry_number' => '12']);
            DB::table('sources')->where('id', $source->id)->update(['entry_number' => '38']);
        })(),
        'sources.provenance' => DB::table('sources')->where('id', Source::factory()->for($tree)->gravestone()->create(['note' => 'Nagrobek.'])->id)
            ->update(['provenance' => 'AP Kraków']),
        'sources.cemetery_locality_id' => DB::table('sources')->where('id', Source::factory()->for($tree)->gravestone()->create()->id)
            ->update(['cemetery_locality_id' => Locality::factory()->for($tree)->create()->id]),
    };
}

test('a value without a copy stops the migration, names its column and drops nothing', function (string $column) {
    arrangeUncopiedValue($column);

    $message = null;

    try {
        dropLegacyColumnsMigration()->up();
    } catch (RuntimeException $exception) {
        $message = $exception->getMessage();
    }

    expect($message)->toBe("Nie usuwam starych kolumn: {$column} (bez kopii: 1). Przenieś te wartości w nowe miejsce albo odtwórz bazę z kopii produkcji i uruchom migrate ponownie.");
    expect(legacyColumnsPresence())->each->toBeTrue();
})->with([
    'people.residence',
    'people.maiden_name',
    'events.place',
    'sources.entry_number',
    'sources.provenance',
    'sources.cemetery_locality_id',
]);

test('the message lists every column with values without a copy', function () {
    arrangeUncopiedValue('events.place');
    arrangeUncopiedValue('events.place');
    arrangeUncopiedValue('sources.provenance');

    expect(fn () => dropLegacyColumnsMigration()->up())
        ->toThrow(RuntimeException::class, 'events.place (bez kopii: 2); sources.provenance (bez kopii: 1).');
});

test('values with a copy and empty values let the migration drop all six columns', function () {
    $tree = Tree::factory()->create();

    $resident = Person::factory()->for($tree)->create();
    Residence::factory()->for($resident)->create();
    $married = Person::factory()->for($tree)->create(['last_name' => 'Kowalska']);
    $blank = Person::factory()->for($tree)->create();
    DB::table('people')->where('id', $resident->id)->update(['residence' => 'Kraków']);
    DB::table('people')->where('id', $married->id)->update(['maiden_name' => 'KOWALSKA']);
    DB::table('people')->where('id', $blank->id)->update(['residence' => '  ', 'maiden_name' => '']);

    $placed = Event::factory()->for($tree)->withLocality()->create();
    $unplaced = Event::factory()->for($tree)->create();
    DB::table('events')->where('id', $placed->id)->update(['place' => 'Kraków']);
    DB::table('events')->where('id', $unplaced->id)->update(['place' => ' ']);

    $record = Source::factory()->for($tree)->create(['archive' => 'AP Kraków']);
    Event::factory()->for($tree)->create()->sources()->attach($record, ['entry_number' => '38']);
    $gravestone = Source::factory()->for($tree)->gravestone()->create([
        'cemetery_id' => Cemetery::factory()->for($tree)->create()->id,
        'note' => "Nagrobek.\n\nArchiwum: AP Tarnów",
    ]);
    DB::table('sources')->where('id', $record->id)->update(['entry_number' => '38', 'provenance' => 'AP Kraków, sygn. 12']);
    DB::table('sources')->where('id', $gravestone->id)->update([
        'provenance' => ' AP Tarnów ',
        'cemetery_locality_id' => Locality::factory()->for($tree)->create()->id,
    ]);

    dropLegacyColumnsMigration()->up();

    expect(legacyColumnsPresence())->each->toBeFalse();
});
