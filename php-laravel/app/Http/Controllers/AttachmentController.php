<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttachmentRequest;
use App\Http\Resources\AttachmentResource;
use App\Models\Consultation;
use App\Models\ConsultationAttachment;
use App\Support\EncryptedFiles;
use App\Support\SafeFilename;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * @group Atendimentos
 */
class AttachmentController extends Controller
{
    public const NOT_FOUND_MESSAGE = 'Anexo não encontrado.';

    /**
     * Listar anexos do atendimento
     *
     * Exames anexados ao atendimento (laudos, imagens), do mais recente para o mais antigo, sem paginação.
     * `404` se o atendimento não existir ou o paciente estiver na lixeira.
     *
     * @urlParam consultation_id integer required ID do atendimento. Example: 1
     *
     * @response 200 scenario="Sucesso" {"data": [{"id": 3, "consultation_id": 1, "original_name": "topografia.pdf", "mime_type": "application/pdf", "size": 183245, "description": "Topografia de córnea", "download_url": "http://localhost:8000/api/attachments/3/download", "created_at": "2026-11-03T15:10:00-03:00"}]}
     * @response 404 scenario="Atendimento não encontrado" {"message": "Atendimento não encontrado."}
     */
    public function index(Consultation $consultation): AnonymousResourceCollection
    {
        $attachments = $consultation->attachments()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return AttachmentResource::collection($attachments);
    }

    /**
     * Anexar exame ao atendimento
     *
     * Envia um arquivo (multipart, campo `file`): PDF, JPEG ou PNG, até 10 MB. O tipo é conferido pelo conteúdo
     * (não só pela extensão). Vale para qualquer status de atendimento. O arquivo fica criptografado em disco
     * privado, com nome aleatório; o nome original é saneado (e recebe a extensão do tipo detectado).
     *
     * @urlParam consultation_id integer required ID do atendimento. Example: 1
     *
     * @response 201 scenario="Anexo criado" {"data": {"id": 3, "consultation_id": 1, "original_name": "topografia.pdf", "mime_type": "application/pdf", "size": 183245, "description": "Topografia de córnea", "download_url": "http://localhost:8000/api/attachments/3/download", "created_at": "2026-11-03T15:10:00-03:00"}}
     * @response 404 scenario="Atendimento não encontrado" {"message": "Atendimento não encontrado."}
     * @response 422 scenario="Tipo não permitido" {"message": "O arquivo deve ser um PDF, JPEG ou PNG.", "errors": {"file": ["O arquivo deve ser um PDF, JPEG ou PNG."]}}
     */
    public function store(AttachmentRequest $request, Consultation $consultation): JsonResponse
    {
        /** @var UploadedFile $file validated as a single file */
        $file = $request->file('file');
        // Detected from the contents (finfo), as validated; never the client's Content-Type.
        $mime = (string) $file->getMimeType();
        $contents = (string) $file->get();
        $path = EncryptedFiles::put(EncryptedFiles::ATTACHMENTS, $contents);

        try {
            $attachment = $consultation->attachments()->create([
                'path' => $path,
                'original_name' => SafeFilename::sanitize(
                    $file->getClientOriginalName(),
                    ConsultationAttachment::MIME_EXTENSIONS[$mime] ?? 'bin'
                ),
                'mime_type' => $mime,
                'size' => strlen($contents),
                'description' => $request->validated('description'),
            ]);
        } catch (Throwable $e) {
            EncryptedFiles::delete($path);

            throw $e;
        }

        return (new AttachmentResource($attachment))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Baixar anexo
     *
     * O arquivo original (descriptografado), com o tipo detectado no envio e
     * `Content-Disposition: attachment; filename="<nome saneado em ASCII>"; filename*=UTF-8''<nome saneado>`.
     * O "Try it out" não exibe o arquivo; para baixar, abra a URL no navegador.
     *
     * @urlParam attachment_id integer required ID do anexo. Example: 1
     *
     * @response 200 scenario="Arquivo" <binário>
     * @response 404 scenario="Anexo não encontrado" {"message": "Anexo não encontrado."}
     */
    public function download(ConsultationAttachment $attachment): StreamedResponse|JsonResponse
    {
        $response = EncryptedFiles::response($attachment->path, [
            'Content-Type' => $attachment->mime_type,
            'Content-Disposition' => SafeFilename::disposition($attachment->original_name),
            'Cache-Control' => 'no-store, private',
        ]);

        return $response ?? response()->json(['message' => self::NOT_FOUND_MESSAGE], Response::HTTP_NOT_FOUND);
    }

    /**
     * Excluir anexo
     *
     * Remove o anexo e o arquivo.
     *
     * @urlParam attachment_id integer required ID do anexo. Example: 1
     *
     * @response 204 scenario="Anexo excluído"
     * @response 404 scenario="Anexo não encontrado" {"message": "Anexo não encontrado."}
     */
    public function destroy(ConsultationAttachment $attachment): Response
    {
        $attachment->delete();

        return response()->noContent();
    }
}
