<?php

use App\Enums\ParticipantRole;
use App\Enums\SourceKind;
use App\Models\Event;
use App\Models\Person;
use App\Models\Source;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

test('guests are redirected to the login page from sources routes', function () {
    $source = Source::factory()->create();

    $this->get(route('sources.index'))->assertRedirect(route('login'));
    $this->get(route('sources.create'))->assertRedirect(route('login'));
    $this->get(route('sources.show', $source))->assertRedirect(route('login'));
    $this->post(route('sources.store'), [])->assertRedirect(route('login'));
    $this->get(route('sources.file', $source))->assertRedirect(route('login'));
    $this->get(route('sources.thumbnail', $source))->assertRedirect(route('login'));
    $this->delete(route('sources.destroy', $source))->assertRedirect(route('login'));
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
            'id', 'kind', 'kind_label', 'original_name', 'mime_type', 'size', 'width', 'height', 'has_thumbnail', 'uploaded_at',
        ]))
        ->where('sources.0.id', $newer->id)
        ->where('sources.1.id', $older->id));
});

test('sources create shows the upload form', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('sources.create'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('sources/create')
        ->where('kinds', SourceKind::options()));
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
