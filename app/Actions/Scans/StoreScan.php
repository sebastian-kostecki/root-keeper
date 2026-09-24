<?php

namespace App\Actions\Scans;

use App\Models\Scan;
use App\Models\Tree;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class StoreScan
{
    public function __construct(
        private GenerateScanThumbnail $generateScanThumbnail,
        private ReadExifOrientation $readExifOrientation,
    ) {}

    /**
     * Store the uploaded scan (and, when possible, a thumbnail) on the private
     * disk and create its row. Leaves no orphan files if creating the row fails.
     */
    public function handle(Tree $tree, UploadedFile $file): Scan
    {
        $uuid = (string) Str::uuid();
        $extension = $file->extension();
        $mimeType = $file->getMimeType();
        $path = "scans/{$tree->id}/{$uuid}.{$extension}";

        if (Storage::disk('local')->putFileAs("scans/{$tree->id}", $file, "{$uuid}.{$extension}") === false) {
            throw new RuntimeException("Unable to store scan file [{$path}].");
        }

        [$width, $height] = $this->dimensions($file->getRealPath(), $mimeType);

        $thumbnailPath = null;
        $thumbnailContent = $this->generateScanThumbnail->handle($file->getRealPath(), $mimeType);

        if ($thumbnailContent !== null) {
            $thumbnailPath = "scans/{$tree->id}/thumbnails/{$uuid}.jpg";

            if (! Storage::disk('local')->put($thumbnailPath, $thumbnailContent)) {
                $thumbnailPath = null;
            }
        }

        try {
            return $tree->scans()->create([
                'original_name' => $this->sanitizeOriginalName($file->getClientOriginalName(), $extension),
                'path' => $path,
                'thumbnail_path' => $thumbnailPath,
                'mime_type' => $mimeType,
                'size' => $file->getSize(),
                'width' => $width,
                'height' => $height,
            ]);
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

        return $sanitized === '' ? "skan.{$extension}" : $sanitized;
    }
}
