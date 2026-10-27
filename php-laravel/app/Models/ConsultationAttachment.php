<?php

namespace App\Models;

use App\Support\EncryptedFiles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An exam file attached to a consultation (PDF, JPEG or PNG). The contents live encrypted on the private disk
 * under a random name (App\Support\EncryptedFiles); deleting the model deletes the file.
 *
 * @property int $id
 * @property int $consultation_id
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Consultation|null $consultation
 */
class ConsultationAttachment extends Model
{
    /** Allowed types, detected from the contents (finfo), with the extension used in the download name. */
    public const MIME_EXTENSIONS = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];

    public const MAX_KILOBYTES = 10240;

    protected $fillable = [
        'path',
        'original_name',
        'mime_type',
        'size',
        'description',
    ];

    protected $hidden = [
        'path',
    ];

    protected $casts = [
        'size' => 'integer',
        // File names and descriptions may contain the patient's name or clinical details.
        'original_name' => 'encrypted',
        'description' => 'encrypted',
    ];

    protected static function booted(): void
    {
        // After the row is gone, so a failed delete never leaves a row pointing to a missing file.
        static::deleted(function (ConsultationAttachment $attachment) {
            EncryptedFiles::delete($attachment->path);
        });
    }

    /**
     * @return BelongsTo<Consultation, ConsultationAttachment>
     */
    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    /**
     * Route model binding: attachments of a consultation whose patient is in the trash are not found (404).
     *
     * @param  mixed  $value
     * @param  string|null  $field
     */
    public function resolveRouteBinding($value, $field = null): ?self
    {
        return $this->newQuery()
            ->where($field ?? $this->getRouteKeyName(), $value)
            ->whereHas('consultation.patient')
            ->first();
    }
}
