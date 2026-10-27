<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\PatientAudit;
use App\Support\EncryptedFiles;
use App\Support\PhotoProcessor;
use GdImage;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\MakesUploadedFiles;
use Tests\TestCase;

class PatientPhotoTest extends TestCase
{
    use MakesUploadedFiles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(EncryptedFiles::DISK);
    }

    private function upload(Patient $patient, UploadedFile $file): TestResponse
    {
        return $this->post("/api/patients/{$patient->id}/photo", ['photo' => $file], ['Accept' => 'application/json']);
    }

    private function png(int $width = 300, int $height = 200, string $name = 'foto.png'): UploadedFile
    {
        return $this->uploadedFile($this->imageBytes($width, $height), $name);
    }

    /**
     * @return array{0: int, 1: int, 2: string} width, height, mime of the stored (decrypted) photo
     */
    private function storedImage(Patient $patient): array
    {
        $patient->refresh();
        $this->assertNotNull($patient->photo_path);
        $jpeg = (string) EncryptedFiles::get($patient->photo_path);
        $info = getimagesizefromstring($jpeg);
        $this->assertIsArray($info);

        return [$info[0], $info[1], $info['mime']];
    }

    /**
     * @return array{int, int, int} RGB of a pixel of the stored photo
     */
    private function storedPixel(Patient $patient, int $x, int $y): array
    {
        $image = imagecreatefromstring((string) EncryptedFiles::get((string) $patient->refresh()->photo_path));
        $this->assertInstanceOf(GdImage::class, $image);
        $rgb = (int) imagecolorat($image, $x, $y);

        return [($rgb >> 16) & 0xFF, ($rgb >> 8) & 0xFF, $rgb & 0xFF];
    }

    public function test_upload_reprocesses_to_a_centered_square_jpeg_and_returns_the_photo_url(): void
    {
        $patient = Patient::factory()->create();

        $this->upload($patient, $this->png(1000, 600))
            ->assertOk()
            ->assertJsonPath('data.id', $patient->id)
            ->assertJsonPath('data.photo_url', url("/api/patients/{$patient->id}/photo?v=1"));

        $this->assertSame([512, 512, 'image/jpeg'], $this->storedImage($patient));
        $this->assertSame(1, $patient->photo_version);
        $this->assertMatchesRegularExpression('#^photos/[A-Za-z0-9]{40}\.bin$#', (string) $patient->photo_path);

        // Small images are cropped but never upscaled.
        $this->upload($patient, $this->png(150, 120))->assertOk()->assertJsonPath('data.photo_url', url("/api/patients/{$patient->id}/photo?v=2"));
        $this->assertSame([120, 120, 'image/jpeg'], $this->storedImage($patient));
    }

    public function test_type_is_checked_by_content_not_by_name(): void
    {
        $patient = Patient::factory()->create();

        // A real PNG with a .jpg name is fine...
        $this->upload($patient, $this->png(200, 200, 'foto.jpg'))->assertOk();

        // ...text, SVG or a PDF renamed to .jpg are not.
        foreach ([
            'texto.jpg' => str_repeat('apenas texto ', 50),
            'desenho.jpg' => '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200"><rect width="200" height="200"/></svg>',
            'laudo.jpg' => $this->pdfBytes(),
        ] as $name => $contents) {
            $this->upload($patient, $this->uploadedFile($contents, $name))
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['photo' => 'A foto deve ser uma imagem JPEG, PNG ou WebP.']);
        }
    }

    public function test_jpeg_and_webp_inputs_are_accepted(): void
    {
        $patient = Patient::factory()->create();

        $this->upload($patient, $this->uploadedFile($this->imageBytes(200, 200, 'jpeg'), 'foto.jpeg'))->assertOk();

        if (! function_exists('imagewebp') || ! (gd_info()['WebP Support'] ?? false)) {
            $this->markTestSkipped('GD without WebP support.');
        }

        $this->upload($patient, $this->uploadedFile($this->imageBytes(220, 220, 'webp'), 'foto.webp'))->assertOk();
        $this->assertSame([220, 220, 'image/jpeg'], $this->storedImage($patient));
    }

    public function test_size_dimensions_and_presence_are_validated(): void
    {
        $patient = Patient::factory()->create();

        // > 2 MB: a valid PNG padded with trailing bytes.
        $big = $this->uploadedFile($this->imageBytes(200, 200).str_repeat("\0", 2 * 1024 * 1024 + 1), 'grande.png');
        $this->upload($patient, $big)->assertUnprocessable()->assertJsonValidationErrors(['photo' => 'A foto não pode ter mais de 2 MB.']);

        foreach ([[99, 150], [150, 99], [4001, 120]] as [$width, $height]) {
            $this->upload($patient, $this->png($width, $height))
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['photo' => 'A foto deve ter entre 100x100 e 4000x4000 pixels.']);
        }

        $this->post("/api/patients/{$patient->id}/photo", [], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['photo' => 'Envie uma foto.']);

        $this->post("/api/patients/{$patient->id}/photo", ['photo' => 'texto'], ['Accept' => 'application/json'])
            ->assertJsonValidationErrors(['photo' => 'O campo foto deve ser um arquivo.']);

        $this->assertNull($patient->refresh()->photo_path);
        $this->assertSame([], Storage::disk(EncryptedFiles::DISK)->allFiles());
    }

    public function test_image_that_cannot_be_decoded_is_rejected(): void
    {
        $patient = Patient::factory()->create();
        // PNG signature + IHDR only: sniffed as image/png and has dimensions, but there is no pixel data.
        $truncated = substr($this->imageBytes(200, 200), 0, 33);

        $this->upload($patient, $this->uploadedFile($truncated, 'quebrada.png'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['photo' => 'A foto não pôde ser processada. Envie outra imagem.']);
    }

    public function test_exif_metadata_is_stripped_and_orientation_applied(): void
    {
        $patient = Patient::factory()->create();
        // Left half red, right half blue; orientation 6 = "rotate 90° clockwise to display".
        $jpeg = $this->jpegWithExif($this->imageBytes(200, 200, 'jpeg', [255, 0, 0], [0, 0, 255]), 6, 'GPS-SECRET-LOCATION');
        $this->assertStringContainsString('GPS-SECRET-LOCATION', $jpeg);

        $this->upload($patient, $this->uploadedFile($jpeg, 'celular.jpg'))->assertOk();

        $stored = (string) EncryptedFiles::get((string) $patient->refresh()->photo_path);
        $this->assertStringNotContainsString('GPS-SECRET-LOCATION', $stored);
        $this->assertStringNotContainsString("Exif\0\0", $stored);
        $this->assertSame([200, 200, 'image/jpeg'], $this->storedImage($patient));

        if (! function_exists('exif_read_data')) {
            $this->markTestSkipped('exif extension not available: orientation is not applied.');
        }

        // After rotating clockwise the left (red) half is on top.
        [$topRed, , $topBlue] = $this->storedPixel($patient, 100, 20);
        [$bottomRed, , $bottomBlue] = $this->storedPixel($patient, 100, 180);
        $this->assertGreaterThan(200, $topRed);
        $this->assertLessThan(60, $topBlue);
        $this->assertGreaterThan(200, $bottomBlue);
        $this->assertLessThan(60, $bottomRed);
    }

    public function test_transparent_areas_become_white(): void
    {
        $patient = Patient::factory()->create();

        $this->upload($patient, $this->uploadedFile($this->transparentPngBytes(120, 120), 'logo.png'))->assertOk();

        foreach ($this->storedPixel($patient, 60, 60) as $channel) {
            $this->assertGreaterThan(245, $channel);
        }
    }

    public function test_photo_is_encrypted_at_rest_and_served_with_cache_headers(): void
    {
        $patient = Patient::factory()->create();
        $this->upload($patient, $this->png())->assertOk();
        $path = (string) $patient->refresh()->photo_path;

        $raw = (string) Storage::disk(EncryptedFiles::DISK)->get($path);
        $this->assertStringStartsNotWith("\xFF\xD8", $raw);
        $this->assertStringNotContainsString('JFIF', $raw);
        $jpeg = Crypt::decryptString($raw);
        $this->assertStringStartsWith("\xFF\xD8", $jpeg);

        $response = $this->get("/api/patients/{$patient->id}/photo?v=1")->assertOk();
        $response->assertHeader('Content-Type', 'image/jpeg');
        $response->assertHeader('Content-Length', (string) strlen($jpeg));
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('max-age=86400', (string) $response->headers->get('Cache-Control'));
        $this->assertSame($jpeg, $response->streamedContent());
    }

    public function test_replacing_and_removing_the_photo_deletes_files_and_is_audited(): void
    {
        $patient = Patient::factory()->create();
        $disk = Storage::disk(EncryptedFiles::DISK);

        $this->upload($patient, $this->png())->assertOk();
        $first = (string) $patient->refresh()->photo_path;

        $this->upload($patient, $this->png(400, 400))->assertOk();
        $second = (string) $patient->refresh()->photo_path;
        $this->assertNotSame($first, $second);
        $this->assertFalse($disk->exists($first));
        $this->assertTrue($disk->exists($second));

        $this->delete("/api/patients/{$patient->id}/photo")->assertNoContent();
        $this->assertFalse($disk->exists($second));
        $this->assertNull($patient->refresh()->photo_path);
        $this->assertSame(3, $patient->photo_version);
        $this->getJson("/api/patients/{$patient->id}")->assertOk()->assertJsonPath('data.photo_url', null);

        $photoChanges = PatientAudit::query()
            ->where('patient_id', $patient->id)
            ->where('event', PatientAudit::UPDATED)
            ->orderBy('id')
            ->get()
            ->map(fn (PatientAudit $audit) => $audit->changes)
            ->all();

        $this->assertSame([
            ['photo' => ['old' => null, 'new' => 'adicionada']],
            ['photo' => ['old' => null, 'new' => 'alterada']],
            ['photo' => ['old' => null, 'new' => 'removida']],
        ], $photoChanges);
    }

    public function test_missing_photo_is_404(): void
    {
        $patient = Patient::factory()->create();

        $this->getJson("/api/patients/{$patient->id}/photo")->assertNotFound()->assertExactJson(['message' => 'Foto não encontrada.']);
        $this->deleteJson("/api/patients/{$patient->id}/photo")->assertNotFound()->assertExactJson(['message' => 'Foto não encontrada.']);

        // Row pointing to a file that is gone (e.g. lost volume): still a clean 404.
        $patient->forceFill(['photo_path' => 'photos/missing.bin', 'photo_version' => 1])->save();
        $this->getJson("/api/patients/{$patient->id}/photo")->assertNotFound();

        $this->getJson('/api/patients/999999/photo')->assertNotFound()->assertExactJson(['message' => 'Paciente não encontrado.']);
    }

    public function test_photo_reads_use_their_own_rate_limit(): void
    {
        RateLimiter::for('api', fn () => Limit::perMinute(1));
        $patient = Patient::factory()->create();

        $this->getJson('/api/patients')->assertOk();
        $this->getJson('/api/patients')->assertStatus(429);

        // Avatars keep loading after the API budget is spent (404 here: the patient has no photo).
        for ($i = 0; $i < 3; $i++) {
            $this->getJson("/api/patients/{$patient->id}/photo")->assertNotFound();
        }

        RateLimiter::for('photos', fn () => Limit::perMinute(1));
        $this->getJson("/api/patients/{$patient->id}/photo")->assertNotFound();
        $this->getJson("/api/patients/{$patient->id}/photo")->assertStatus(429);
    }

    public function test_trashed_patient_photo_routes_are_404(): void
    {
        $patient = Patient::factory()->create();
        $this->upload($patient, $this->png())->assertOk();
        $patient->refresh()->delete();

        $this->getJson("/api/patients/{$patient->id}/photo")->assertNotFound()->assertExactJson(['message' => 'Paciente não encontrado.']);
        $this->upload($patient, $this->png())->assertNotFound();
        $this->deleteJson("/api/patients/{$patient->id}/photo")->assertNotFound();

        // Soft delete keeps the file (the patient can be restored).
        $this->assertTrue(Storage::disk(EncryptedFiles::DISK)->exists((string) $patient->photo_path));
    }

    public function test_processor_never_upscales_and_outputs_jpeg(): void
    {
        $jpeg = PhotoProcessor::process($this->imageBytes(100, 300));
        $info = getimagesizefromstring($jpeg);

        $this->assertIsArray($info);
        $this->assertSame([100, 100, 'image/jpeg'], [$info[0], $info[1], $info['mime']]);
    }
}
