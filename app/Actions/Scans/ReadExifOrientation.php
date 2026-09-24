<?php

namespace App\Actions\Scans;

class ReadExifOrientation
{
    /**
     * Read the EXIF Orientation tag (1–8) of a JPEG scan, or null when it is
     * absent, unreadable, or the image is not a JPEG.
     */
    public function handle(string $absolutePath, string $mimeType): ?int
    {
        if ($mimeType !== 'image/jpeg' || ! function_exists('exif_read_data')) {
            return null;
        }

        $exif = @exif_read_data($absolutePath);

        if ($exif === false || ! isset($exif['Orientation'])) {
            return null;
        }

        return (int) $exif['Orientation'];
    }

    /**
     * Whether the orientation turns the image by 90°, so its upright width is the stored height.
     */
    public function swapsDimensions(?int $orientation): bool
    {
        return in_array($orientation, [5, 6, 7, 8], true);
    }
}
