<?php

namespace App\Support;

use GdImage;
use RuntimeException;

/**
 * Re-encodes an uploaded patient photo with GD: applies the camera orientation (EXIF), crops the centered square,
 * scales it down to at most 512x512 (never up) and encodes a JPEG. Re-encoding drops every metadata block of the
 * original (EXIF with GPS location, camera serial, XMP, ICC...) and any payload hidden in the file.
 *
 * Input dimensions are validated before decoding (max 4000x4000, ~64 MB of GD memory at worst).
 */
final class PhotoProcessor
{
    public const MAX_SIZE = 512;

    public const QUALITY = 85;

    /**
     * @throws RuntimeException when the image cannot be decoded
     */
    public static function process(string $contents): string
    {
        $source = @imagecreatefromstring($contents);

        if (! $source instanceof GdImage) {
            throw new RuntimeException('A imagem não pôde ser processada.');
        }

        $source = self::orient($source, self::exifOrientation($contents));

        $width = imagesx($source);
        $height = imagesy($source);
        $side = min($width, $height);
        $target = min($side, self::MAX_SIZE);

        $square = imagecreatetruecolor($target, $target);

        if (! $square instanceof GdImage) {
            throw new RuntimeException('A imagem não pôde ser processada.');
        }

        // JPEG has no alpha: transparent PNG/WebP areas become white instead of black.
        imagefill($square, 0, 0, (int) imagecolorallocate($square, 255, 255, 255));
        imagecopyresampled(
            $square,
            $source,
            0,
            0,
            intdiv($width - $side, 2),
            intdiv($height - $side, 2),
            $target,
            $target,
            $side,
            $side
        );
        imageinterlace($square, true); // progressive JPEG

        ob_start();
        imagejpeg($square, null, self::QUALITY);

        return (string) ob_get_clean();
    }

    /**
     * EXIF orientation (1–8) of a JPEG; 1 when absent, unreadable, or the exif extension is missing.
     */
    private static function exifOrientation(string $contents): int
    {
        if (! function_exists('exif_read_data') || ! str_starts_with($contents, "\xFF\xD8")) {
            return 1;
        }

        $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($contents));
        $orientation = is_array($exif) ? ($exif['Orientation'] ?? 1) : 1;

        return is_int($orientation) && $orientation >= 1 && $orientation <= 8 ? $orientation : 1;
    }

    /**
     * Rotates/flips the pixels so the image displays upright once the EXIF tag is gone.
     */
    private static function orient(GdImage $image, int $orientation): GdImage
    {
        if (in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        }

        // imagerotate() turns counter-clockwise (270 = 90° clockwise). 4/5/7 are mirrored: horizontal flip above,
        // then the rotation (a vertical flip is a horizontal flip + 180°).
        $angle = match ($orientation) {
            3, 4 => 180,
            6, 7 => 270,
            5, 8 => 90,
            default => 0,
        };

        if ($angle === 0) {
            return $image;
        }

        $rotated = imagerotate($image, $angle, 0);

        return $rotated instanceof GdImage ? $rotated : $image;
    }
}
