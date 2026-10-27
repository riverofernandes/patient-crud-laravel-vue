<?php

namespace App\Http\Controllers;

use App\Http\Requests\PatientPhotoRequest;
use App\Http\Resources\PatientResource;
use App\Models\Patient;
use App\Support\EncryptedFiles;
use App\Support\PhotoProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * @group Pacientes
 */
class PatientPhotoController extends Controller
{
    public const NOT_FOUND_MESSAGE = 'Foto não encontrada.';

    /**
     * Foto do paciente
     *
     * A foto (JPEG, até 512x512) do paciente. A URL com `?v=<versão>` vem em `photo_url` no recurso do paciente;
     * a versão muda a cada troca, então a resposta pode ficar em cache no navegador (`Cache-Control: private,
     * max-age=86400`). `404` se o paciente não tiver foto, não existir ou estiver na lixeira.
     *
     * @urlParam patient_id integer required ID do paciente. Example: 1
     *
     * @queryParam v integer Versão da foto (só para invalidar o cache; ignorada pelo servidor). Example: 1
     *
     * @response 200 scenario="Imagem (image/jpeg)" <binário JPEG>
     * @response 404 scenario="Paciente sem foto" {"message": "Foto não encontrada."}
     */
    public function show(Patient $patient): StreamedResponse|JsonResponse
    {
        $response = $patient->photo_path === null ? null : EncryptedFiles::response($patient->photo_path, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=86400',
        ]);

        return $response ?? $this->notFound();
    }

    /**
     * Enviar foto do paciente
     *
     * Envia ou troca a foto (multipart, campo `photo`): JPEG, PNG ou WebP (tipo conferido pelo conteúdo), até 2 MB,
     * entre 100x100 e 4000x4000 px. A imagem é reprocessada no servidor (corte quadrado centralizado, no máximo
     * 512x512, JPEG), o que remove os metadados EXIF (como a localização), e fica criptografada em disco privado.
     * Gera um registro `photo` no histórico (sem a imagem). Retorna o paciente com o novo `photo_url`.
     *
     * @urlParam patient_id integer required ID do paciente. Example: 1
     *
     * @response 200 scenario="Foto enviada" {"data": {"id": 1, "name": "Maria da Silva", "cpf": "62518953060", "rg": "35688019X", "birth_date": "1996-07-20", "sex": "F", "phone": "11928535718", "email": "maria@example.com", "description": null, "created_at": "2026-10-26T15:30:00-03:00", "updated_at": "2026-11-03T10:00:00-03:00", "age": 30, "deleted_at": null, "address": null, "photo_url": "http://localhost:8000/api/patients/1/photo?v=1"}}
     * @response 404 scenario="Paciente não encontrado ou excluído" {"message": "Paciente não encontrado."}
     * @response 422 scenario="Tipo inválido" {"message": "A foto deve ser uma imagem JPEG, PNG ou WebP.", "errors": {"photo": ["A foto deve ser uma imagem JPEG, PNG ou WebP."]}}
     */
    public function store(PatientPhotoRequest $request, Patient $patient): PatientResource
    {
        /** @var UploadedFile $file validated as a single file */
        $file = $request->file('photo');
        $contents = (string) $file->get();

        try {
            $jpeg = PhotoProcessor::process($contents);
        } catch (RuntimeException) {
            throw ValidationException::withMessages(['photo' => 'A foto não pôde ser processada. Envie outra imagem.']);
        }

        $previous = $patient->photo_path;
        $path = EncryptedFiles::put(EncryptedFiles::PHOTOS, $jpeg);

        try {
            DB::transaction(function () use ($patient, $path) {
                $patient->photo_path = $path;
                $patient->photo_version++;
                $patient->save(); // PatientObserver records the "photo" audit
            });
        } catch (Throwable $e) {
            EncryptedFiles::delete($path);

            throw $e;
        }

        EncryptedFiles::delete($previous);

        return new PatientResource($patient->load('address'));
    }

    /**
     * Remover foto do paciente
     *
     * Remove a foto (e o arquivo). Gera um registro `photo` no histórico. `404` se o paciente não tiver foto.
     *
     * @urlParam patient_id integer required ID do paciente. Example: 1
     *
     * @response 204 scenario="Foto removida"
     * @response 404 scenario="Paciente sem foto" {"message": "Foto não encontrada."}
     */
    public function destroy(Patient $patient): Response|JsonResponse
    {
        $previous = $patient->photo_path;

        if ($previous === null) {
            return $this->notFound();
        }

        $patient->photo_path = null;
        $patient->photo_version++;
        $patient->save();

        EncryptedFiles::delete($previous);

        return response()->noContent();
    }

    private function notFound(): JsonResponse
    {
        return response()->json(['message' => self::NOT_FOUND_MESSAGE], Response::HTTP_NOT_FOUND);
    }
}
