<?php

use App\Models\Event;
use App\Models\Source;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

function moveGravestoneDescriptionsMigration(): Migration
{
    static $migration = null;

    return $migration ??= require database_path('migrations/2026_10_22_100000_move_gravestone_descriptions_to_note.php');
}

test('a gravestone with a year and an archive moves them to the end of its note', function () {
    $user = User::factory()->create();
    $gravestone = Source::factory()->gravestone()->for($user->tree)->create([
        'year' => 1932,
        'archive' => 'AP Kraków',
        'note' => 'Nagrobek przy kościele.',
    ]);

    moveGravestoneDescriptionsMigration()->up();

    $gravestone->refresh();
    expect($gravestone->note)->toBe("Nagrobek przy kościele.\n\nRok: 1932\nArchiwum: AP Kraków");
    expect($gravestone->year)->toBeNull();
    expect($gravestone->archive)->toBeNull();
});

test('an empty note receives just the lines', function () {
    $user = User::factory()->create();
    $missing = Source::factory()->gravestone()->for($user->tree)->create(['year' => 1932, 'note' => null]);
    $blank = Source::factory()->gravestone()->for($user->tree)->create(['archive' => 'AP Kraków', 'note' => '']);

    moveGravestoneDescriptionsMigration()->up();

    expect($missing->refresh()->note)->toBe('Rok: 1932');
    expect($blank->refresh()->note)->toBe('Archiwum: AP Kraków');
});

test('a blank archive counts as no value', function () {
    $user = User::factory()->create();
    $gravestone = Source::factory()->gravestone()->for($user->tree)->create(['year' => 1932, 'archive' => '  ', 'note' => null]);

    moveGravestoneDescriptionsMigration()->up();

    $gravestone->refresh();
    expect($gravestone->note)->toBe('Rok: 1932');
    expect($gravestone->archive)->toBeNull();
});

test('every entry number of a gravestone becomes a line in link order and is cleared', function () {
    $user = User::factory()->create();
    $gravestone = Source::factory()->gravestone()->for($user->tree)->create(['note' => 'Stara notatka.']);
    $birth = Event::factory()->for($user->tree)->create();
    $death = Event::factory()->for($user->tree)->create();
    $burial = Event::factory()->for($user->tree)->create();
    $birth->sources()->attach($gravestone, ['entry_number' => '12']);
    $burial->sources()->attach($gravestone);
    $death->sources()->attach($gravestone, ['entry_number' => '47/3']);

    moveGravestoneDescriptionsMigration()->up();

    expect($gravestone->refresh()->note)->toBe("Stara notatka.\n\nNumer wpisu: 12\nNumer wpisu: 47/3");
    expect(DB::table('event_source')->where('source_id', $gravestone->id)->whereNotNull('entry_number')->count())->toBe(0);
    expect(DB::table('event_source')->where('source_id', $gravestone->id)->count())->toBe(3);
});

test('the year, the archive and the entry numbers come in that order', function () {
    $user = User::factory()->create();
    $gravestone = Source::factory()->gravestone()->for($user->tree)->create(['year' => 1932, 'archive' => 'AP Kraków', 'note' => null]);
    Event::factory()->for($user->tree)->create()->sources()->attach($gravestone, ['entry_number' => '5']);

    moveGravestoneDescriptionsMigration()->up();

    expect($gravestone->refresh()->note)->toBe("Rok: 1932\nArchiwum: AP Kraków\nNumer wpisu: 5");
});

test('a metrical record keeps its year, archive and entry numbers', function () {
    $user = User::factory()->create();
    $record = Source::factory()->described()->for($user->tree)->create();
    Event::factory()->for($user->tree)->create()->sources()->attach($record, ['entry_number' => '47']);

    moveGravestoneDescriptionsMigration()->up();

    $record->refresh();
    expect($record->year)->toBe(1878);
    expect($record->archive)->toBe('Archiwum Państwowe w Krakowie');
    expect($record->note)->toBe('Wpis po prawej stronie rozkładówki.');
    expect(DB::table('event_source')->where('source_id', $record->id)->value('entry_number'))->toBe('47');
});

test('a source of another kind keeps its year', function () {
    $user = User::factory()->create();
    $other = Source::factory()->other()->for($user->tree)->create(['year' => 1900, 'archive' => 'Prywatne', 'note' => 'Notatka.']);

    moveGravestoneDescriptionsMigration()->up();

    $other->refresh();
    expect($other->year)->toBe(1900);
    expect($other->archive)->toBe('Prywatne');
    expect($other->note)->toBe('Notatka.');
});

test('a gravestone with no year, archive or entry number keeps its note untouched', function () {
    $user = User::factory()->create();
    $gravestone = Source::factory()->gravestone()->for($user->tree)->create(['note' => 'Bez zmian.']);
    $untouchedAt = $gravestone->updated_at;
    Event::factory()->for($user->tree)->create()->sources()->attach($gravestone);

    moveGravestoneDescriptionsMigration()->up();

    $gravestone->refresh();
    expect($gravestone->note)->toBe('Bez zmian.');
    expect($gravestone->updated_at->equalTo($untouchedAt))->toBeTrue();
});

test('a gravestone in another tree is migrated the same way', function () {
    $mine = Source::factory()->gravestone()->for(User::factory()->create()->tree)->create(['year' => 1932]);
    $other = Source::factory()->gravestone()->for(User::factory()->create()->tree)->create(['archive' => 'AP Lublin']);

    moveGravestoneDescriptionsMigration()->up();

    expect($mine->refresh()->note)->toBe('Rok: 1932');
    expect($other->refresh()->note)->toBe('Archiwum: AP Lublin');
});

test('running the migration twice does not append another line', function () {
    $user = User::factory()->create();
    $gravestone = Source::factory()->gravestone()->for($user->tree)->create(['year' => 1932, 'note' => null]);

    moveGravestoneDescriptionsMigration()->up();
    moveGravestoneDescriptionsMigration()->up();

    expect($gravestone->refresh()->note)->toBe('Rok: 1932');
});
