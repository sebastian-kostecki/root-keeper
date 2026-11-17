<?php

namespace App\Actions\Sources;

use App\Models\Tree;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class StoreSourceFile
{
    public function __construct(
        private GenerateSourceThumbnail $generateSourceThumbnail,
        private ReadExifOrientation $readExifOrientation,
    ) {}

    /**
     * Store a scan (and, when possible, its thumbnail) on the private disk and read its
     * dimensions (EXIF-oriented). The source file of a `File` stays where it is. Leaves
     * no orphan files if anything fails after the original is written.
     *
     * @return array{path: string, thumbnail_path: string|null, original_name: string, mime_type: string, size: int, width: int|null, height: int|null}
     */
    public function handle(Tree $tree, UploadedFile|File $file): array
    {
        $uuid = (string) Str::uuid();
        $extension = $file->extension();
        $mimeType = (string) $file->getMimeType();
        $path = "sources/{$tree->id}/{$uuid}.{$extension}";

        if (Storage::disk('local')->putFileAs("sources/{$tree->id}", $file, "{$uuid}.{$extension}") === false) {
            throw new RuntimeException("Unable to store source file [{$path}].");
        }

        $thumbnailPath = null;

        try {
            [$width, $height] = $this->dimensions((string) $file->getRealPath(), $mimeType);

            $thumbnailContent = $this->generateSourceThumbnail->handle((string) $file->getRealPath(), $mimeType);

            if ($thumbnailContent !== null) {
                $thumbnailPath = "sources/{$tree->id}/thumbnails/{$uuid}.jpg";

                if (! Storage::disk('local')->put($thumbnailPath, $thumbnailContent)) {
                    $thumbnailPath = null;
                }
            }

            return [
                'path' => $path,
                'thumbnail_path' => $thumbnailPath,
                'original_name' => $this->sanitizeOriginalName($this->originalName($file), $extension),
                'mime_type' => $mimeType,
                'size' => (int) $file->getSize(),
                'width' => $width,
                'height' => $height,
            ];
        } catch (Throwable $e) {
            Storage::disk('local')->delete(array_filter([$path, $thumbnailPath]));

            throw $e;
        }
    }

    private function originalName(UploadedFile|File $file): string
    {
        return $file instanceof UploadedFile ? $file->getClientOriginalName() : $file->getFilename();
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
