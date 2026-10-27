<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Patient photo upload (multipart, field `photo`). The type is detected from the contents (finfo), never from the
 * file name or the client's Content-Type; SVG is not accepted (the `image` rule would allow it).
 */
class PatientPhotoRequest extends FormRequest
{
    public const MAX_KILOBYTES = 2048;

    public const MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

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
            'photo' => [
                'bail',
                'required',
                'file',
                'max:'.self::MAX_KILOBYTES,
                'mimetypes:'.implode(',', self::MIME_TYPES),
                'dimensions:min_width=100,min_height=100,max_width=4000,max_height=4000',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'photo.required' => 'Envie uma foto.',
            'photo.file' => 'O campo foto deve ser um arquivo.',
            'photo.uploaded' => 'A foto não pôde ser enviada. O tamanho máximo é 2 MB.',
            'photo.max' => 'A foto não pode ter mais de 2 MB.',
            'photo.mimetypes' => 'A foto deve ser uma imagem JPEG, PNG ou WebP.',
            'photo.dimensions' => 'A foto deve ter entre 100x100 e 4000x4000 pixels.',
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
            'photo' => [
                'description' => 'Imagem JPEG, PNG ou WebP (conferido pelo conteúdo), até 2 MB, entre 100x100 e 4000x4000 px. Reprocessada no servidor: corte quadrado centralizado, no máximo 512x512, JPEG, sem metadados EXIF.',
                // Real sample file (path relative to the project root): keeps the generated docs deterministic.
                'example' => 'postman/fixtures/photo.png',
            ],
        ];
    }
}
