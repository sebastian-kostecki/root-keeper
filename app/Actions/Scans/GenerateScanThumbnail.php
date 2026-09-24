<?php

namespace App\Actions\Scans;

use GdImage;
use Throwable;

class GenerateScanThumbnail
{
    private const TARGET_WIDTH = 400;

    private const JPEG_QUALITY = 80;

    public function __construct(
        private int $maxPixels = 25_000_000,
        private ReadExifOrientation $readExifOrientation = new ReadExifOrientation,
    ) {}

    /**
     * Generate a ~400px-wide, correctly oriented JPEG thumbnail for the given image.
     *
     * Returns null (never throws) when the image is too large, cannot be decoded,
     * or anything else goes wrong, so a thumbnail failure never breaks the upload.
     */
    public function handle(string $sourceAbsolutePath, string $mimeType): ?string
    {
        try {
            $size = getimagesize($sourceAbsolutePath);

            if ($size === false) {
                return null;
            }

            [$width, $height] = $size;

            if ($width * $height > $this->maxPixels) {
                return null;
            }

            $image = $this->decode($sourceAbsolutePath, $mimeType);

            if (! $image instanceof GdImage) {
                return null;
            }

            $orientation = $this->readExifOrientation->handle($sourceAbsolutePath, $mimeType);

            $targetDimension = $this->readExifOrientation->swapsDimensions($orientation) ? $height : $width;
            $finalWidth = min(self::TARGET_WIDTH, $targetDimension);
            $scale = $finalWidth / $targetDimension;

            $newWidth = max(1, (int) round($width * $scale));
            $newHeight = max(1, (int) round($height * $scale));

            $scaled = imagescale($image, $newWidth, $newHeight);
            unset($image);

            if (! $scaled instanceof GdImage) {
                return null;
            }

            $rotated = $this->applyOrientation($scaled, $orientation);

            if (in_array($mimeType, ['image/png', 'image/webp'], true)) {
                $rotated = $this->flattenToWhite($rotated);
            }

            ob_start();
            imagejpeg($rotated, null, self::JPEG_QUALITY);
            $content = ob_get_clean();
            unset($rotated);

            return $content !== false && $content !== '' ? $content : null;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    private function decode(string $path, string $mimeType): GdImage|false
    {
        return match ($mimeType) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
            default => false,
        };
    }

    /**
     * Turn the (already scaled) image upright. Orientations 2, 4, 5 and 7 are mirrored.
     */
    private function applyOrientation(GdImage $image, ?int $orientation): GdImage
    {
        $rotated = match ($orientation) {
            3, 4 => imagerotate($image, 180, 0),
            5, 6 => imagerotate($image, -90, 0),
            7, 8 => imagerotate($image, 90, 0),
            default => $image,
        };

        if (! $rotated instanceof GdImage) {
            return $image;
        }

        if (in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($rotated, IMG_FLIP_HORIZONTAL);
        }

        return $rotated;
    }

    private function flattenToWhite(GdImage $image): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);

        $background = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($background, 255, 255, 255);
        imagefilledrectangle($background, 0, 0, $width, $height, $white !== false ? $white : 0xFFFFFF);

        imagealphablending($background, true);
        imagesavealpha($background, false);
        imagecopy($background, $image, 0, 0, 0, 0, $width, $height);

        return $background;
    }
}
