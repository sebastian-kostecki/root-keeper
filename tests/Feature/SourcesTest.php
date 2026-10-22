<?php

use App\Enums\EventType;
use App\Enums\ParticipantRole;
use App\Enums\Religion;
use App\Enums\SourceKind;
use App\Enums\SourceRecordType;
use App\Models\Cemetery;
use App\Models\Event;
use App\Models\Locality;
use App\Models\Parish;
use App\Models\Person;
use App\Models\Source;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

test('guests are redirected to the login page from sources routes', function () {
    $source = Source::factory()->create();

    $this->get(route('sources.index'))->assertRedirect(route('login'));
    $this->get(route('sources.show', $source))->assertRedirect(route('login'));
    $this->post(route('sources.store'), [])->assertRedirect(route('login'));
    $this->get(route('sources.file', $source))->assertRedirect(route('login'));
    $this->get(route('sources.thumbnail', $source))->assertRedirect(route('login'));
    $this->delete(route('sources.destroy', $source))->assertRedirect(route('login'));
    $this->put(route('sources.update', $source), [])->assertRedirect(route('login'));
});

test('the private disk does not expose a signed-url storage route', function () {
    expect(Route::has('storage.local'))->toBeFalse();
});

test('sources index shows only the authenticated user own sources, newest first', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $older = Source::factory()->for($user->tree)->create();
    $newer = Source::factory()->for($user->tree)->create();
    Source::factory()->for($otherUser->tree)->create();

    $response = $this->actingAs($user)->get(route('sources.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('sources/index')
        ->has('sources', 2)
        ->has('sources.0', fn ($source) => $source->hasAll([
            'id', 'kind', 'kind_label', 'kind_short_label', 'name', 'display_name', 'record_type', 'record_type_label', 'legacy_entry_number', 'year', 'parish_id', 'parish_label', 'parish_locality_id', 'locality_name', 'cemetery_id', 'cemetery_label', 'cemetery_locality_id', 'archive', 'url', 'note', 'original_name', 'mime_type', 'size', 'width', 'height', 'has_thumbnail', 'uploaded_at', 'events_count',
        ]))
        ->where('sources.0.id', $newer->id)
        ->where('sources.1.id', $older->id));
});

test('the old upload page returns not found', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/sources/create')->assertNotFound();
});

test('the sources index renders the source panel for ?source=id', function () {
    $user = User::factory()->create();
    $source = Source::factory()->for($user->tree)->withThumbnail()->create();

    $response = $this->actingAs($user)->get(route('sources.index', ['source' => $source->id]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('sources/index')
        ->where('sourcePanel.source.id', $source->id)
        ->where('sourcePanel.source.has_thumbnail', true));
});

test('the source panel is null without a source key or with a non-numeric one', function (array $query) {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('sources.index', $query))->assertInertia(fn ($page) => $page
        ->component('sources/index')
        ->where('sourcePanel', null));
})->with([
    'no key' => [[]],
    'letters' => [['source' => 'abc']],
    'negative' => [['source' => '-1']],
    'array' => [['source' => ['5']]],
]);

test('?source=new opens an empty panel for a new source', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('sources.index', ['source' => 'new']))->assertInertia(fn ($page) => $page
        ->component('sources/index')
        ->where('sourcePanel.source', null)
        ->where('sourcePanel.confirms', []));
});

test('?source= of a missing source is a 404', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('sources.index', ['source' => 999999]))->assertNotFound();
});

test('sources show redirects to the panel on the list', function () {
    $user = User::factory()->create();
    $source = Source::factory()->for($user->tree)->create();

    $this->actingAs($user)->get(route('sources.show', $source))
        ->assertRedirect(route('sources.index', ['source' => $source->id]));
});

test('source panel confirms is empty for an unlinked source', function () {
    $user = User::factory()->create();
    $source = Source::factory()->for($user->tree)->create();

    $response = $this->actingAs($user)->get(route('sources.index', ['source' => $source->id]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('sources/index')
        ->where('sourcePanel.confirms', []));
});

test('source panel confirms lists the linked events with the person names and whether this is the last source', function () {
    $user = User::factory()->create();
    $source = Source::factory()->for($user->tree)->create();
    $otherSource = Source::factory()->for($user->tree)->create();
    $person = Person::factory()->for($user->tree)->create(['first_name' => 'Jan', 'last_name' => 'Kowalski']);
    $birth = Event::factory()->for($user->tree)->birth()->create();
    $birth->participants()->attach($person, ['role' => ParticipantRole::Subject]);
    $source->events()->attach($birth, ['entry_number' => '95']);

    $death = Event::factory()->for($user->tree)->death()->create();
    $death->participants()->attach($person, ['role' => ParticipantRole::Subject]);
    $death->sources()->attach([$source->id, $otherSource->id]);

    $response = $this->actingAs($user)->get(route('sources.index', ['source' => $source->id]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('sources/index')
        ->has('sourcePanel.confirms', 2)
        ->where('sourcePanel.confirms.0.event_id', $birth->id)
        ->where('sourcePanel.confirms.0.event_type', 'birth')
        ->where('sourcePanel.confirms.0.event_label', 'Urodzenie')
        ->where('sourcePanel.confirms.0.is_last_source', true)
        ->where('sourcePanel.confirms.0.entry_number', '95')
        ->where('sourcePanel.confirms.0.person.id', $person->id)
        ->where('sourcePanel.confirms.0.person.display_name', 'Jan Kowalski')
        ->where('sourcePanel.confirms.1.event_id', $death->id)
        ->where('sourcePanel.confirms.1.is_last_source', false)
        ->where('sourcePanel.confirms.1.entry_number', null));
});

test('storing a jpg creates the source, the file and its thumbnail', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('rodzinne-zdjecie.jpg', 1200, 800);

    $response = $this->actingAs($user)->post(route('sources.store'), [
        'kind' => 'metrical_record',
        'file' => $file,
    ]);

    $source = Source::firstOrFail();

    $response->assertRedirect(route('sources.index', ['source' => $source->id]));

    expect($source->tree_id)->toBe($user->tree->id);
    expect($source->original_name)->toBe('rodzinne-zdjecie.jpg');
    expect($source->mime_type)->toBe('image/jpeg');
    expect($source->size)->toBeGreaterThan(0);
    expect($source->width)->toBe(1200);
    expect($source->height)->toBe(800);

    expect($source->path)->toMatch("#^sources/{$user->tree->id}/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\.jpg$#");
    expect($source->path)->not->toContain('rodzinne-zdjecie');
    Storage::disk('local')->assertExists($source->path);

    expect($source->thumbnail_path)->not->toBeNull();
    Storage::disk('local')->assertExists($source->thumbnail_path);
});

test('storing without a kind is rejected with a polish error', function () {
    Storage::fake('local');

    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('sources.store'), [
        'file' => UploadedFile::fake()->image('akt.jpg'),
    ]);

    $response->assertSessionHasErrors('kind');
    expect($response->getSession()->get('errors')->getBag('default')->first('kind'))->toContain('wymagane');
    expect(Source::count())->toBe(0);
});

test('storing an unknown kind is rejected', function () {
    Storage::fake('local');

    $this->actingAs(User::factory()->create())->post(route('sources.store'), [
        'kind' => 'scan',
        'file' => UploadedFile::fake()->image('akt.jpg'),
    ])->assertSessionHasErrors('kind');
});

test('storing a gravestone persists its kind', function () {
    Storage::fake('local');

    $user = User::factory()->create();

    $this->actingAs($user)->post(route('sources.store'), [
        'kind' => 'gravestone',
        'file' => UploadedFile::fake()->image('nagrobek.jpg'),
    ]);

    expect(Source::firstOrFail()->kind)->toBe(SourceKind::Gravestone);
});

test('the kind is exposed on the source panel', function () {
    $user = User::factory()->create();
    $source = Source::factory()->gravestone()->for($user->tree)->create();

    $this->actingAs($user)->get(route('sources.index', ['source' => $source->id]))->assertInertia(fn ($page) => $page
        ->where('sourcePanel.source.kind', 'gravestone')
        ->where('sourcePanel.source.kind_label', 'zdjęcie nagrobka'));
});

test('the old scans routes no longer exist', function () {
    $this->actingAs(User::factory()->create())->get('/scans')->assertNotFound();
});

test('storing a png is accepted', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('zdjecie.png', 400, 300);

    $response = $this->actingAs($user)->post(route('sources.store'), [
        'kind' => 'metrical_record',
        'file' => $file,
    ]);

    $source = Source::sole();

    $response->assertSessionHasNoErrors()->assertRedirect(route('sources.index', ['source' => $source->id]));
    expect($source->mime_type)->toBe('image/png');
    expect($source->path)->toStartWith("sources/{$user->tree->id}/")->toEndWith('.png');
    Storage::disk('local')->assertExists($source->path);
    Storage::disk('local')->assertExists($source->thumbnail_path);
});

test('storing a webp is accepted', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('zdjecie.webp', 400, 300);

    $response = $this->actingAs($user)->post(route('sources.store'), [
        'kind' => 'metrical_record',
        'file' => $file,
    ]);

    $source = Source::sole();

    $response->assertSessionHasNoErrors()->assertRedirect(route('sources.index', ['source' => $source->id]));
    expect($source->mime_type)->toBe('image/webp');
    expect($source->path)->toStartWith("sources/{$user->tree->id}/")->toEndWith('.webp');
    Storage::disk('local')->assertExists($source->path);
    Storage::disk('local')->assertExists($source->thumbnail_path);
});

test('storing leaves no source row and no orphan original when the thumbnail cannot be written', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $blockedThumbnailsDirectory = "sources/{$user->tree->id}/thumbnails";
    Storage::disk('local')->put($blockedThumbnailsDirectory, 'a file where the directory belongs');

    $response = $this->actingAs($user)->post(route('sources.store'), [
        'kind' => 'metrical_record',
        'file' => UploadedFile::fake()->image('akt.jpg', 400, 300),
    ]);

    $response->assertInternalServerError();
    expect(Source::count())->toBe(0);
    expect(Storage::disk('local')->allFiles("sources/{$user->tree->id}"))->toBe([$blockedThumbnailsDirectory]);
});

test('storing without a file is rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('sources.store'), ['kind' => 'metrical_record'])
        ->assertSessionHasErrors('file');
});

test('storing a file over 20mb is rejected', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->create('akt.jpg', 20481, 'image/jpeg');

    $this->actingAs($user)->post(route('sources.store'), [
        'kind' => 'metrical_record',
        'file' => $file,
    ])->assertSessionHasErrors('file');
});

test('storing a pdf is rejected', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->create('akt.pdf', 10, 'application/pdf');

    $this->actingAs($user)->post(route('sources.store'), [
        'kind' => 'metrical_record',
        'file' => $file,
    ])->assertSessionHasErrors('file');
});

test('storing a gif is rejected', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->create('akt.gif', 10, 'image/gif');

    $this->actingAs($user)->post(route('sources.store'), [
        'kind' => 'metrical_record',
        'file' => $file,
    ])->assertSessionHasErrors('file');
});

test('storing an svg is rejected', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->create('akt.svg', 10, 'image/svg+xml');

    $this->actingAs($user)->post(route('sources.store'), [
        'kind' => 'metrical_record',
        'file' => $file,
    ])->assertSessionHasErrors('file');
});

test('storing a text file renamed to jpg is rejected', function () {
    $user = User::factory()->create();

    $tmpPath = tempnam(sys_get_temp_dir(), 'source');
    file_put_contents($tmpPath, 'this is plain text, not an image');
    $file = new UploadedFile($tmpPath, 'akt.jpg', null, null, true);

    $this->actingAs($user)->post(route('sources.store'), [
        'kind' => 'metrical_record',
        'file' => $file,
    ])->assertSessionHasErrors('file');
});

test('source validation errors are in polish', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('sources.store'), []);

    $response->assertInvalid();
    $errors = $response->getSession()->get('errors')->getBag('default');

    expect($errors->first('file'))->toContain('wymagane');
});

test('source file serves the original bytes with the right headers', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $source = Source::factory()->for($user->tree)->create([
        'path' => "sources/{$user->tree->id}/original.jpg",
        'mime_type' => 'image/jpeg',
    ]);
    Storage::disk('local')->put($source->path, 'original-bytes');

    $response = $this->actingAs($user)->get(route('sources.file', $source));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'image/jpeg');
    expect($response->headers->get('Cache-Control'))->toContain('private');
    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    expect($response->streamedContent())->toBe('original-bytes');
});

test('source file builds a safe download name from the original name', function (string $originalName, string $expectedFallback) {
    Storage::fake('local');

    $user = User::factory()->create();
    $source = Source::factory()->for($user->tree)->create([
        'original_name' => $originalName,
        'path' => "sources/{$user->tree->id}/original.jpg",
    ]);
    Storage::disk('local')->put($source->path, 'original-bytes');

    $response = $this->actingAs($user)->get(route('sources.file', $source));

    $response->assertOk();
    expect($response->headers->get('Content-Disposition'))
        ->toContain('filename='.str_replace('{id}', (string) $source->id, $expectedFallback));
})->with([
    'polish letters' => ['ąę.jpg', 'ae.jpg'],
    'percent only' => ['%', 'zrodlo-{id}.jpg'],
    'emoji and percent' => ['😀%', 'zrodlo-{id}.jpg'],
    'emoji only' => ['😀', 'zrodlo-{id}.jpg'],
]);

test('source thumbnail returns not found when the source has no thumbnail', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $source = Source::factory()->for($user->tree)->create(['thumbnail_path' => null]);

    $this->actingAs($user)->get(route('sources.thumbnail', $source))->assertNotFound();
});

test('deleting the account removes the tree sources from disk', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $tree = $user->tree;
    Storage::disk('local')->put("sources/{$tree->id}/keepsake.jpg", 'content');

    $this->actingAs($user)->delete(route('profile.destroy'), [
        'password' => 'password',
    ]);

    Storage::disk('local')->assertDirectoryEmpty("sources/{$tree->id}");
});

test('deleting an account without a tree leaves other trees sources intact', function () {
    Storage::fake('local');

    $otherUser = User::factory()->create();
    Storage::disk('local')->put("sources/{$otherUser->tree->id}/keepsake.jpg", 'content');

    $user = User::factory()->withoutTree()->create();

    $this->actingAs($user)->delete(route('profile.destroy'), [
        'password' => 'password',
    ])->assertSessionHasNoErrors()->assertRedirect(route('home'));

    $this->assertGuest();
    expect($user->fresh())->toBeNull();
    Storage::disk('local')->assertExists("sources/{$otherUser->tree->id}/keepsake.jpg");
});

test('destroy deletes the source row, its event_source rows and both files, keeps events and persons, and redirects with a toast', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $source = Source::factory()->for($user->tree)->withThumbnail()->create();
    Storage::disk('local')->put($source->path, 'original-bytes');
    Storage::disk('local')->put($source->thumbnail_path, 'thumbnail-bytes');
    $person = Person::factory()->for($user->tree)->create();
    $event = Event::factory()->for($user->tree)->birth()->create();
    $event->participants()->attach($person, ['role' => ParticipantRole::Subject]);
    $event->sources()->attach($source);

    $response = $this->actingAs($user)->delete(route('sources.destroy', $source));

    $response->assertRedirect(route('sources.index'));
    $response->assertSessionHas('inertia.flash_data.toast', ['type' => 'success', 'message' => 'Usunięto źródło']);

    expect(Source::find($source->id))->toBeNull();
    expect(DB::table('event_source')->where('source_id', $source->id)->exists())->toBeFalse();
    expect(Event::find($event->id))->not->toBeNull();
    expect(Person::find($person->id))->not->toBeNull();
    Storage::disk('local')->assertMissing($source->path);
    Storage::disk('local')->assertMissing($source->thumbnail_path);
});

test('destroy unconfirms an event that had only this source and keeps another event confirmed via its other source', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $source = Source::factory()->for($user->tree)->create();
    $otherSource = Source::factory()->for($user->tree)->create();
    $onlyThisSource = Event::factory()->for($user->tree)->birth()->create();
    $onlyThisSource->sources()->attach($source);
    $alsoOtherSource = Event::factory()->for($user->tree)->death()->create();
    $alsoOtherSource->sources()->attach([$source->id, $otherSource->id]);

    $this->actingAs($user)->delete(route('sources.destroy', $source))->assertRedirect(route('sources.index'));

    expect($onlyThisSource->fresh()->sources()->count())->toBe(0);
    expect($alsoOtherSource->fresh()->sources()->count())->toBe(1);
});

test('destroy succeeds for a source without a thumbnail', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $source = Source::factory()->for($user->tree)->create(['thumbnail_path' => null]);
    Storage::disk('local')->put($source->path, 'original-bytes');

    $this->actingAs($user)->delete(route('sources.destroy', $source))->assertRedirect(route('sources.index'));

    expect(Source::find($source->id))->toBeNull();
    Storage::disk('local')->assertMissing($source->path);
});

test('destroy succeeds when the file is already missing on disk', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $source = Source::factory()->for($user->tree)->withThumbnail()->create();

    $this->actingAs($user)->delete(route('sources.destroy', $source))->assertRedirect(route('sources.index'));

    expect(Source::find($source->id))->toBeNull();
});

test('destroy still removes the source and reports the failure when a file cannot be deleted', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $source = Source::factory()->for($user->tree)->create();
    Storage::disk('local')->makeDirectory($source->path);
    Exceptions::fake();

    $this->actingAs($user)->delete(route('sources.destroy', $source))->assertRedirect(route('sources.index'));

    expect(Source::find($source->id))->toBeNull();
    Exceptions::assertReported(fn (RuntimeException $e): bool => str_contains($e->getMessage(), $source->path));
});

test('storing with only a file and a kind leaves the description empty', function () {
    Storage::fake('local');

    $this->actingAs(User::factory()->create())->post(route('sources.store'), [
        'kind' => 'metrical_record',
        'file' => UploadedFile::fake()->image('akt.jpg'),
    ])->assertSessionHasNoErrors();

    $source = Source::firstOrFail();

    expect($source->only(['name', 'record_type', 'entry_number', 'parish_id', 'year', 'archive', 'url', 'note']))
        ->each->toBeNull();
});

test('storing with a full description persists every field and ignores a submitted entry number', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $parish = Parish::factory()->for($user->tree)->create();

    $this->actingAs($user)->post(route('sources.store'), [
        'kind' => 'metrical_record',
        'file' => UploadedFile::fake()->image('akt.jpg'),
        'name' => 'Urodzenia Lipnica 1878',
        'record_type' => 'birth',
        'entry_number' => '47a',
        'year' => '1878',
        'parish_id' => $parish->id,
        'archive' => 'Archiwum w Krakowie',
        'url' => 'https://example.com/akt',
        'note' => "Pierwszy akapit.\n\nDrugi akapit.",
    ])->assertSessionHasNoErrors();

    $source = Source::firstOrFail();

    expect($source->name)->toBe('Urodzenia Lipnica 1878');
    expect($source->record_type)->toBe(SourceRecordType::Birth);
    expect($source->entry_number)->toBeNull();
    expect($source->year)->toBe(1878);
    expect($source->parish_id)->toBe($parish->id);
    expect($source->archive)->toBe('Archiwum w Krakowie');
    expect($source->provenance)->toBeNull();
    expect($source->url)->toBe('https://example.com/akt');
    expect($source->note)->toBe("Pierwszy akapit.\n\nDrugi akapit.");
});

test('storing a gravestone ignores a submitted record type', function () {
    Storage::fake('local');

    $this->actingAs(User::factory()->create())->post(route('sources.store'), [
        'kind' => 'gravestone',
        'file' => UploadedFile::fake()->image('nagrobek.jpg'),
        'name' => 'Nagrobek',
        'record_type' => 'birth',
    ])->assertSessionHasNoErrors();

    $source = Source::firstOrFail();

    expect($source->name)->toBe('Nagrobek');
    expect($source->record_type)->toBeNull();
    expect($source->entry_number)->toBeNull();
});

test('store rejects an invalid description with polish messages', function (array $input, string $field, string $expected) {
    Storage::fake('local');

    $response = $this->actingAs(User::factory()->create())->post(route('sources.store'), [
        'kind' => 'metrical_record',
        'file' => UploadedFile::fake()->image('akt.jpg'),
        ...$input,
    ]);

    $response->assertInvalid($field);
    expect($response->getSession()->get('errors')->getBag('default')->first($field))->toContain($expected);
    expect(Source::count())->toBe(0);
})->with([
    'year too early' => [['year' => '999'], 'year', 'rok'],
    'year in the future' => [['year' => '9999'], 'year', 'rok'],
    'javascript link' => [['url' => 'javascript:alert(1)'], 'url', 'link'],
    'ftp link' => [['url' => 'ftp://example.com/akt'], 'url', 'link'],
    'unknown record type' => [['record_type' => 'baptism'], 'record_type', 'typ aktu'],
    'long name' => [['name' => str_repeat('a', 256)], 'name', 'nazwa'],
    'long archive' => [['archive' => str_repeat('a', 256)], 'archive', 'archiwum'],
    'long note' => [['note' => str_repeat('a', 5001)], 'note', 'notatka'],
]);

test('updating changes the kind and the description, ignores a submitted entry number and redirects back with the toast', function () {
    $user = User::factory()->create();
    $source = Source::factory()->for($user->tree)->create(['entry_number' => '47']);

    $this->actingAs($user)->from(route('sources.index', ['source' => $source->id]))->put(route('sources.update', $source), [
        'kind' => 'metrical_record',
        'name' => 'Nowa nazwa',
        'record_type' => 'death',
        'entry_number' => '12/1878',
        'year' => '1878',
        'archive' => 'Archiwum',
        'url' => 'https://example.com',
        'note' => 'Notatka',
    ])
        ->assertRedirect(route('sources.index', ['source' => $source->id]))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Zapisano źródło']);

    $source->refresh();

    expect($source->name)->toBe('Nowa nazwa');
    expect($source->record_type)->toBe(SourceRecordType::Death);
    expect($source->entry_number)->toBe('47');
    expect($source->year)->toBe(1878);
    expect($source->note)->toBe('Notatka');
});

test('changing an akt with a stored record type or parish to another kind without confirmation is rejected', function (string $stored) {
    $user = User::factory()->create();
    $source = Source::factory()->for($user->tree)->create(['entry_number' => '47']);

    if ($stored === 'record type') {
        $source->update(['record_type' => SourceRecordType::Birth]);
    } else {
        $source->update(['parish_id' => Parish::factory()->for($user->tree)->create()->id]);
    }

    $response = $this->actingAs($user)->put(route('sources.update', $source), ['kind' => 'gravestone']);

    $response->assertInvalid('kind');
    expect($response->getSession()->get('errors')->getBag('default')->first('kind'))->toBe('Zmiana rodzaju usunie pola opisu tego rodzaju. Potwierdź zmianę.');

    $source->refresh();

    expect($source->kind)->toBe(SourceKind::MetricalRecord);
    expect($source->entry_number)->toBe('47');
})->with(['record type', 'parish']);

test('changing an akt to another kind with confirmation erases type and parish but keeps the legacy entry number', function () {
    $user = User::factory()->create();
    $parish = Parish::factory()->for($user->tree)->create();
    $source = Source::factory()->for($user->tree)->described()->create(['parish_id' => $parish->id]);

    $this->actingAs($user)->put(route('sources.update', $source), [
        'kind' => 'gravestone',
        'discard_record_fields' => '1',
        'name' => 'Nagrobek',
        'record_type' => 'birth',
        'entry_number' => '99',
    ])->assertSessionHasNoErrors();

    $source->refresh();

    expect($source->kind)->toBe(SourceKind::Gravestone);
    expect($source->record_type)->toBeNull();
    expect($source->parish_id)->toBeNull();
    expect($source->entry_number)->toBe('47');
    expect($source->name)->toBe('Nagrobek');
});

test('changing an akt with only a legacy entry number to another kind needs no confirmation and keeps the number', function () {
    $user = User::factory()->create();
    $source = Source::factory()->for($user->tree)->create(['entry_number' => '47']);

    $this->actingAs($user)->put(route('sources.update', $source), ['kind' => 'other'])
        ->assertSessionHasNoErrors();

    $source->refresh();

    expect($source->kind)->toBe(SourceKind::Other);
    expect($source->entry_number)->toBe('47');
});

test('saving a source without an entry number in the body keeps the legacy entry number', function () {
    $user = User::factory()->create();
    $source = Source::factory()->for($user->tree)->described()->create();

    $this->actingAs($user)->put(route('sources.update', $source), ['kind' => 'metrical_record', 'name' => 'Inna nazwa'])
        ->assertSessionHasNoErrors();

    expect($source->refresh()->entry_number)->toBe('47');
});

test('clearing the legacy entry number empties only that value and toasts', function () {
    $user = User::factory()->create();
    $source = Source::factory()->for($user->tree)->described()->create();

    $this->actingAs($user)->from(route('sources.index', ['source' => $source->id]))->delete(route('sources.legacy-entry-number.destroy', $source))
        ->assertRedirect(route('sources.index', ['source' => $source->id]))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Usunięto stary numer wpisu']);

    $source->refresh();

    expect($source->entry_number)->toBeNull();
    expect($source->name)->toBe('Urodzenia Lipnica 1878 s. 12');
    expect($source->record_type)->toBe(SourceRecordType::Birth);
});

test('clearing the legacy entry number of another user\'s source is a 404', function () {
    $source = Source::factory()->for(User::factory()->create()->tree)->described()->create();

    $this->actingAs(User::factory()->create())->delete(route('sources.legacy-entry-number.destroy', $source))
        ->assertNotFound();

    expect($source->refresh()->entry_number)->toBe('47');
});

test('changing the kind of a linked akt keeps its event links and the events stay confirmed', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $event = Event::factory()->for($user->tree)->birth()->create();
    $event->participants()->attach($person, ['role' => ParticipantRole::Subject]);
    $source = Source::factory()->for($user->tree)->described()->create();
    $event->sources()->attach($source);

    $this->actingAs($user)->put(route('sources.update', $source), [
        'kind' => 'other',
        'discard_record_fields' => '1',
    ])->assertSessionHasNoErrors();

    expect($event->sources()->pluck('sources.id')->all())->toBe([$source->id]);
    $this->actingAs($user)->get(personPreviewUrl($person))->assertInertia(fn ($page) => $page
        ->where('personPreview.person.birth.is_confirmed', true));
});

test('updating never changes the original name or the stored file paths', function () {
    $user = User::factory()->create();
    $source = Source::factory()->for($user->tree)->withThumbnail()->create(['original_name' => 'akt.jpg']);
    $before = $source->only(['original_name', 'path', 'thumbnail_path']);

    $this->actingAs($user)->put(route('sources.update', $source), [
        'kind' => 'other',
        'name' => 'Inna nazwa',
        'original_name' => 'podmienione.jpg',
        'path' => 'sources/1/podmienione.jpg',
        'thumbnail_path' => 'sources/1/thumbnails/podmienione.jpg',
    ])->assertSessionHasNoErrors();

    expect($source->refresh()->only(['original_name', 'path', 'thumbnail_path']))->toBe($before);
});

test('the source resource falls back to the file name when the name is empty', function () {
    $user = User::factory()->create();
    Source::factory()->for($user->tree)->create(['original_name' => 'IMG_2034.jpg']);

    $this->actingAs($user)->get(route('sources.index'))->assertInertia(fn ($page) => $page
        ->where('sources.0.name', null)
        ->where('sources.0.display_name', 'IMG_2034.jpg'));

    Source::query()->update(['name' => 'Akt chrztu']);

    $this->actingAs($user)->get(route('sources.index'))->assertInertia(fn ($page) => $page
        ->where('sources.0.display_name', 'Akt chrztu')
        ->where('sources.0.original_name', 'IMG_2034.jpg'));
});

test('the index page and the source panel expose the kind and record type options', function () {
    $user = User::factory()->create();
    $source = Source::factory()->for($user->tree)->create();

    $this->actingAs($user)->get(route('sources.index'))->assertInertia(fn ($page) => $page
        ->where('kinds', SourceKind::options())
        ->where('recordTypes', SourceRecordType::options()));

    $this->actingAs($user)->get(route('sources.index', ['source' => $source->id]))->assertInertia(fn ($page) => $page
        ->where('kinds', SourceKind::options())
        ->where('recordTypes', SourceRecordType::options()));
});

test('the file download name ignores the source name', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $source = Source::factory()->for($user->tree)->create([
        'original_name' => 'oryginal.jpg',
        'name' => 'Akt chrztu',
        'path' => "sources/{$user->tree->id}/original.jpg",
    ]);
    Storage::disk('local')->put($source->path, 'original-bytes');

    $response = $this->actingAs($user)->get(route('sources.file', $source));

    expect($response->headers->get('Content-Disposition'))->toContain('oryginal.jpg')->not->toContain('Akt');
});

test('the index counts the linked events per source', function () {
    $user = User::factory()->create();
    $unlinked = Source::factory()->for($user->tree)->create();
    $linked = Source::factory()->for($user->tree)->create();
    $linked->events()->attach([
        Event::factory()->for($user->tree)->birth()->create()->id,
        Event::factory()->for($user->tree)->death()->create()->id,
    ]);

    $this->actingAs($user)->get(route('sources.index'))->assertInertia(fn ($page) => $page
        ->where('sources.0.id', $linked->id)
        ->where('sources.0.events_count', 2)
        ->where('sources.1.id', $unlinked->id)
        ->where('sources.1.events_count', 0));
});

test('a marriage event with two participants counts once', function () {
    $user = User::factory()->create();
    $source = Source::factory()->for($user->tree)->create();
    $marriage = Event::factory()->for($user->tree)->create(['type' => EventType::Marriage]);
    $marriage->participants()->attach(Person::factory()->for($user->tree)->create(), ['role' => ParticipantRole::Spouse]);
    $marriage->participants()->attach(Person::factory()->for($user->tree)->create(), ['role' => ParticipantRole::Spouse]);
    $marriage->sources()->attach($source);

    $this->actingAs($user)->get(route('sources.index'))->assertInertia(fn ($page) => $page
        ->where('sources.0.events_count', 1));
});

test('the source panel and the picker payload carry no events count', function () {
    $user = User::factory()->create();
    $source = Source::factory()->for($user->tree)->create();
    $source->events()->attach(Event::factory()->for($user->tree)->birth()->create());

    $this->actingAs($user)->get(route('sources.index', ['source' => $source->id]))->assertInertia(fn ($page) => $page
        ->has('sourcePanel.source')
        ->missing('sourcePanel.source.events_count'));

    $version = $this->actingAs($user)->get(route('sources.index'), ['X-Inertia' => 'true'])->headers->get('X-Inertia-Version');

    $response = $this->actingAs($user)->get(route('sources.index'), [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => $version,
        'X-Inertia-Partial-Data' => 'availableSources',
        'X-Inertia-Partial-Component' => 'sources/index',
    ]);

    expect($response->json('props.availableSources.0'))->not->toHaveKey('events_count');
});

test('the index filters default to all kinds, the gallery and newest first', function () {
    $this->actingAs(User::factory()->create())->get(route('sources.index'))->assertInertia(fn ($page) => $page
        ->where('filters', [
            'q' => '',
            'kind' => 'wszystkie',
            'view' => 'galeria',
            'sort' => '-dodano',
            'place' => '',
            'year_from' => null,
            'year_to' => null,
            'archive' => '',
        ]));
});

test('the index echoes a valid place', function (string $place) {
    $this->actingAs(User::factory()->create())->get(route('sources.index', ['place' => $place]))->assertInertia(fn ($page) => $page
        ->where('filters.place', $place));
})->with(['locality' => ['miejscowosc-3'], 'parish' => ['parafia-5'], 'cemetery' => ['cmentarz-7']]);

test('the index echoes the year bounds and the archive', function () {
    $this->actingAs(User::factory()->create())->get(route('sources.index', [
        'year_from' => '1850',
        'year_to' => '1900',
        'archive' => 'Archiwum Diecezjalne w Tarnowie',
    ]))->assertInertia(fn ($page) => $page
        ->where('filters.year_from', 1850)
        ->where('filters.year_to', 1900)
        ->where('filters.archive', 'Archiwum Diecezjalne w Tarnowie'));
});

test('the index echoes a reversed year range without swapping its bounds', function () {
    $this->actingAs(User::factory()->create())->get(route('sources.index', ['year_from' => '1900', 'year_to' => '1850']))
        ->assertInertia(fn ($page) => $page
            ->where('filters.year_from', 1900)
            ->where('filters.year_to', 1850));
});

test('the index drops invalid place, year and archive parameters', function (array $query, string $key, mixed $expected) {
    $this->actingAs(User::factory()->create())->get(route('sources.index', $query))->assertInertia(fn ($page) => $page
        ->where("filters.{$key}", $expected));
})->with([
    'place with another prefix' => [['place' => 'osoba-3'], 'place', ''],
    'place without an id' => [['place' => 'parafia-'], 'place', ''],
    'place array' => [['place' => ['miejscowosc-3']], 'place', ''],
    'year letters' => [['year_from' => 'abc'], 'year_from', null],
    'year zero' => [['year_from' => '0'], 'year_from', null],
    'year above 9999' => [['year_to' => '10000'], 'year_to', null],
    'year array' => [['year_to' => ['1900']], 'year_to', null],
    'archive of spaces' => [['archive' => '     '], 'archive', ''],
    'archive array' => [['archive' => ['Archiwum A']], 'archive', ''],
]);

test('a partial reload of the filters with the new parameters runs no sources query', function () {
    $user = User::factory()->create();
    Source::factory()->for($user->tree)->create();

    $version = $this->actingAs($user)->get(route('sources.index'), ['X-Inertia' => 'true'])->headers->get('X-Inertia-Version');

    DB::enableQueryLog();

    $response = $this->actingAs($user)->get(route('sources.index', [
        'place' => 'miejscowosc-3',
        'year_from' => '1850',
        'year_to' => '1900',
        'archive' => 'Archiwum A',
    ]), [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => $version,
        'X-Inertia-Partial-Data' => 'filters',
        'X-Inertia-Partial-Component' => 'sources/index',
    ]);

    $sourceQueries = collect(DB::getQueryLog())->filter(fn (array $log): bool => str_contains($log['query'], '"sources"'));

    $response->assertOk();
    expect($response->json('props.filters'))->toMatchArray([
        'place' => 'miejscowosc-3',
        'year_from' => 1850,
        'year_to' => 1900,
        'archive' => 'Archiwum A',
    ]);
    expect($response->json('props'))->not->toHaveKey('sources')->not->toHaveKey('placeOptions')->not->toHaveKey('archiveOptions');
    expect($sourceQueries)->toBeEmpty();
});

test('the place options list the cemeteries of gravestones and the seats of act parishes of the tree only', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $cemeteryLocality = Locality::factory()->for($user->tree)->create(['name' => 'Luboml']);
    $cemetery = Cemetery::factory()->in($cemeteryLocality)->unnamed()->create();
    $namedCemetery = Cemetery::factory()->in($cemeteryLocality)->create(['name' => 'Parafialny']);
    Cemetery::factory()->in($cemeteryLocality)->create(['name' => 'Nieużywany']);
    $seat = Locality::factory()->for($user->tree)->create(['name' => 'Tarnów']);
    Locality::factory()->for($user->tree)->create(['name' => 'Nieużyta']);
    $foreignLocality = Locality::factory()->for($other->tree)->create(['name' => 'Obca']);
    $seated = Parish::factory()->for($user->tree)->seatedIn($seat)->dedicatedTo('św. Jana')->create();
    $seatless = Parish::factory()->for($user->tree)->legacy('Bez siedziby')->create();
    Parish::factory()->for($user->tree)->seatedIn($cemeteryLocality)->dedicatedTo('Nieużywana')->create();

    Source::factory()->for($user->tree)->gravestone()->create(['cemetery_id' => $cemetery->id]);
    Source::factory()->for($user->tree)->gravestone()->create(['cemetery_id' => $namedCemetery->id]);
    Source::factory()->for($user->tree)->create(['parish_id' => $seated->id]);
    Source::factory()->for($user->tree)->create(['parish_id' => $seatless->id]);
    Source::factory()->for($other->tree)->gravestone()->create(['cemetery_id' => Cemetery::factory()->in($foreignLocality)->create()->id]);

    $options = $this->actingAs($user)->get(route('sources.index'))->inertiaProps('placeOptions');

    expect($options)->toEqual([
        'localities' => [
            ['id' => $cemeteryLocality->id, 'name' => 'Luboml'],
            ['id' => $seat->id, 'name' => 'Tarnów'],
        ],
        'parishes' => [
            ['id' => $seatless->id, 'label' => $seatless->label()],
            ['id' => $seated->id, 'label' => $seated->label()],
        ],
        'cemeteries' => [
            ['id' => $cemetery->id, 'label' => 'cmentarz Luboml'],
            ['id' => $namedCemetery->id, 'label' => 'Parafialny, Luboml'],
        ],
    ]);
});

test('the archive options list the distinct non-blank archives of the tree only', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    Source::factory()->for($user->tree)->create(['archive' => 'Archiwum B']);
    Source::factory()->for($user->tree)->create(['archive' => 'Archiwum A']);
    Source::factory()->for($user->tree)->create(['archive' => 'Archiwum A']);
    Source::factory()->for($user->tree)->create(['archive' => '   ']);
    Source::factory()->for($user->tree)->create(['archive' => null]);
    Source::factory()->for($other->tree)->create(['archive' => 'Cudze archiwum']);

    $this->actingAs($user)->get(route('sources.index'))->assertInertia(fn ($page) => $page
        ->where('archiveOptions', ['Archiwum A', 'Archiwum B']));
});

test('the source resource exposes the id of the parish seat, null without a seat or a parish', function () {
    $user = User::factory()->create();
    $locality = Locality::factory()->for($user->tree)->create();
    $seated = Parish::factory()->for($user->tree)->seatedIn($locality)->create();
    $seatless = Parish::factory()->for($user->tree)->legacy('Bez siedziby')->create();

    $withSeat = Source::factory()->for($user->tree)->create(['parish_id' => $seated->id]);
    $withoutSeat = Source::factory()->for($user->tree)->create(['parish_id' => $seatless->id]);
    $withoutParish = Source::factory()->for($user->tree)->create(['parish_id' => null]);

    $sources = collect($this->actingAs($user)->get(route('sources.index'))->inertiaProps('sources'))
        ->mapWithKeys(fn (array $source): array => [$source['id'] => $source['parish_locality_id']])
        ->all();

    expect($sources)->toEqual([
        $withSeat->id => $locality->id,
        $withoutSeat->id => null,
        $withoutParish->id => null,
    ]);
});

test('the index echoes each valid kind, the table view and each valid sort', function (array $query, string $key, string $expected) {
    $this->actingAs(User::factory()->create())->get(route('sources.index', $query))->assertInertia(fn ($page) => $page
        ->where("filters.{$key}", $expected));
})->with([
    'akty' => [['kind' => 'akty'], 'kind', 'akty'],
    'nagrobki' => [['kind' => 'nagrobki'], 'kind', 'nagrobki'],
    'inne' => [['kind' => 'inne'], 'kind', 'inne'],
    'tabela' => [['view' => 'tabela'], 'view', 'tabela'],
    ...collect(['nazwa', 'rodzaj', 'rok', 'potwierdza', 'dodano'])
        ->flatMap(fn (string $key): array => [$key, "-{$key}"])
        ->mapWithKeys(fn (string $sort): array => ["sort {$sort}" => [['view' => 'tabela', 'sort' => $sort], 'sort', $sort]])
        ->all(),
]);

test('the index falls back to the defaults for unknown, array and blank parameters', function (array $query, string $key, string $expected) {
    $this->actingAs(User::factory()->create())->get(route('sources.index', $query))->assertInertia(fn ($page) => $page
        ->where("filters.{$key}", $expected));
})->with([
    'unknown kind' => [['kind' => 'zdjecia'], 'kind', 'wszystkie'],
    'array kind' => [['kind' => ['akty']], 'kind', 'wszystkie'],
    'blank kind' => [['kind' => ''], 'kind', 'wszystkie'],
    'unknown view' => [['view' => 'lista'], 'view', 'galeria'],
    'array view' => [['view' => ['tabela']], 'view', 'galeria'],
    'blank view' => [['view' => ''], 'view', 'galeria'],
    'unknown sort' => [['view' => 'tabela', 'sort' => 'kolor'], 'sort', '-dodano'],
    'array sort' => [['view' => 'tabela', 'sort' => ['rok']], 'sort', '-dodano'],
    'blank sort' => [['view' => 'tabela', 'sort' => ''], 'sort', '-dodano'],
    'sort without the table view' => [['sort' => 'rok'], 'sort', '-dodano'],
    'sort in the gallery view' => [['view' => 'galeria', 'sort' => '-nazwa'], 'sort', '-dodano'],
]);

test('the index search query is trimmed, cut to 100 characters and empty for a non-string', function (array $query, string $expected) {
    $this->actingAs(User::factory()->create())->get(route('sources.index', $query))->assertInertia(fn ($page) => $page
        ->where('filters.q', $expected));
})->with([
    'trimmed' => [['q' => '  akt  '], 'akt'],
    'cut' => [['q' => str_repeat('a', 120)], str_repeat('a', 100)],
    'array' => [['q' => ['akt']], ''],
]);

test('the index returns the whole tree in the same order whatever the filters say', function () {
    $user = User::factory()->create();
    $older = Source::factory()->for($user->tree)->create();
    $newer = Source::factory()->gravestone()->for($user->tree)->create();

    $this->actingAs($user)->get(route('sources.index', ['q' => 'xyz', 'kind' => 'nagrobki']))->assertInertia(fn ($page) => $page
        ->has('sources', 2)
        ->where('sources.0.id', $newer->id)
        ->where('sources.1.id', $older->id));
});

test('a partial reload of the filters runs no sources query', function () {
    $user = User::factory()->create();
    Source::factory()->for($user->tree)->create();

    $version = $this->actingAs($user)->get(route('sources.index'), ['X-Inertia' => 'true'])->headers->get('X-Inertia-Version');

    DB::enableQueryLog();

    $response = $this->actingAs($user)->get(route('sources.index', ['kind' => 'akty']), [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => $version,
        'X-Inertia-Partial-Data' => 'filters',
        'X-Inertia-Partial-Component' => 'sources/index',
    ]);

    $sourceQueries = collect(DB::getQueryLog())->filter(fn (array $log): bool => str_contains($log['query'], '"sources"'));

    $response->assertOk();
    expect($response->json('props.filters.kind'))->toBe('akty');
    expect($response->json('props'))->not->toHaveKey('sources');
    expect($sourceQueries)->toBeEmpty();
});

test('a partial reload of the filters with a panel key computes neither the list nor the panel', function () {
    $user = User::factory()->create();
    $source = Source::factory()->for($user->tree)->create();

    $version = $this->actingAs($user)->get(route('sources.index'), ['X-Inertia' => 'true'])->headers->get('X-Inertia-Version');

    DB::enableQueryLog();

    $response = $this->actingAs($user)->get(route('sources.index', ['source' => $source->id, 'kind' => 'akty']), [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => $version,
        'X-Inertia-Partial-Data' => 'filters',
        'X-Inertia-Partial-Component' => 'sources/index',
    ]);

    $sourceQueries = collect(DB::getQueryLog())->filter(fn (array $log): bool => str_contains($log['query'], '"sources"'));

    $response->assertOk();
    expect($response->json('props.filters.kind'))->toBe('akty');
    expect($response->json('props'))->not->toHaveKey('sources')->not->toHaveKey('sourcePanel');
    expect($sourceQueries)->toBeEmpty();
});

test('storing keeps the list filters of the previous sources page and opens the new source', function () {
    Storage::fake('local');

    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->from('/sources?view=tabela&kind=akty&q=Kraków&sort=nazwa&source=new')
        ->post(route('sources.store'), [
            'kind' => 'metrical_record',
            'name' => 'Akt Kraków 1890',
            'file' => UploadedFile::fake()->image('akt.jpg', 400, 300),
        ]);

    $source = Source::sole();

    $response->assertRedirect(route('sources.index', [
        'q' => 'Kraków',
        'kind' => 'akty',
        'view' => 'tabela',
        'sort' => 'nazwa',
        'source' => $source->id,
    ]));
});

test('storing drops the kind and search filters that would hide the new source', function () {
    Storage::fake('local');

    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->from('/sources?view=tabela&kind=akty&q=Kraków&sort=nazwa&source=new')
        ->post(route('sources.store'), [
            'kind' => 'gravestone',
            'name' => 'Nagrobek Nowak',
            'file' => UploadedFile::fake()->image('nagrobek.jpg', 400, 300),
        ]);

    $response->assertRedirect(route('sources.index', [
        'view' => 'tabela',
        'sort' => 'nazwa',
        'source' => Source::sole()->id,
    ]));
});

test('storing from a page other than the sources list carries no filters', function () {
    Storage::fake('local');

    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->from('/persons?q=Kraków&kind=akty')
        ->post(route('sources.store'), [
            'kind' => 'metrical_record',
            'file' => UploadedFile::fake()->image('akt.jpg', 400, 300),
        ]);

    $response->assertRedirect(route('sources.index', ['source' => Source::sole()->id]));
});

test('storing without a referer carries no filters, even after a filtered list visit in the session', function () {
    Storage::fake('local');

    $user = User::factory()->create();

    $this->actingAs($user)->get('/sources?kind=akty&view=tabela')->assertOk();

    $response = $this->post(route('sources.store'), [
        'kind' => 'metrical_record',
        'file' => UploadedFile::fake()->image('akt.jpg', 400, 300),
    ]);

    $response->assertRedirect(route('sources.index', ['source' => Source::sole()->id]));
});

test('storing from a sources list on another host carries no filters', function () {
    Storage::fake('local');

    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->from('https://example.com/sources?kind=akty')
        ->post(route('sources.store'), [
            'kind' => 'metrical_record',
            'file' => UploadedFile::fake()->image('akt.jpg', 400, 300),
        ]);

    $response->assertRedirect(route('sources.index', ['source' => Source::sole()->id]));
});

test('storing drops invalid, default and foreign list parameters', function () {
    Storage::fake('local');

    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->from('/sources?kind=zle&view=galeria&sort=nazwa&person=5&source=new')
        ->post(route('sources.store'), [
            'kind' => 'metrical_record',
            'file' => UploadedFile::fake()->image('akt.jpg', 400, 300),
        ]);

    $response->assertRedirect(route('sources.index', ['source' => Source::sole()->id]));
});

test('destroying closes the panel and keeps the list filters of the previous page', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $source = Source::factory()->for($user->tree)->create();

    $this->actingAs($user)
        ->from("/sources?view=tabela&source={$source->id}")
        ->delete(route('sources.destroy', $source))
        ->assertRedirect(route('sources.index', ['view' => 'tabela']));

    expect(Source::find($source->id))->toBeNull();
});

test('storing keeps the place, year bounds and archive that fit the new source', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $seat = Locality::factory()->for($user->tree)->create();
    $parish = Parish::factory()->for($user->tree)->seatedIn($seat)->create();

    $response = $this->actingAs($user)
        ->from("/sources?place=miejscowosc-{$seat->id}&year_from=1850&year_to=1900&archive=Archiwum%20A&source=new")
        ->post(route('sources.store'), [
            'kind' => 'metrical_record',
            'year' => 1880,
            'archive' => 'Archiwum A',
            'parish_id' => $parish->id,
            'file' => UploadedFile::fake()->image('akt.jpg', 400, 300),
        ]);

    $response->assertRedirect(route('sources.index', [
        'place' => "miejscowosc-{$seat->id}",
        'year_from' => '1850',
        'year_to' => '1900',
        'archive' => 'Archiwum A',
        'source' => Source::sole()->id,
    ]));
});

test('storing an akt keeps a place that names its parish', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $parish = Parish::factory()->for($user->tree)->create();

    $this->actingAs($user)
        ->from("/sources?place=parafia-{$parish->id}&source=new")
        ->post(route('sources.store'), [
            'kind' => 'metrical_record',
            'parish_id' => $parish->id,
            'file' => UploadedFile::fake()->image('akt.jpg', 400, 300),
        ])
        ->assertRedirect(route('sources.index', ['place' => "parafia-{$parish->id}", 'source' => Source::sole()->id]));
});

test('storing a gravestone keeps a place that names its cemetery or the locality of that cemetery', function (string $placeType) {
    Storage::fake('local');

    $user = User::factory()->create();
    $cemetery = Cemetery::factory()->for($user->tree)->unnamed()->create();
    $place = $placeType === 'cmentarz' ? "cmentarz-{$cemetery->id}" : "miejscowosc-{$cemetery->locality_id}";

    $this->actingAs($user)
        ->from("/sources?place={$place}&source=new")
        ->post(route('sources.store'), [
            'kind' => 'gravestone',
            'cemetery_id' => $cemetery->id,
            'file' => UploadedFile::fake()->image('nagrobek.jpg', 400, 300),
        ])
        ->assertRedirect(route('sources.index', ['place' => $place, 'source' => Source::sole()->id]));
})->with(['cemetery' => ['cmentarz'], 'locality of the cemetery' => ['miejscowosc']]);

test('storing a gravestone drops a place that names another cemetery', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $cemetery = Cemetery::factory()->for($user->tree)->create();
    $otherCemetery = Cemetery::factory()->for($user->tree)->create();

    $this->actingAs($user)
        ->from("/sources?place=cmentarz-{$otherCemetery->id}&source=new")
        ->post(route('sources.store'), [
            'kind' => 'gravestone',
            'cemetery_id' => $cemetery->id,
            'file' => UploadedFile::fake()->image('nagrobek.jpg', 400, 300),
        ])
        ->assertRedirect(route('sources.index', ['source' => Source::sole()->id]));
});

test('the cemetery options are an optional shared prop of the own tree', function () {
    $user = User::factory()->create();
    $named = Cemetery::factory()->for($user->tree)->create(['name' => 'Parafialny']);
    Cemetery::factory()->for(User::factory()->create()->tree)->create();

    $this->actingAs($user)->get(route('sources.index'))->assertInertia(fn ($page) => $page->missing('cemeteryOptions'));

    $version = $this->actingAs($user)->get(route('sources.index'), ['X-Inertia' => 'true'])->headers->get('X-Inertia-Version');

    $response = $this->actingAs($user)->get(route('sources.index'), [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => $version,
        'X-Inertia-Partial-Data' => 'cemeteryOptions',
        'X-Inertia-Partial-Component' => 'sources/index',
    ]);

    expect($response->json('props.cemeteryOptions'))->toBe([
        ['id' => $named->id, 'label' => $named->label(), 'locality_id' => $named->locality_id, 'locality_name' => $named->locality->name],
    ]);
});

test('storing an akt drops a place that names another parish', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $parish = Parish::factory()->for($user->tree)->create();
    $otherParish = Parish::factory()->for($user->tree)->create();

    $this->actingAs($user)
        ->from("/sources?place=parafia-{$otherParish->id}&source=new")
        ->post(route('sources.store'), [
            'kind' => 'metrical_record',
            'parish_id' => $parish->id,
            'file' => UploadedFile::fake()->image('akt.jpg', 400, 300),
        ])
        ->assertRedirect(route('sources.index', ['source' => Source::sole()->id]));
});

test('storing drops the place, year bound and archive that would hide the new source', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $seat = Locality::factory()->for($user->tree)->create();
    $parish = Parish::factory()->for($user->tree)->seatedIn($seat)->create();

    $response = $this->actingAs($user)
        ->from('/sources?place=parafia-999&year_from=1850&year_to=1870&archive=Archiwum%20B&source=new')
        ->post(route('sources.store'), [
            'kind' => 'metrical_record',
            'year' => 1880,
            'archive' => 'Archiwum A',
            'parish_id' => $parish->id,
            'file' => UploadedFile::fake()->image('akt.jpg', 400, 300),
        ]);

    $response->assertRedirect(route('sources.index', [
        'year_from' => '1850',
        'source' => Source::sole()->id,
    ]));
});

test('storing drops the year bounds when the new source has no year', function () {
    Storage::fake('local');

    $user = User::factory()->create();

    $this->actingAs($user)
        ->from('/sources?year_from=1850&year_to=1900&source=new')
        ->post(route('sources.store'), [
            'kind' => 'gravestone',
            'file' => UploadedFile::fake()->image('nagrobek.jpg', 400, 300),
        ])
        ->assertRedirect(route('sources.index', ['source' => Source::sole()->id]));
});

test('storing drops invalid and blank new list parameters', function () {
    Storage::fake('local');

    $user = User::factory()->create();

    $this->actingAs($user)
        ->from('/sources?place=osoba-1&year_from=abc&year_to=0&archive=%20%20&source=new')
        ->post(route('sources.store'), [
            'kind' => 'metrical_record',
            'file' => UploadedFile::fake()->image('akt.jpg', 400, 300),
        ])
        ->assertRedirect(route('sources.index', ['source' => Source::sole()->id]));
});

test('destroying keeps the place, year bounds and archive of the previous page', function () {
    $user = User::factory()->create();
    $source = Source::factory()->for($user->tree)->create();

    $this->actingAs($user)
        ->from("/sources?place=parafia-5&year_from=1850&year_to=1900&archive=Archiwum%20A&source={$source->id}")
        ->delete(route('sources.destroy', $source))
        ->assertRedirect(route('sources.index', [
            'place' => 'parafia-5',
            'year_from' => '1850',
            'year_to' => '1900',
            'archive' => 'Archiwum A',
        ]));
});

test('storing an akt saves the parish and the archive', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $parish = Parish::factory()->for($user->tree)->create();

    $this->actingAs($user)->post(route('sources.store'), [
        'kind' => 'metrical_record',
        'file' => UploadedFile::fake()->image('akt.jpg'),
        'parish_id' => $parish->id,
        'archive' => 'Archiwum Diecezjalne w Tarnowie',
    ])->assertSessionHasNoErrors();

    $source = Source::with('parish')->firstOrFail();

    expect($source->parish?->is($parish))->toBeTrue()
        ->and($source->archive)->toBe('Archiwum Diecezjalne w Tarnowie');
});

test('storing a gravestone ignores a submitted year, archive and parish', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $parish = Parish::factory()->for($user->tree)->create();

    $this->actingAs($user)->post(route('sources.store'), [
        'kind' => 'gravestone',
        'file' => UploadedFile::fake()->image('nagrobek.jpg'),
        'parish_id' => $parish->id,
        'year' => 1932,
        'archive' => 'Archiwum cmentarne',
    ])->assertSessionHasNoErrors();

    $source = Source::firstOrFail();

    expect($source->parish_id)->toBeNull()
        ->and($source->year)->toBeNull()
        ->and($source->archive)->toBeNull();
});

test('another tree parish is rejected on store and update without saving', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $foreignParish = Parish::factory()->for(User::factory()->create()->tree)->create();

    $this->actingAs($user)->post(route('sources.store'), [
        'kind' => 'metrical_record',
        'file' => UploadedFile::fake()->image('akt.jpg'),
        'parish_id' => $foreignParish->id,
    ])->assertInvalid('parish_id');

    expect(Source::count())->toBe(0);

    $source = Source::factory()->for($user->tree)->create();

    $this->actingAs($user)->put(route('sources.update', $source), [
        'kind' => 'metrical_record',
        'parish_id' => $foreignParish->id,
    ])->assertInvalid('parish_id');

    expect($source->refresh()->parish_id)->toBeNull();
});

test('updating a source never changes the stored provenance', function () {
    $user = User::factory()->create();
    $source = Source::factory()->for($user->tree)->create();
    DB::table('sources')->where('id', $source->id)->update(['provenance' => 'Stare pochodzenie']);

    $this->actingAs($user)->put(route('sources.update', $source), [
        'kind' => 'metrical_record',
        'archive' => 'Nowe archiwum',
        'provenance' => 'Podmienione',
    ])->assertSessionHasNoErrors();

    expect(DB::table('sources')->where('id', $source->id)->value('provenance'))->toBe('Stare pochodzenie')
        ->and($source->refresh()->archive)->toBe('Nowe archiwum');
});

test('changing an akt with a parish to another kind needs confirmation and then clears the parish', function () {
    $user = User::factory()->create();
    $parish = Parish::factory()->for($user->tree)->create();
    $source = Source::factory()->for($user->tree)->create(['parish_id' => $parish->id, 'year' => 1878, 'archive' => 'Archiwum']);

    $response = $this->actingAs($user)->put(route('sources.update', $source), ['kind' => 'gravestone', 'archive' => 'Archiwum']);

    $response->assertInvalid('kind');
    expect($source->refresh()->parish_id)->toBe($parish->id);

    $this->actingAs($user)->put(route('sources.update', $source), [
        'kind' => 'gravestone',
        'discard_record_fields' => '1',
        'archive' => 'Archiwum',
    ])->assertSessionHasNoErrors();

    $source->refresh();

    expect($source->kind)->toBe(SourceKind::Gravestone)
        ->and($source->parish_id)->toBeNull()
        ->and($source->archive)->toBeNull()
        ->and($source->year)->toBeNull()
        ->and($source->note)->toContain('Rok: 1878')
        ->and($source->note)->toContain('Archiwum: Archiwum');
});

test('changing an akt with entry numbers to a gravestone moves year, archive and numbers to the submitted note', function () {
    $user = User::factory()->create();
    $source = Source::factory()->for($user->tree)->described()->create(['note' => 'Stara notatka.']);
    $first = Event::factory()->for($user->tree)->create();
    $second = Event::factory()->for($user->tree)->create();
    $first->sources()->attach($source, ['entry_number' => '12']);
    $second->sources()->attach($source, ['entry_number' => '47/3']);

    $this->actingAs($user)->put(route('sources.update', $source), [
        'kind' => 'gravestone',
        'discard_record_fields' => '1',
        'year' => 1878,
        'archive' => 'Archiwum Państwowe w Krakowie',
        'note' => 'Nowa notatka.',
    ])->assertSessionHasNoErrors();

    $source->refresh();

    expect($source->note)->toBe("Nowa notatka.\n\nRok: 1878\nArchiwum: Archiwum Państwowe w Krakowie\nNumer wpisu: 12\nNumer wpisu: 47/3")
        ->and($source->year)->toBeNull()
        ->and($source->archive)->toBeNull();
    expect(DB::table('event_source')->where('source_id', $source->id)->whereNotNull('entry_number')->count())->toBe(0);
    expect(DB::table('event_source')->where('source_id', $source->id)->count())->toBe(2);
});

test('updating a gravestone with a year and an archive in the body leaves both empty', function () {
    $user = User::factory()->create();
    $source = Source::factory()->gravestone()->for($user->tree)->create(['note' => 'Notatka.']);

    $this->actingAs($user)->put(route('sources.update', $source), [
        'kind' => 'gravestone',
        'year' => 1932,
        'archive' => 'Archiwum',
        'note' => 'Notatka.',
    ])->assertSessionHasNoErrors();

    $source->refresh();

    expect($source->year)->toBeNull()
        ->and($source->archive)->toBeNull()
        ->and($source->note)->toBe('Notatka.');
});

test('the source resource exposes the parish id and label', function () {
    $user = User::factory()->create();
    $seat = Locality::factory()->for($user->tree)->create(['name' => 'Płock']);
    $parish = Parish::factory()->for($user->tree)->withReligion(Religion::RomanCatholic)->seatedIn($seat)->dedicatedTo('św. Bartłomieja')->create();
    Source::factory()->for($user->tree)->create(['parish_id' => $parish->id]);

    $this->actingAs($user)->get(route('sources.index'))->assertInertia(fn ($page) => $page
        ->where('sources.0.parish_id', $parish->id)
        ->where('sources.0.parish_label', 'Płock św. Bartłomieja (rzym.-kat.)'));
});

test('the source panel carries the parishes, localities and distinct archives of the tree only', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $locality = Locality::factory()->for($user->tree)->create(['name' => 'Tarnów']);
    Locality::factory()->for($other->tree)->create();
    $parish = Parish::factory()->for($user->tree)->seatedIn($locality)->create();
    Parish::factory()->for($other->tree)->create();

    Source::factory()->for($user->tree)->create(['archive' => 'Archiwum A']);
    Source::factory()->for($user->tree)->create(['archive' => 'Archiwum A']);
    Source::factory()->for($user->tree)->create(['archive' => 'Archiwum B']);
    Source::factory()->for($user->tree)->create(['archive' => '   ']);
    Source::factory()->for($user->tree)->create(['archive' => null]);
    Source::factory()->for($other->tree)->create(['archive' => 'Cudze archiwum']);
    $open = Source::factory()->for($user->tree)->create();

    foreach (['new', $open->id] as $key) {
        $this->actingAs($user)->get(route('sources.index', ['source' => $key]))->assertInertia(fn ($page) => $page
            ->has('sourcePanel.parishes', 1)
            ->where('sourcePanel.parishes.0.id', $parish->id)
            ->where('sourcePanel.parishes.0.locality_name', 'Tarnów')
            ->where('sourcePanel.localities', [['id' => $locality->id, 'name' => 'Tarnów', 'is_defunct' => false]])
            ->where('sourcePanel.archives', ['Archiwum A', 'Archiwum B'])
            ->where('sourcePanel.religions', Religion::options()));
    }
});

test('the sources payload carries the parish label and the seat locality name, null without a seat', function () {
    $user = User::factory()->create();
    $locality = Locality::factory()->for($user->tree)->create(['name' => 'Luboml']);
    $seated = Parish::factory()->seatedIn($locality)->dedicatedTo('św. Jana')->create();
    $seatless = Parish::factory()->for($user->tree)->legacy('Parafia bez siedziby')->create();

    $withSeat = Source::factory()->for($user->tree)->create(['parish_id' => $seated->id]);
    $withoutSeat = Source::factory()->for($user->tree)->create(['parish_id' => $seatless->id]);
    $withoutParish = Source::factory()->for($user->tree)->create(['parish_id' => null]);

    $sources = $this->actingAs($user)->get(route('sources.index'))->inertiaProps('sources');

    expect(collect($sources)->mapWithKeys(fn (array $source): array => [
        $source['id'] => [$source['parish_label'], $source['locality_name']],
    ])->all())->toEqual([
        $withSeat->id => ['Luboml św. Jana', 'Luboml'],
        $withoutSeat->id => ['Parafia bez siedziby', null],
        $withoutParish->id => [null, null],
    ]);
});

test('a gravestone carries its cemetery as the locality name and the panel lists the buried people', function () {
    $user = User::factory()->create();
    $locality = Locality::factory()->for($user->tree)->create(['name' => 'Luboml']);
    $cemetery = Cemetery::factory()->in($locality)->create(['name' => 'Parafialny']);
    $first = Person::factory()->for($user->tree)->create(['first_name' => 'Jan', 'last_name' => 'Kowalski']);
    $second = Person::factory()->for($user->tree)->create(['first_name' => 'Maria', 'last_name' => 'Kowalska']);
    $gravestone = Source::factory()->for($user->tree)->gravestone()->create(['cemetery_id' => $cemetery->id]);
    $gravestone->buriedPeople()->attach([$second->id, $first->id]);

    $response = $this->actingAs($user)->get(route('sources.index', ['source' => $gravestone->id]));

    $response->assertInertia(fn ($page) => $page
        ->where('sources.0.locality_name', 'Luboml')
        ->where('sources.0.cemetery_id', $cemetery->id)
        ->where('sources.0.cemetery_label', 'Parafialny, Luboml')
        ->where('sources.0.cemetery_locality_id', $locality->id)
        ->missing('sources.0.buried_people')
        ->where('sourcePanel.source.cemetery_label', 'Parafialny, Luboml')
        ->where('sourcePanel.cemeteries.0.id', $cemetery->id)
        ->has('sourcePanel.cemeteryManagers')
        ->where('sourcePanel.source.buried_people', [
            ['id' => $second->id, 'display_name' => $second->displayName()],
            ['id' => $first->id, 'display_name' => $first->displayName()],
        ]));

    $this->actingAs($user)->get(route('persons.index'))->assertInertia(fn ($page) => $page
        ->missing('availableSources'));
});
