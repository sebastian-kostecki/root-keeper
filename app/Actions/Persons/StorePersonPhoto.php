<?php

namespace App\Actions\Persons;

use App\Actions\Sources\GenerateSourceThumbnail;
use App\Models\Person;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class StorePersonPhoto
{
    public function __construct(
        private GenerateSourceThumbnail $generateSourceThumbnail,
    ) {}

    /**
     * Store the photo and its thumbnail on the private disk, point the person at them and then remove the
     * previous photo files. A photo without a thumbnail is refused with a validation error on `photo`, and
     * nothing is left on disk. Previous files are removed only after the row is updated; a failed removal is reported.
     *
     * @throws ValidationException when no thumbnail can be made from the file
     */
    public function handle(Person $person, UploadedFile $photo): void
    {
        $uuid = (string) Str::uuid();
        $extension = $photo->extension();
        $photoPath = "persons/{$person->tree_id}/{$uuid}.{$extension}";
        $thumbnailPath = "persons/{$person->tree_id}/thumbnails/{$uuid}.jpg";
        $previousPaths = array_values(array_filter([$person->photo_path, $person->photo_thumbnail_path]));

        if (Storage::disk('local')->putFileAs("persons/{$person->tree_id}", $photo, "{$uuid}.{$extension}") === false) {
            throw new RuntimeException("Unable to store person photo [{$photoPath}].");
        }

        try {
            $thumbnailContent = $this->generateSourceThumbnail->handle($photo->getRealPath(), $photo->getMimeType());

            if ($thumbnailContent === null || ! Storage::disk('local')->put($thumbnailPath, $thumbnailContent)) {
                throw ValidationException::withMessages([
                    'photo' => 'Nie udało się przetworzyć zdjęcia. Spróbuj innego pliku.',
                ]);
            }

            $person->update([
                'photo_path' => $photoPath,
                'photo_thumbnail_path' => $thumbnailPath,
            ]);
        } catch (Throwable $e) {
            Storage::disk('local')->delete([$photoPath, $thumbnailPath]);

            throw $e;
        }

        if ($previousPaths !== [] && ! Storage::disk('local')->delete($previousPaths)) {
            report(new RuntimeException('Unable to delete previous person photo file(s) ['.implode(', ', $previousPaths).'].'));
        }
    }
}
