<?php

use App\Models\Scan;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('guests are redirected to the login page from scans routes', function () {
    $scan = Scan::factory()->create();

    $this->get(route('scans.index'))->assertRedirect(route('login'));
    $this->get(route('scans.create'))->assertRedirect(route('login'));
    $this->get(route('scans.show', $scan))->assertRedirect(route('login'));
    $this->post(route('scans.store'), [])->assertRedirect(route('login'));
    $this->get(route('scans.file', $scan))->assertRedirect(route('login'));
    $this->get(route('scans.thumbnail', $scan))->assertRedirect(route('login'));
});

test('scans index shows only the authenticated user own scans, newest first', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $older = Scan::factory()->for($user->tree)->create();
    $newer = Scan::factory()->for($user->tree)->create();
    Scan::factory()->for($otherUser->tree)->create();

    $response = $this->actingAs($user)->get(route('scans.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('scans/index')
        ->has('scans', 2)
        ->where('scans.0.id', $newer->id)
        ->where('scans.1.id', $older->id));
});

test('scans create shows the upload form', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('scans.create'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('scans/create'));
});

test('scans show returns not found for a scan from another users tree', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $scan = Scan::factory()->for($otherUser->tree)->create();

    $this->actingAs($user)->get(route('scans.show', $scan))->assertNotFound();
});

test('scans show renders the scan', function () {
    $user = User::factory()->create();
    $scan = Scan::factory()->for($user->tree)->withThumbnail()->create();

    $response = $this->actingAs($user)->get(route('scans.show', $scan));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('scans/show')
        ->where('scan.id', $scan->id)
        ->where('scan.has_thumbnail', true));
});

test('scan file returns not found for a scan from another users tree', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $scan = Scan::factory()->for($otherUser->tree)->create();
    Storage::disk('local')->put($scan->path, 'content');

    $this->actingAs($user)->get(route('scans.file', $scan))->assertNotFound();
});

test('scan thumbnail returns not found for a scan from another users tree', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $scan = Scan::factory()->for($otherUser->tree)->withThumbnail()->create();
    Storage::disk('local')->put($scan->thumbnail_path, 'content');

    $this->actingAs($user)->get(route('scans.thumbnail', $scan))->assertNotFound();
});

test('storing a jpg creates the scan, the file and its thumbnail', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('rodzinne-zdjecie.jpg', 1200, 800);

    $response = $this->actingAs($user)->post(route('scans.store'), [
        'scan' => $file,
    ]);

    $scan = Scan::firstOrFail();

    $response->assertRedirect(route('scans.show', $scan));

    expect($scan->tree_id)->toBe($user->tree->id);
    expect($scan->original_name)->toBe('rodzinne-zdjecie.jpg');
    expect($scan->mime_type)->toBe('image/jpeg');
    expect($scan->size)->toBeGreaterThan(0);
    expect($scan->width)->toBe(1200);
    expect($scan->height)->toBe(800);

    expect($scan->path)->toStartWith("scans/{$user->tree->id}/");
    expect($scan->path)->not->toContain('rodzinne-zdjecie');
    Storage::disk('local')->assertExists($scan->path);

    expect($scan->thumbnail_path)->not->toBeNull();
    Storage::disk('local')->assertExists($scan->thumbnail_path);
});

test('storing a png is accepted', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('zdjecie.png', 400, 300);

    $this->actingAs($user)->post(route('scans.store'), [
        'scan' => $file,
    ])->assertSessionHasNoErrors();

    expect(Scan::count())->toBe(1);
});

test('storing a webp is accepted', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('zdjecie.webp', 400, 300);

    $this->actingAs($user)->post(route('scans.store'), [
        'scan' => $file,
    ])->assertSessionHasNoErrors();

    expect(Scan::count())->toBe(1);
});

test('storing without a file is rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('scans.store'), [])
        ->assertSessionHasErrors('scan');
});

test('storing a file over 20mb is rejected', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->create('akt.jpg', 20481, 'image/jpeg');

    $this->actingAs($user)->post(route('scans.store'), [
        'scan' => $file,
    ])->assertSessionHasErrors('scan');
});

test('storing a pdf is rejected', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->create('akt.pdf', 10, 'application/pdf');

    $this->actingAs($user)->post(route('scans.store'), [
        'scan' => $file,
    ])->assertSessionHasErrors('scan');
});

test('storing a gif is rejected', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->create('akt.gif', 10, 'image/gif');

    $this->actingAs($user)->post(route('scans.store'), [
        'scan' => $file,
    ])->assertSessionHasErrors('scan');
});

test('storing an svg is rejected', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->create('akt.svg', 10, 'image/svg+xml');

    $this->actingAs($user)->post(route('scans.store'), [
        'scan' => $file,
    ])->assertSessionHasErrors('scan');
});

test('storing a text file renamed to jpg is rejected', function () {
    $user = User::factory()->create();

    $tmpPath = tempnam(sys_get_temp_dir(), 'scan');
    file_put_contents($tmpPath, 'this is plain text, not an image');
    $file = new UploadedFile($tmpPath, 'akt.jpg', null, null, true);

    $this->actingAs($user)->post(route('scans.store'), [
        'scan' => $file,
    ])->assertSessionHasErrors('scan');
});

test('scan validation errors are in polish', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('scans.store'), []);

    $response->assertInvalid();
    $errors = $response->getSession()->get('errors')->getBag('default');

    expect($errors->first('scan'))->toContain('wymagane');
});

test('scan file serves the original bytes with the right headers', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $scan = Scan::factory()->for($user->tree)->create([
        'path' => "scans/{$user->tree->id}/original.jpg",
        'mime_type' => 'image/jpeg',
    ]);
    Storage::disk('local')->put($scan->path, 'original-bytes');

    $response = $this->actingAs($user)->get(route('scans.file', $scan));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'image/jpeg');
    expect($response->headers->get('Cache-Control'))->toContain('private');
    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    expect($response->streamedContent())->toBe('original-bytes');
});

test('scan thumbnail returns not found when the scan has no thumbnail', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $scan = Scan::factory()->for($user->tree)->create(['thumbnail_path' => null]);

    $this->actingAs($user)->get(route('scans.thumbnail', $scan))->assertNotFound();
});

test('deleting the account removes the tree scans from disk', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $tree = $user->tree;
    Storage::disk('local')->put("scans/{$tree->id}/keepsake.jpg", 'content');

    $this->actingAs($user)->delete(route('profile.destroy'), [
        'password' => 'password',
    ]);

    Storage::disk('local')->assertDirectoryEmpty("scans/{$tree->id}");
});

test('deleting an account without a tree leaves other trees scans intact', function () {
    Storage::fake('local');

    $otherUser = User::factory()->create();
    Storage::disk('local')->put("scans/{$otherUser->tree->id}/keepsake.jpg", 'content');

    $user = User::factory()->withoutTree()->create();

    $this->actingAs($user)->delete(route('profile.destroy'), [
        'password' => 'password',
    ]);

    Storage::disk('local')->assertExists("scans/{$otherUser->tree->id}/keepsake.jpg");
});
