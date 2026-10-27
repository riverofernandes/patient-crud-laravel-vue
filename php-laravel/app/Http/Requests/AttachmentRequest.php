<?php

namespace App\Http\Requests;

use App\Models\ConsultationAttachment;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Exam attachment upload (multipart). The type is detected from the contents (finfo), never from the file name or
 * the client's Content-Type.
 */
class AttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'bail',
                'required',
                'file',
                'max:'.ConsultationAttachment::MAX_KILOBYTES,
                'mimetypes:'.implode(',', array_keys(ConsultationAttachment::MIME_EXTENSIONS)),
            ],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Envie um arquivo.',
            'file.file' => 'O campo arquivo deve ser um arquivo.',
            'file.uploaded' => 'O arquivo não pôde ser enviado. O tamanho máximo é 10 MB.',
            'file.max' => 'O arquivo não pode ter mais de 10 MB.',
            'file.mimetypes' => 'O arquivo deve ser um PDF, JPEG ou PNG.',
            'description.string' => 'O campo descrição deve ser um texto.',
            'description.max' => 'O campo descrição não pode ter mais de :max caracteres.',
        ];
    }

    /**
     * Body parameter documentation for Scribe.
     *
     * @return array<string, array<string, mixed>>
     */
    public function bodyParameters(): array
    {
        return [
            'file' => [
                'description' => 'Arquivo PDF, JPEG ou PNG, até 10 MB. O tipo é conferido pelo conteúdo, não pela extensão.',
                // Real sample file (path relative to the project root): keeps the generated docs deterministic.
                'example' => 'postman/fixtures/exam.pdf',
            ],
            'description' => [
                'description' => 'Descrição do anexo (opcional, até 255 caracteres).',
                'example' => 'Topografia de córnea',
            ],
        ];
    }
}
