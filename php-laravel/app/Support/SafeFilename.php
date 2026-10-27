<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Sanitizes user-supplied file names (attachments) and builds the download Content-Disposition header.
 */
final class SafeFilename
{
    private const MAX_LENGTH = 150;

    private const FALLBACK = 'anexo';

    /**
     * Base name only (no directories), without control characters or characters that are reserved in file names
     * (`\ / : * ? " < > |`, `%`), trimmed of dots/spaces and capped. The extension is forced to the one of the
     * detected type, so a PDF uploaded as "laudo.html" downloads as "laudo.html.pdf" (never opened as HTML).
     */
    public static function sanitize(string $name, string $extension): string
    {
        $name = mb_check_encoding($name, 'UTF-8') ? $name : '';
        $name = (string) Str::afterLast(str_replace('\\', '/', $name), '/');
        $name = (string) preg_replace('/\s+/u', ' ', $name); // tabs/line breaks become spaces
        $name = (string) preg_replace('/[\x00-\x1F\x7F\/\\\\:*?"<>|%]+/u', '', $name);
        $name = trim($name, ' .');

        $dot = mb_strrpos($name, '.');
        $original = $dot === false ? '' : mb_strtolower(mb_substr($name, $dot + 1));
        $matches = $original === $extension || ($extension === 'jpg' && $original === 'jpeg');

        $stem = $matches && $dot !== false ? mb_substr($name, 0, $dot) : $name;
        $stem = trim(mb_substr($stem, 0, self::MAX_LENGTH), " .\t");

        return ($stem === '' ? self::FALLBACK : $stem).'.'.($matches ? $original : $extension);
    }

    /**
     * `attachment; filename="<ASCII fallback>"; filename*=UTF-8''<percent-encoded name>` (RFC 6266 / RFC 5987).
     */
    public static function disposition(string $name): string
    {
        $fallback = (string) preg_replace('/[^A-Za-z0-9._ ()-]+/', '_', Str::ascii($name));
        // Nothing left of the stem (e.g. a name only in Japanese): "anexo.pdf", never a dot file.
        $fallback = trim($fallback) === '' || str_starts_with($fallback, '.') ? self::FALLBACK.$fallback : $fallback;

        // Always both parameters: old clients read the quoted ASCII `filename`, current ones prefer `filename*`.
        return 'attachment; filename="'.$fallback.'"; filename*=UTF-8\'\''.rawurlencode($name);
    }
}
