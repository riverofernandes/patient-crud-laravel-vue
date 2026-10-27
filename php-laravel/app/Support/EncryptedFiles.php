<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Encrypted file storage on the private disk (storage/app/private, outside public/): patient photos and exam
 * attachments.
 *
 * - Contents are encrypted with Laravel's Crypt (AES-256-CBC + HMAC, APP_KEY). Rotating APP_KEY therefore makes
 *   the stored files unreadable too, exactly like the encrypted columns: decrypt and re-encrypt them with the new
 *   key before switching (see config/patients.php).
 * - File names are random (`<directory>/<40 random chars>.bin`): nothing on disk identifies the patient, the
 *   original name or the type.
 * - Memory and size trade-off: Crypt is not a streaming cipher, so a file is encrypted/decrypted as a whole in
 *   memory. The payload is base64 twice (ciphertext, then the JSON envelope): on disk a file takes ~1.78x its
 *   size. Measured on the prod image for a 10 MB file: ~45 MB extra peak to encrypt and ~37 MB to decrypt (on top
 *   of the 10 MB plain / 17.8 MB encrypted copies), i.e. roughly 60–70 MB per upload/download request — fine for
 *   memory_limit=256M, but count it when sizing php-fpm pm.max_children. That is why uploads are capped (10 MB
 *   for attachments, 2 MB for photos); responses are still streamed to the client in 1 MB chunks. Larger files
 *   would need a chunked cipher (e.g. libsodium secretstream) or an object store with server-side encryption.
 */
final class EncryptedFiles
{
    public const DISK = 'private';

    public const PHOTOS = 'photos';

    public const ATTACHMENTS = 'attachments';

    private const CHUNK = 1024 * 1024;

    /**
     * Encrypts and stores $contents under $directory; returns the (random) path on the private disk.
     */
    public static function put(string $directory, string $contents): string
    {
        $path = $directory.'/'.Str::random(40).'.bin';

        self::disk()->put($path, Crypt::encryptString($contents));

        return $path;
    }

    /**
     * Decrypted contents, or null when the file does not exist.
     *
     * @throws \Illuminate\Contracts\Encryption\DecryptException when the file was not encrypted with APP_KEY
     */
    public static function get(string $path): ?string
    {
        $disk = self::disk();

        if (! $disk->exists($path)) {
            return null;
        }

        return Crypt::decryptString((string) $disk->get($path));
    }

    public static function exists(?string $path): bool
    {
        return $path !== null && $path !== '' && self::disk()->exists($path);
    }

    /**
     * Removes the file (missing files are ignored).
     */
    public static function delete(?string $path): void
    {
        if ($path !== null && $path !== '') {
            self::disk()->delete($path);
        }
    }

    /**
     * Response streaming the decrypted contents in chunks (decrypted before the headers are sent, so a missing
     * key or a corrupted file fails with a clean 500 instead of a truncated body). Null when the file is missing.
     *
     * @param  array<string, string>  $headers
     */
    public static function response(string $path, array $headers): ?StreamedResponse
    {
        $contents = self::get($path);

        if ($contents === null) {
            return null;
        }

        return new StreamedResponse(function () use ($contents) {
            $length = strlen($contents);

            for ($offset = 0; $offset < $length; $offset += self::CHUNK) {
                echo substr($contents, $offset, self::CHUNK);
                flush();
            }
        }, 200, $headers + [
            'Content-Length' => (string) strlen($contents),
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public static function disk(): Filesystem
    {
        return Storage::disk(self::DISK);
    }
}
