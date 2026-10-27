<?php

namespace Tests\Feature;

use App\Models\Consultation;
use App\Models\ConsultationAttachment;
use App\Models\Patient;
use App\Support\EncryptedFiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\MakesUploadedFiles;
use Tests\TestCase;

class ConsultationAttachmentTest extends TestCase
{
    use MakesUploadedFiles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(EncryptedFiles::DISK);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function upload(Consultation $consultation, UploadedFile $file, array $extra = []): TestResponse
    {
        return $this->post(
            "/api/consultations/{$consultation->id}/attachments",
            ['file' => $file] + $extra,
            ['Accept' => 'application/json']
        );
    }

    private function storedAttachment(Consultation $consultation, string $name = 'laudo.pdf'): ConsultationAttachment
    {
        $id = $this->upload($consultation, $this->uploadedFile($this->pdfBytes(), $name))->assertCreated()->json('data.id');

        return ConsultationAttachment::findOrFail((int) $id);
    }

    public function test_pdf_is_stored_encrypted_and_described(): void
    {
        $consultation = Consultation::factory()->create();
        $pdf = $this->pdfBytes('Topografia');

        $response = $this->upload($consultation, $this->uploadedFile($pdf, 'Topografia Córnea.pdf'), ['description' => 'Topografia de córnea'])
            ->assertCreated();

        $id = $response->json('data.id');
        $response->assertExactJson(['data' => [
            'id' => $id,
            'consultation_id' => $consultation->id,
            'original_name' => 'Topografia Córnea.pdf',
            'mime_type' => 'application/pdf',
            'size' => strlen($pdf),
            'description' => 'Topografia de córnea',
            'download_url' => url("/api/attachments/{$id}/download"),
            'created_at' => $response->json('data.created_at'),
        ]]);

        $attachment = ConsultationAttachment::findOrFail((int) $id);
        $this->assertMatchesRegularExpression('#^attachments/[A-Za-z0-9]{40}\.bin$#', $attachment->path);

        $raw = (string) Storage::disk(EncryptedFiles::DISK)->get($attachment->path);
        $this->assertStringNotContainsString('%PDF', $raw);
        $this->assertSame($pdf, Crypt::decryptString($raw));

        $row = (array) DB::table('consultation_attachments')->where('id', $id)->first();
        $this->assertSame('Topografia Córnea.pdf', Crypt::decryptString((string) $row['original_name']));
        $this->assertSame('Topografia de córnea', Crypt::decryptString((string) $row['description']));
    }

    public function test_type_is_detected_from_the_content(): void
    {
        $consultation = Consultation::factory()->create();

        // PNG named .jpg: accepted as PNG, and the download name gets the real extension.
        $this->upload($consultation, $this->uploadedFile($this->imageBytes(50, 50), 'retina.jpg'))
            ->assertCreated()
            ->assertJsonPath('data.mime_type', 'image/png')
            ->assertJsonPath('data.original_name', 'retina.jpg.png');

        $this->upload($consultation, $this->uploadedFile($this->imageBytes(50, 50, 'jpeg'), 'retina.JPEG'))
            ->assertCreated()
            ->assertJsonPath('data.mime_type', 'image/jpeg')
            ->assertJsonPath('data.original_name', 'retina.jpeg');

        foreach ([
            'laudo.pdf' => 'texto simples, não é PDF',
            'pagina.pdf' => '<!DOCTYPE html><html><body><script>alert(1)</script></body></html>',
            'script.png' => "#!/bin/sh\necho hacked\n",
            'zipado.pdf' => "PK\x03\x04".str_repeat("\0", 100),
        ] as $name => $contents) {
            $this->upload($consultation, $this->uploadedFile($contents, $name))
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['file' => 'O arquivo deve ser um PDF, JPEG ou PNG.']);
        }

        // WebP is fine for photos but not an accepted attachment type.
        if (function_exists('imagewebp')) {
            $this->upload($consultation, $this->uploadedFile($this->imageBytes(50, 50, 'webp'), 'exame.webp'))->assertUnprocessable();
        }

        $this->assertSame(2, ConsultationAttachment::count());
        $this->assertCount(2, Storage::disk(EncryptedFiles::DISK)->allFiles());
    }

    public function test_size_description_and_presence_are_validated(): void
    {
        $consultation = Consultation::factory()->create();

        $big = $this->uploadedFile($this->pdfBytes().str_repeat('0', 10 * 1024 * 1024), 'grande.pdf');
        $this->upload($consultation, $big)->assertUnprocessable()->assertJsonValidationErrors(['file' => 'O arquivo não pode ter mais de 10 MB.']);

        $this->upload($consultation, $this->uploadedFile($this->pdfBytes(), 'a.pdf'), ['description' => str_repeat('d', 256)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['description' => 'O campo descrição não pode ter mais de 255 caracteres.']);

        $this->post("/api/consultations/{$consultation->id}/attachments", [], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file' => 'Envie um arquivo.']);

        $this->post("/api/consultations/{$consultation->id}/attachments", ['file' => 'x'], ['Accept' => 'application/json'])
            ->assertJsonValidationErrors(['file' => 'O campo arquivo deve ser um arquivo.']);

        $this->assertSame(0, ConsultationAttachment::count());
        $this->assertSame([], Storage::disk(EncryptedFiles::DISK)->allFiles());
    }

    public function test_body_above_post_max_size_is_413_in_portuguese(): void
    {
        $consultation = Consultation::factory()->create();
        $limit = (int) ini_get('post_max_size') * 1024 * 1024;

        $this->call('POST', "/api/consultations/{$consultation->id}/attachments", [], [], [], [
            'CONTENT_LENGTH' => (string) ($limit + 1),
            'HTTP_ACCEPT' => 'application/json',
        ])->assertStatus(413)->assertExactJson(['message' => 'O arquivo enviado é grande demais.']);
    }

    public function test_any_consultation_status_accepts_attachments(): void
    {
        $consultation = Consultation::factory()->create(['status' => 'completed']);

        $this->upload($consultation, $this->uploadedFile($this->pdfBytes(), 'laudo.pdf'))->assertCreated();
    }

    public function test_listing_is_newest_first_without_pagination(): void
    {
        $consultation = Consultation::factory()->create();
        Carbon::setTestNow('2026-11-03 10:00:00');
        $old = $this->storedAttachment($consultation, 'antigo.pdf');
        Carbon::setTestNow('2026-11-03 11:00:00');
        $new = $this->storedAttachment($consultation, 'novo.pdf');
        $this->storedAttachment(Consultation::factory()->create(), 'outro.pdf');

        $this->getJson("/api/consultations/{$consultation->id}/attachments")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $new->id)
            ->assertJsonPath('data.1.id', $old->id)
            ->assertJsonPath('data.1.original_name', 'antigo.pdf')
            ->assertJsonMissingPath('data.0.path')
            ->assertJsonMissingPath('meta');
    }

    public function test_download_round_trips_the_content_with_safe_headers(): void
    {
        $consultation = Consultation::factory()->create();
        $pdf = $this->pdfBytes('conteudo');
        $id = $this->upload($consultation, $this->uploadedFile($pdf, '../../etc/Laudo "Córnea"; tipo=1.pdf'))->assertCreated()->json('data.id');

        $response = $this->get("/api/attachments/{$id}/download")->assertOk();

        $this->assertSame($pdf, $response->streamedContent());
        $response->assertHeader('Content-Type', 'application/pdf');
        $response->assertHeader('Content-Length', (string) strlen($pdf));
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader(
            'Content-Disposition',
            'attachment; filename="Laudo Cornea_ tipo_1.pdf"; filename*=UTF-8\'\'Laudo%20C%C3%B3rnea%3B%20tipo%3D1.pdf'
        );
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_delete_removes_the_row_and_the_file(): void
    {
        $attachment = $this->storedAttachment(Consultation::factory()->create());
        $disk = Storage::disk(EncryptedFiles::DISK);
        $this->assertTrue($disk->exists($attachment->path));

        $this->delete("/api/attachments/{$attachment->id}")->assertNoContent();

        $this->assertFalse($disk->exists($attachment->path));
        $this->assertModelMissing($attachment);
        $this->deleteJson("/api/attachments/{$attachment->id}")->assertNotFound()->assertExactJson(['message' => 'Anexo não encontrado.']);
        $this->getJson("/api/attachments/{$attachment->id}/download")->assertNotFound()->assertExactJson(['message' => 'Anexo não encontrado.']);
    }

    public function test_not_found_cases(): void
    {
        $this->getJson('/api/attachments/999999/download')->assertNotFound()->assertExactJson(['message' => 'Anexo não encontrado.']);
        $this->getJson('/api/attachments/abc/download')->assertNotFound();
        $this->getJson('/api/consultations/999999/attachments')->assertNotFound()->assertExactJson(['message' => 'Atendimento não encontrado.']);

        // Row whose file is gone: clean 404, not a 500.
        $attachment = $this->storedAttachment(Consultation::factory()->create());
        Storage::disk(EncryptedFiles::DISK)->delete($attachment->path);
        $this->getJson("/api/attachments/{$attachment->id}/download")->assertNotFound()->assertExactJson(['message' => 'Anexo não encontrado.']);
    }

    public function test_trashed_patient_attachments_are_404(): void
    {
        $patient = Patient::factory()->create();
        $consultation = Consultation::factory()->for($patient)->create();
        $attachment = $this->storedAttachment($consultation);
        $patient->delete();

        $this->getJson("/api/consultations/{$consultation->id}/attachments")->assertNotFound();
        $this->upload($consultation, $this->uploadedFile($this->pdfBytes(), 'b.pdf'))->assertNotFound();
        $this->getJson("/api/attachments/{$attachment->id}/download")->assertNotFound()->assertExactJson(['message' => 'Anexo não encontrado.']);
        $this->deleteJson("/api/attachments/{$attachment->id}")->assertNotFound();

        // Soft delete keeps the files (restorable).
        $this->assertTrue(Storage::disk(EncryptedFiles::DISK)->exists($attachment->path));
    }

    public function test_hard_deletes_remove_the_files(): void
    {
        $disk = Storage::disk(EncryptedFiles::DISK);
        $patient = Patient::factory()->create();
        $first = $this->storedAttachment(Consultation::factory()->for($patient)->create());
        $second = $this->storedAttachment(Consultation::factory()->for($patient)->create());
        $patient->forceFill(['photo_path' => EncryptedFiles::put(EncryptedFiles::PHOTOS, 'jpeg'), 'photo_version' => 1])->save();
        $other = $this->storedAttachment(Consultation::factory()->create());

        // Deleting a consultation removes its attachments' files.
        Consultation::findOrFail($first->consultation_id)->delete();
        $this->assertFalse($disk->exists($first->path));
        $this->assertModelMissing($first);

        // A patient hard delete removes the photo and every attachment file (soft delete would keep them).
        $patient->delete();
        $this->assertTrue($disk->exists($second->path));
        $patient->forceDelete();

        $this->assertFalse($disk->exists($second->path));
        $this->assertSame([$other->path], $disk->allFiles(EncryptedFiles::ATTACHMENTS));
        $this->assertSame([], $disk->allFiles(EncryptedFiles::PHOTOS));
        $this->assertSame(1, ConsultationAttachment::count());
    }
}
