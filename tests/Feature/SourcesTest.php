<?php

use App\Enums\ParticipantRole;
use App\Enums\SourceKind;
use App\Enums\SourceRecordType;
use App\Models\Event;
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
            'id', 'kind', 'kind_label', 'name', 'display_name', 'record_type', 'record_type_label', 'entry_number', 'year', 'provenance', 'url', 'note', 'original_name', 'mime_type', 'size', 'width', 'height', 'has_thumbnail', 'uploaded_at',
        ]))
        ->where('sources.0.id', $newer->id)
        ->where('sources.1.id', $older->id));
});

test('the old upload page returns not found', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/sources/create')->assertNotFound();
});

test('sources show returns not found for a source from another users tree', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $source = Source::factory()->for($otherUser->tree)->create();

    $this->actingAs($user)->get(route('sources.show', $source))->assertNotFound();
});

test('sources show renders the source', function () {
    $user = User::factory()->create();
    $source = Source::factory()->for($user->tree)->withThumbnail()->create();

    $response = $this->actingAs($user)->get(route('sources.show', $source));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('sources/show')
        ->where('source.id', $source->id)
        ->where('source.has_thumbnail', true));
});

test('source show confirms is empty for an unlinked source', function () {
    $user = User::factory()->create();
    $source = Source::factory()->for($user->tree)->create();

    $response = $this->actingAs($user)->get(route('sources.show', $source));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('sources/show')
        ->where('confirms', []));
});

test('source show confirms lists the linked events with the person names and whether this is the last source', function () {
    $user = User::factory()->create();
    $source = Source::factory()->for($user->tree)->create();
    $otherSource = Source::factory()->for($user->tree)->create();
    $person = Person::factory()->for($user->tree)->create(['first_name' => 'Jan', 'last_name' => 'Kowalski']);
    $birth = Event::factory()->for($user->tree)->birth()->create();
    $birth->participants()->attach($person, ['role' => ParticipantRole::Subject]);
    $source->events()->attach($birth);

    $death = Event::factory()->for($user->tree)->death()->create();
    $death->participants()->attach($person, ['role' => ParticipantRole::Subject]);
    $death->sources()->attach([$source->id, $otherSource->id]);

    $response = $this->actingAs($user)->get(route('sources.show', $source));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('sources/show')
        ->has('confirms', 2)
        ->where('confirms.0.event_id', $birth->id)
        ->where('confirms.0.event_type', 'birth')
        ->where('confirms.0.event_label', 'Urodzenie')
        ->where('confirms.0.is_last_source', true)
        ->where('confirms.0.person.id', $person->id)
        ->where('confirms.0.person.display_name', 'Jan Kowalski')
        ->where('confirms.1.event_id', $death->id)
        ->where('confirms.1.is_last_source', false));
});

test('source file returns not found for a source from another users tree', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $source = Source::factory()->for($otherUser->tree)->create();
    Storage::disk('local')->put($source->path, 'content');

    $this->actingAs($user)->get(route('sources.file', $source))->assertNotFound();
});

test('source thumbnail returns not found for a source from another users tree', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $source = Source::factory()->for($otherUser->tree)->withThumbnail()->create();
    Storage::disk('local')->put($source->thumbnail_path, 'content');

    $this->actingAs($user)->get(route('sources.thumbnail', $source))->assertNotFound();
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

    $response->assertRedirect(route('sources.show', $source));

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

test('the kind is exposed on the show page', function () {
    $user = User::factory()->create();
    $source = Source::factory()->gravestone()->for($user->tree)->create();

    $this->actingAs($user)->get(route('sources.show', $source))->assertInertia(fn ($page) => $page
        ->where('source.kind', 'gravestone')
        ->where('source.kind_label', 'zdjęcie nagrobka'));
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

    $response->assertSessionHasNoErrors()->assertRedirect(route('sources.show', $source));
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

    $response->assertSessionHasNoErrors()->assertRedirect(route('sources.show', $source));
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

test('destroy of a source from another users tree returns not found and deletes nothing', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $source = Source::factory()->for($otherUser->tree)->withThumbnail()->create();
    Storage::disk('local')->put($source->path, 'original-bytes');
    Storage::disk('local')->put($source->thumbnail_path, 'thumbnail-bytes');
    $event = Event::factory()->for($otherUser->tree)->create();
    $event->sources()->attach($source);

    $response = $this->actingAs($user)->delete(route('sources.destroy', $source));

    $response->assertNotFound();
    expect(Source::find($source->id))->not->toBeNull();
    expect(DB::table('event_source')->where('source_id', $source->id)->exists())->toBeTrue();
    Storage::disk('local')->assertExists($source->path);
    Storage::disk('local')->assertExists($source->thumbnail_path);
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

    expect($source->only(['name', 'record_type', 'entry_number', 'year', 'provenance', 'url', 'note']))
        ->each->toBeNull();
});

test('storing with a full description persists every field', function () {
    Storage::fake('local');

    $this->actingAs(User::factory()->create())->post(route('sources.store'), [
        'kind' => 'metrical_record',
        'file' => UploadedFile::fake()->image('akt.jpg'),
        'name' => 'Urodzenia Lipnica 1878',
        'record_type' => 'birth',
        'entry_number' => '47a',
        'year' => '1878',
        'provenance' => 'Archiwum w Krakowie',
        'url' => 'https://example.com/akt',
        'note' => "Pierwszy akapit.\n\nDrugi akapit.",
    ])->assertSessionHasNoErrors();

    $source = Source::firstOrFail();

    expect($source->name)->toBe('Urodzenia Lipnica 1878');
    expect($source->record_type)->toBe(SourceRecordType::Birth);
    expect($source->entry_number)->toBe('47a');
    expect($source->year)->toBe(1878);
    expect($source->provenance)->toBe('Archiwum w Krakowie');
    expect($source->url)->toBe('https://example.com/akt');
    expect($source->note)->toBe("Pierwszy akapit.\n\nDrugi akapit.");
});

test('storing a gravestone ignores a submitted record type and entry number', function () {
    Storage::fake('local');

    $this->actingAs(User::factory()->create())->post(route('sources.store'), [
        'kind' => 'gravestone',
        'file' => UploadedFile::fake()->image('nagrobek.jpg'),
        'name' => 'Nagrobek',
        'record_type' => 'birth',
        'entry_number' => '47',
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
    'long provenance' => [['provenance' => str_repeat('a', 256)], 'provenance', 'pochodzenie'],
    'long entry number' => [['entry_number' => str_repeat('1', 51)], 'entry_number', 'numer wpisu'],
    'long note' => [['note' => str_repeat('a', 5001)], 'note', 'notatka'],
]);

test('updating changes the kind and the description and redirects back with the toast', function () {
    $user = User::factory()->create();
    $source = Source::factory()->for($user->tree)->create();

    $this->actingAs($user)->from(route('sources.show', $source))->put(route('sources.update', $source), [
        'kind' => 'metrical_record',
        'name' => 'Nowa nazwa',
        'record_type' => 'death',
        'entry_number' => '12/1878',
        'year' => '1878',
        'provenance' => 'Archiwum',
        'url' => 'https://example.com',
        'note' => 'Notatka',
    ])
        ->assertRedirect(route('sources.show', $source))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Zapisano źródło']);

    $source->refresh();

    expect($source->name)->toBe('Nowa nazwa');
    expect($source->record_type)->toBe(SourceRecordType::Death);
    expect($source->entry_number)->toBe('12/1878');
    expect($source->year)->toBe(1878);
    expect($source->note)->toBe('Notatka');
});

test('updating a source from another users tree returns not found and changes nothing', function () {
    $source = Source::factory()->for(User::factory()->create()->tree)->create(['name' => 'Cudze']);

    $this->actingAs(User::factory()->create())->put(route('sources.update', $source), [
        'kind' => 'other',
        'name' => 'Przejęte',
    ])->assertNotFound();

    $source->refresh();

    expect($source->name)->toBe('Cudze');
    expect($source->kind)->toBe(SourceKind::MetricalRecord);
});

test('changing an akt with a stored record type or entry number to another kind without confirmation is rejected', function (array $stored) {
    $user = User::factory()->create();
    $source = Source::factory()->for($user->tree)->create($stored);

    $response = $this->actingAs($user)->put(route('sources.update', $source), ['kind' => 'gravestone']);

    $response->assertInvalid('kind');
    expect($response->getSession()->get('errors')->getBag('default')->first('kind'))->toBe('Zmiana rodzaju usunie typ aktu i numer wpisu. Potwierdź zmianę.');

    $source->refresh();

    expect($source->kind)->toBe(SourceKind::MetricalRecord);
    expect($source->record_type ?? $source->entry_number)->not->toBeNull();
})->with([
    'record type only' => [['record_type' => SourceRecordType::Birth]],
    'entry number only' => [['entry_number' => '47']],
]);

test('changing an akt to another kind with confirmation saves the kind and erases both fields', function () {
    $user = User::factory()->create();
    $source = Source::factory()->for($user->tree)->described()->create();

    $this->actingAs($user)->put(route('sources.update', $source), [
        'kind' => 'gravestone',
        'discard_record_fields' => '1',
        'name' => 'Nagrobek',
        'record_type' => 'birth',
        'entry_number' => '47',
    ])->assertSessionHasNoErrors();

    $source->refresh();

    expect($source->kind)->toBe(SourceKind::Gravestone);
    expect($source->record_type)->toBeNull();
    expect($source->entry_number)->toBeNull();
    expect($source->name)->toBe('Nagrobek');
});

test('changing an akt with an empty record type and entry number to another kind needs no confirmation', function () {
    $user = User::factory()->create();
    $source = Source::factory()->for($user->tree)->create();

    $this->actingAs($user)->put(route('sources.update', $source), ['kind' => 'other'])
        ->assertSessionHasNoErrors();

    expect($source->refresh()->kind)->toBe(SourceKind::Other);
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

test('the index and show pages expose the kind and record type options', function () {
    $user = User::factory()->create();
    $source = Source::factory()->for($user->tree)->create();

    $this->actingAs($user)->get(route('sources.index'))->assertInertia(fn ($page) => $page
        ->where('kinds', SourceKind::options())
        ->where('recordTypes', SourceRecordType::options()));

    $this->actingAs($user)->get(route('sources.show', $source))->assertInertia(fn ($page) => $page
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
