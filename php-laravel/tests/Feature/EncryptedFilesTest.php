<?php

namespace Tests\Feature;

use App\Support\EncryptedFiles;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Runs against a real local directory (not Storage::fake) to prove what actually lands on disk.
 */
class EncryptedFilesTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = storage_path('framework/testing/encrypted-files-'.bin2hex(random_bytes(4)));
        config(['filesystems.disks.private.root' => $this->root]);
        Storage::forgetDisk(EncryptedFiles::DISK);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        Storage::forgetDisk(EncryptedFiles::DISK);

        parent::tearDown();
    }

    public function test_contents_are_encrypted_on_the_real_disk_under_a_random_name(): void
    {
        $plain = "%PDF-1.4\nLaudo de Maria da Silva, CPF 625.189.530-60\n%%EOF";

        $path = EncryptedFiles::put(EncryptedFiles::ATTACHMENTS, $plain);

        $this->assertMatchesRegularExpression('#^attachments/[A-Za-z0-9]{40}\.bin$#', $path);
        $onDisk = (string) file_get_contents($this->root.'/'.$path);
        $this->assertStringNotContainsString('%PDF', $onDisk);
        $this->assertStringNotContainsString('Maria', $onDisk);
        $this->assertStringNotContainsString('625.189.530-60', $onDisk);
        $this->assertSame($plain, Crypt::decryptString($onDisk));

        $this->assertTrue(EncryptedFiles::exists($path));
        $this->assertSame($plain, EncryptedFiles::get($path));

        $response = EncryptedFiles::response($path, ['Content-Type' => 'application/pdf']);
        $this->assertNotNull($response);
        $this->assertSame((string) strlen($plain), $response->headers->get('Content-Length'));
        ob_start();
        $response->sendContent();
        $this->assertSame($plain, ob_get_clean());

        EncryptedFiles::delete($path);
        $this->assertFileDoesNotExist($this->root.'/'.$path);
        $this->assertFalse(EncryptedFiles::exists($path));
        $this->assertNull(EncryptedFiles::get($path));
        $this->assertNull(EncryptedFiles::response($path, []));
        EncryptedFiles::delete($path); // missing files are ignored
        EncryptedFiles::delete(null);
        $this->assertFalse(EncryptedFiles::exists(null));
    }

    public function test_large_files_are_streamed_in_chunks(): void
    {
        $plain = random_bytes(2 * 1024 * 1024 + 123);
        $path = EncryptedFiles::put(EncryptedFiles::ATTACHMENTS, $plain);

        $response = EncryptedFiles::response($path, []);
        $this->assertNotNull($response);
        ob_start();
        $response->sendContent();
        $this->assertSame($plain, ob_get_clean());
    }

    public function test_rotating_app_key_makes_files_unreadable(): void
    {
        $path = EncryptedFiles::put(EncryptedFiles::PHOTOS, 'jpeg bytes');

        $this->app->instance('encrypter', new Encrypter(Encrypter::generateKey('aes-256-cbc'), 'aes-256-cbc'));
        Crypt::clearResolvedInstance('encrypter');

        $this->expectException(DecryptException::class);
        EncryptedFiles::get($path);
    }
}
