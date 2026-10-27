<?php
/*
 * Regenerates the image fixtures of the photo/attachment specs (needs PHP with GD, e.g. the dev API image):
 *
 *   docker run --rm --entrypoint php -v "$PWD/e2e/fixtures:/out" -w /out lovel-eyecare-api generate.php
 *
 * - tiny-50x50.png      below the 100x100 minimum (server 422)
 * - wide-900x600.png    non-square, larger than 512: the served photo must be exactly 512x512
 * - exif-gps.jpg        300x300 JPEG with an EXIF APP1 (Make "E2ECam" + GPS latitude/longitude):
 *                       the served photo must carry no EXIF at all
 */

function gradient(int $w, int $h): GdImage
{
    $img = imagecreatetruecolor($w, $h);
    for ($x = 0; $x < $w; $x++) {
        for ($y = 0; $y < $h; $y += 4) {
            $c = imagecolorallocate($img, (int) (255 * $x / $w), (int) (255 * $y / $h), 128);
            imagefilledrectangle($img, $x, $y, $x, $y + 3, $c);
        }
    }
    return $img;
}

imagepng(gradient(50, 50), 'tiny-50x50.png');
imagepng(gradient(900, 600), 'wide-900x600.png');

ob_start();
imagejpeg(gradient(300, 300), null, 85);
$jpeg = ob_get_clean();

// TIFF (little endian): IFD0 = Make + GPSInfo pointer; GPS IFD = S/W reference + latitude/longitude.
$le16 = fn (int $v) => pack('v', $v);
$le32 = fn (int $v) => pack('V', $v);
$entry = fn (int $tag, int $type, int $count, string $value) => $le16($tag).$le16($type).$le32($count).str_pad($value, 4, "\0");
$rational = fn (array $pairs) => implode('', array_map(fn ($p) => $le32($p[0]).$le32($p[1]), $pairs));

$make = "E2ECam\0";                       // 7 bytes at offset 38 (+1 pad)
$tiff = "II".$le16(42).$le32(8);          // header, IFD0 at 8
$tiff .= $le16(2)
    .$entry(0x010F, 2, strlen($make), $le32(38))   // Make (ASCII)
    .$entry(0x8825, 4, 1, $le32(46))               // GPSInfo IFD pointer
    .$le32(0);                                      // no next IFD -> ends at 38
$tiff .= $make."\0";                                // -> 46
$tiff .= $le16(4)
    .$entry(0x0001, 2, 2, "S\0")                    // GPSLatitudeRef
    .$entry(0x0002, 5, 3, $le32(100))               // GPSLatitude
    .$entry(0x0003, 2, 2, "W\0")                    // GPSLongitudeRef
    .$entry(0x0004, 5, 3, $le32(124))               // GPSLongitude
    .$le32(0);                                      // -> 100
$tiff .= $rational([[23, 1], [33, 1], [1234, 100]]);  // 23° 33' 12.34" S
$tiff .= $rational([[46, 1], [39, 1], [5678, 100]]);  // 46° 39' 56.78" W

$app1 = "Exif\0\0".$tiff;
$segment = "\xFF\xE1".pack('n', strlen($app1) + 2).$app1;
// Right after SOI (FFD8).
file_put_contents('exif-gps.jpg', substr($jpeg, 0, 2).$segment.substr($jpeg, 2));

$exif = exif_read_data('exif-gps.jpg', null, true);
if (($exif['IFD0']['Make'] ?? null) !== 'E2ECam' || ($exif['GPS']['GPSLatitudeRef'] ?? null) !== 'S') {
    fwrite(STDERR, "EXIF not readable\n");
    exit(1);
}
echo "ok\n";
