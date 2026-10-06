<?php

namespace App\Actions\Sources;

use App\Enums\SourceKind;
use App\Models\Source;
use App\Models\Tree;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class StoreSource
{
    public function __construct(
        private GenerateSourceThumbnail $generateSourceThumbnail,
        private ReadExifOrientation $readExifOrientation,
        private FillBurialPlacesFromGravestone $fillBurialPlacesFromGravestone,
    ) {}

    /**
     * Store the uploaded source (and, when possible, a thumbnail) on the private
     * disk and create its row. Leaves no orphan files if anything fails once the
     * original is written. A gravestone's buried people are linked and their empty
     * burial places filled in the same transaction as the row.
     *
     * @param  array{name?: string|null, record_type?: string|null, parish_id?: int|null, cemetery_locality_id?: int|null, buried_person_ids?: list<int>|null, year?: int|null, archive?: string|null, url?: string|null, note?: string|null}  $description
     */
    public function handle(Tree $tree, UploadedFile $file, SourceKind $kind, array $description = []): Source
    {
        $uuid = (string) Str::uuid();
        $extension = $file->extension();
        $mimeType = $file->getMimeType();
        $path = "sources/{$tree->id}/{$uuid}.{$extension}";

        if (Storage::disk('local')->putFileAs("sources/{$tree->id}", $file, "{$uuid}.{$extension}") === false) {
            throw new RuntimeException("Unable to store source file [{$path}].");
        }

        $thumbnailPath = null;

        try {
            [$width, $height] = $this->dimensions($file->getRealPath(), $mimeType);

            $thumbnailContent = $this->generateSourceThumbnail->handle($file->getRealPath(), $mimeType);

            if ($thumbnailContent !== null) {
                $thumbnailPath = "sources/{$tree->id}/thumbnails/{$uuid}.jpg";

                if (! Storage::disk('local')->put($thumbnailPath, $thumbnailContent)) {
                    $thumbnailPath = null;
                }
            }

            $buriedPersonIds = $description['buried_person_ids'] ?? [];
            unset($description['buried_person_ids']);

            return DB::transaction(function () use ($tree, $description, $kind, $file, $extension, $path, $thumbnailPath, $mimeType, $width, $height, $buriedPersonIds): Source {
                $source = $tree->sources()->create([
                    ...$description,
                    'kind' => $kind,
                    'original_name' => $this->sanitizeOriginalName($file->getClientOriginalName(), $extension),
                    'path' => $path,
                    'thumbnail_path' => $thumbnailPath,
                    'mime_type' => $mimeType,
                    'size' => $file->getSize(),
                    'width' => $width,
                    'height' => $height,
                ]);

                if ($kind === SourceKind::Gravestone) {
                    $source->buriedPeople()->sync($buriedPersonIds);
                }

                $this->fillBurialPlacesFromGravestone->handle($source);

                return $source;
            });
        } catch (Throwable $e) {
            Storage::disk('local')->delete(array_filter([$path, $thumbnailPath]));

            throw $e;
        }
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    private function dimensions(string $absolutePath, string $mimeType): array
    {
        $size = @getimagesize($absolutePath);

        if ($size === false) {
            return [null, null];
        }

        [$width, $height] = $size;

        $orientation = $this->readExifOrientation->handle($absolutePath, $mimeType);

        if ($this->readExifOrientation->swapsDimensions($orientation)) {
            return [$height, $width];
        }

        return [$width, $height];
    }

    private function sanitizeOriginalName(string $name, string $extension): string
    {
        $sanitized = preg_replace('/[\x00-\x1F\x7F\/\\\\]/', '', $name) ?? '';
        $sanitized = Str::limit($sanitized, 255, '');

        return $sanitized === '' ? "zrodlo.{$extension}" : $sanitized;
    }
}
