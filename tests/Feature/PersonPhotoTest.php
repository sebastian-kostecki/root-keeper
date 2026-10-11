<?php

use App\Actions\Sources\GenerateSourceThumbnail;
use App\Models\Person;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * A real JPEG upload from the shared fixture.
 */
function personPhotoUpload(string $name = 'portret.jpg'): UploadedFile
{
    return new UploadedFile(base_path('tests/Fixtures/portrait-small.jpg'), $name, 'image/jpeg', null, true);
}

/**
 * Put a photo and its thumbnail on the disk and point the person at them, as a previous upload would have.
 *
 * @return array{photo: string, thumbnail: string}
 */
function personPhotoStoredFor(Person $person): array
{
    $paths = [
        'photo' => "persons/{$person->tree_id}/".fake()->uuid().'.jpg',
        'thumbnail' => "persons/{$person->tree_id}/thumbnails/".fake()->uuid().'.jpg',
    ];

    Storage::disk('local')->put($paths['photo'], 'original-bytes');
    Storage::disk('local')->put($paths['thumbnail'], 'thumbnail-bytes');
    $person->update(['photo_path' => $paths['photo'], 'photo_thumbnail_path' => $paths['thumbnail']]);

    return $paths;
}

beforeEach(function () {
    Storage::fake('local');
});

test('guests are redirected to the login page from the photo routes', function () {
    $person = Person::factory()->create();

    $this->post(route('persons.photo.store', $person), [])->assertRedirect(route('login'));
    $this->delete(route('persons.photo.destroy', $person))->assertRedirect(route('login'));
    $this->get(route('persons.photo.show', $person))->assertRedirect(route('login'));
});

test('an upload stores the original and a thumbnail under the tree folder and sets the person columns', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();

    $this->actingAs($user)
        ->post(route('persons.photo.store', $person), ['photo' => personPhotoUpload()])
        ->assertSessionHasNoErrors()
        ->assertRedirect()
        ->assertSessionHas('inertia.flash_data.toast', ['type' => 'success', 'message' => 'Zapisano zdjęcie']);

    $person->refresh();

    expect($person->photo_path)->toStartWith("persons/{$user->tree->id}/")
        ->and($person->photo_path)->not->toContain('/thumbnails/')
        ->and($person->photo_thumbnail_path)->toMatch("#^persons/{$user->tree->id}/thumbnails/[0-9a-f-]{36}\\.jpg$#")
        ->and(Storage::disk('local')->exists($person->photo_path))->toBeTrue()
        ->and(Storage::disk('local')->exists($person->photo_thumbnail_path))->toBeTrue();
});

test('a replacement removes the previous files and changes the photo version', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $previous = personPhotoStoredFor($person);
    $previousVersion = pathinfo($previous['thumbnail'], PATHINFO_FILENAME);

    $this->actingAs($user)
        ->post(route('persons.photo.store', $person), ['photo' => personPhotoUpload()])
        ->assertSessionHasNoErrors();

    $person->refresh();

    expect(Storage::disk('local')->exists($previous['photo']))->toBeFalse()
        ->and(Storage::disk('local')->exists($previous['thumbnail']))->toBeFalse()
        ->and(Storage::disk('local')->exists($person->photo_thumbnail_path))->toBeTrue()
        ->and(pathinfo($person->photo_thumbnail_path, PATHINFO_FILENAME))->not->toBe($previousVersion);
});

test('files of other types and oversized files are rejected on the photo field', function (UploadedFile $file) {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();

    $this->actingAs($user)
        ->post(route('persons.photo.store', $person), ['photo' => $file])
        ->assertSessionHasErrors('photo');

    expect($person->refresh()->photo_path)->toBeNull()
        ->and(Storage::disk('local')->allFiles())->toBeEmpty();
})->with([
    'pdf' => fn () => UploadedFile::fake()->create('dokument.pdf', 10, 'application/pdf'),
    'gif' => fn () => UploadedFile::fake()->create('animacja.gif', 10, 'image/gif'),
    'svg' => fn () => UploadedFile::fake()->create('grafika.svg', 10, 'image/svg+xml'),
    'jpg z tekstem w środku' => fn () => UploadedFile::fake()->createWithContent('tekst.jpg', 'to nie jest obraz'),
    'ponad 20 MB' => function () {
        $path = sys_get_temp_dir().'/duzy-'.bin2hex(random_bytes(4)).'.jpg';
        file_put_contents($path, file_get_contents(base_path('tests/Fixtures/portrait-small.jpg')).str_repeat("\0", 20 * 1024 * 1024));

        return new UploadedFile($path, 'duzy.jpg', 'image/jpeg', null, true);
    },
]);

test('a photo without a thumbnail is refused and leaves no files and the person unchanged', function () {
    $this->mock(GenerateSourceThumbnail::class, fn ($mock) => $mock->shouldReceive('handle')->once()->andReturnNull());

    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $previous = personPhotoStoredFor($person);

    $this->actingAs($user)
        ->post(route('persons.photo.store', $person), ['photo' => personPhotoUpload()])
        ->assertSessionHasErrors(['photo' => 'Nie udało się przetworzyć zdjęcia. Spróbuj innego pliku.']);

    $person->refresh();

    expect($person->photo_path)->toBe($previous['photo'])
        ->and($person->photo_thumbnail_path)->toBe($previous['thumbnail'])
        ->and(Storage::disk('local')->allFiles("persons/{$user->tree->id}"))->toHaveCount(2);
});

test('a photo without a thumbnail on a person without a photo leaves no columns and no files', function () {
    $this->mock(GenerateSourceThumbnail::class, fn ($mock) => $mock->shouldReceive('handle')->once()->andReturnNull());

    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();

    $this->actingAs($user)
        ->post(route('persons.photo.store', $person), ['photo' => personPhotoUpload()])
        ->assertSessionHasErrors('photo');

    expect($person->refresh()->photo_path)->toBeNull()
        ->and($person->photo_thumbnail_path)->toBeNull()
        ->and(Storage::disk('local')->allFiles())->toBeEmpty();
});

test('destroy clears the person columns and deletes both files', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $stored = personPhotoStoredFor($person);

    $this->actingAs($user)
        ->delete(route('persons.photo.destroy', $person))
        ->assertRedirect()
        ->assertSessionHas('inertia.flash_data.toast', ['type' => 'success', 'message' => 'Usunięto zdjęcie']);

    $person->refresh();

    expect($person->photo_path)->toBeNull()
        ->and($person->photo_thumbnail_path)->toBeNull()
        ->and(Storage::disk('local')->exists($stored['photo']))->toBeFalse()
        ->and(Storage::disk('local')->exists($stored['thumbnail']))->toBeFalse();
});

test('the photo route serves the thumbnail with private caching and nosniff', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $stored = personPhotoStoredFor($person);

    $response = $this->actingAs($user)->get(route('persons.photo.show', $person))->assertOk();

    expect($response->streamedContent())->toBe('thumbnail-bytes')
        ->and($response->headers->get('Content-Type'))->toBe('image/jpeg')
        ->and($response->headers->get('Cache-Control'))->toContain('private')
        ->and($response->headers->get('Cache-Control'))->toContain('max-age=86400')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($stored['thumbnail'])->toEndWith('.jpg');
});

test('the photo route is a 404 without a photo', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();

    $this->actingAs($user)->get(route('persons.photo.show', $person))->assertNotFound();
});

test('the photo route is a 404 when the thumbnail file is missing from the disk', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $stored = personPhotoStoredFor($person);
    Storage::disk('local')->delete($stored['thumbnail']);

    $this->actingAs($user)->get(route('persons.photo.show', $person))->assertNotFound();
});

test('the person resource in the preview carries the photo version', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create([
        'photo_path' => "persons/{$user->tree->id}/a.jpg",
        'photo_thumbnail_path' => "persons/{$user->tree->id}/thumbnails/wersja.jpg",
    ]);

    $this->actingAs($user)->get(personPreviewUrl($person))
        ->assertInertia(fn ($page) => $page
            ->where('personPreview.person.photo_version', 'wersja'));
});
