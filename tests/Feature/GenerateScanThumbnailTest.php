<?php

use App\Actions\Scans\GenerateScanThumbnail;
use Illuminate\Http\UploadedFile;

test('generates a 400px wide jpeg thumbnail from a jpeg scan', function () {
    $file = UploadedFile::fake()->image('zdjecie.jpg', 1200, 800);

    $thumbnail = (new GenerateScanThumbnail)->handle($file->getRealPath(), 'image/jpeg');

    expect($thumbnail)->not->toBeNull();

    $size = getimagesizefromstring($thumbnail);
    expect($size[0])->toBe(400);
    expect($size['mime'])->toBe('image/jpeg');
});

test('never upscales a small image', function () {
    $file = UploadedFile::fake()->image('zdjecie.jpg', 100, 50);

    $thumbnail = (new GenerateScanThumbnail)->handle($file->getRealPath(), 'image/jpeg');

    $size = getimagesizefromstring($thumbnail);
    expect($size[0])->toBe(100);
});

test('fills png transparency with white', function () {
    $file = UploadedFile::fake()->image('zdjecie.png', 800, 600);

    $thumbnail = (new GenerateScanThumbnail)->handle($file->getRealPath(), 'image/png');

    expect($thumbnail)->not->toBeNull();
    $size = getimagesizefromstring($thumbnail);
    expect($size['mime'])->toBe('image/jpeg');
});

test('accepts a webp scan', function () {
    $file = UploadedFile::fake()->image('zdjecie.webp', 800, 600);

    $thumbnail = (new GenerateScanThumbnail)->handle($file->getRealPath(), 'image/webp');

    expect($thumbnail)->not->toBeNull();
});

test('skips the thumbnail when the image exceeds the pixel cap', function () {
    $file = UploadedFile::fake()->image('zdjecie.jpg', 50, 50);

    $thumbnail = (new GenerateScanThumbnail(maxPixels: 100))->handle($file->getRealPath(), 'image/jpeg');

    expect($thumbnail)->toBeNull();
});

test('returns null for an undecodable file instead of throwing', function () {
    $path = tempnam(sys_get_temp_dir(), 'scan');
    file_put_contents($path, 'not an image');

    $thumbnail = (new GenerateScanThumbnail)->handle($path, 'image/jpeg');

    expect($thumbnail)->toBeNull();

    unlink($path);
});

test('an exif-rotated jpeg yields a portrait thumbnail', function () {
    $path = base_path('tests/Fixtures/rotated-exif.jpg');

    $thumbnail = (new GenerateScanThumbnail)->handle($path, 'image/jpeg');

    expect($thumbnail)->not->toBeNull();

    $size = getimagesizefromstring($thumbnail);
    expect($size[0])->toBe(400);
    expect($size[1])->toBeGreaterThan($size[0]);
});
