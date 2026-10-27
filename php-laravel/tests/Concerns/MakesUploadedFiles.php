<?php

namespace Tests\Concerns;

use GdImage;
use Illuminate\Http\UploadedFile;

/**
 * Real uploaded files (actual bytes on disk). UploadedFile::fake() reports the MIME type from the file NAME,
 * which would hide type-spoofing bugs: these files are sniffed from their contents like real uploads.
 */
trait MakesUploadedFiles
{
    /** @var array<int, string> */
    private array $temporaryFiles = [];

    protected function tearDownMakesUploadedFiles(): void
    {
        foreach ($this->temporaryFiles as $path) {
            @unlink($path);
        }

        $this->temporaryFiles = [];
    }

    protected function uploadedFile(string $contents, string $clientName): UploadedFile
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'upl');
        file_put_contents($path, $contents);
        $this->temporaryFiles[] = $path;

        return new UploadedFile($path, $clientName, null, null, true);
    }

    /**
     * Solid-color image (or two halves: left $color, right $right) encoded as png|jpeg|webp.
     *
     * @param  array{int, int, int}  $color
     * @param  array{int, int, int}|null  $right
     */
    protected function imageBytes(int $width, int $height, string $format = 'png', array $color = [200, 30, 30], array $right = null): string
    {
        $image = imagecreatetruecolor($width, $height);
        $this->assertInstanceOf(GdImage::class, $image);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, ...$color));

        if ($right !== null) {
            imagefilledrectangle($image, intdiv($width, 2), 0, $width - 1, $height - 1, (int) imagecolorallocate($image, ...$right));
        }

        ob_start();
        match ($format) {
            'jpeg' => imagejpeg($image, null, 95),
            'webp' => imagewebp($image),
            default => imagepng($image),
        };

        return (string) ob_get_clean();
    }

    protected function transparentPngBytes(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        $this->assertInstanceOf(GdImage::class, $image);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, (int) imagecolorallocatealpha($image, 0, 0, 0, 127));

        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    /**
     * JPEG with an EXIF (APP1) block right after SOI: Orientation and an ImageDescription carrying $marker
     * (stands in for GPS coordinates / camera data that must not survive the upload).
     */
    protected function jpegWithExif(string $jpeg, int $orientation, string $marker): string
    {
        $description = $marker."\0";
        $entries = 2;
        $dataOffset = 8 + 2 + $entries * 12 + 4;

        $tiff = 'II'.pack('v', 42).pack('V', 8)
            .pack('v', $entries)
            .pack('vvV', 0x010E, 2, strlen($description)).pack('V', $dataOffset) // ImageDescription (ASCII)
            .pack('vvV', 0x0112, 3, 1).pack('vv', $orientation, 0)              // Orientation (SHORT)
            .pack('V', 0)
            .$description;
        $payload = "Exif\0\0".$tiff;

        return substr($jpeg, 0, 2)."\xFF\xE1".pack('n', strlen($payload) + 2).$payload.substr($jpeg, 2);
    }

    protected function pdfBytes(string $body = 'Laudo'): string
    {
        return "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\n% {$body}\ntrailer << /Root 1 0 R >>\n%%EOF\n";
    }
}
