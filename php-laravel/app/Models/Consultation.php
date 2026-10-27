<?php

namespace App\Models;

use App\Enums\ConsultationStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A patient's appointment (atendimento). Datetimes are America/Sao_Paulo local time.
 *
 * @property int $id
 * @property int $patient_id
 * @property Carbon $scheduled_at
 * @property Carbon $ends_at
 * @property int $duration_minutes
 * @property ConsultationStatus $status
 * @property string $professional_name
 * @property string $professional_key
 * @property string|null $professional_crm
 * @property string|null $reason
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Patient|null $patient
 * @property-read EyeExam|null $eyeExam
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ConsultationAttachment> $attachments
 *
 * @method static Builder<static> occupyingSlot(string $professionalName, CarbonInterface $start, CarbonInterface $end, int|null $ignoreId = null)
 * @method static Builder<static> startingBetween(CarbonInterface $from, CarbonInterface $until)
 * @method static Builder<static> withStatuses(array<int, string> $statuses)
 * @method static Builder<static> professionalLike(mixed $term)
 */
class Consultation extends Model
{
    use HasFactory;

    public const DEFAULT_DURATION = 30;

    public const MIN_DURATION = 10;

    public const MAX_DURATION = 240;

    /** Fields replaced by PUT (same as POST). */
    public const EDITABLE = ['scheduled_at', 'duration_minutes', 'professional_name', 'professional_crm', 'reason', 'notes'];

    protected $fillable = [
        'scheduled_at',
        'duration_minutes',
        'status',
        'professional_name',
        'professional_crm',
        'reason',
        'notes',
    ];

    protected $hidden = [
        'ends_at',
        'professional_key',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'scheduled',
        'duration_minutes' => self::DEFAULT_DURATION,
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'ends_at' => 'datetime',
        'duration_minutes' => 'integer',
        'status' => ConsultationStatus::class,
        // Clinical free text, encrypted at rest with APP_KEY.
        'reason' => 'encrypted',
        'notes' => 'encrypted',
    ];

    protected static function booted(): void
    {
        // Derived columns used by the overlap check and the agenda filter.
        static::saving(function (Consultation $consultation) {
            $consultation->ends_at = $consultation->scheduled_at->copy()->addMinutes($consultation->duration_minutes);
            $consultation->professional_key = self::professionalKey($consultation->professional_name);
        });

        // The database cascade fires no model events: delete the attachments one by one so their encrypted files
        // are removed too (see ConsultationAttachment).
        static::deleting(function (Consultation $consultation) {
            $consultation->attachments()->get()->each(fn (ConsultationAttachment $attachment) => $attachment->delete());
        });
    }

    /**
     * Identity of a professional: trimmed, single-spaced, lower-cased and without accents ("Dr. Érico" and
     * "dr.  erico" are the same person). Folding accents here makes SQLite (tests) behave like MySQL, whose
     * utf8mb4_unicode_ci collation already ignores case and accents.
     */
    public static function professionalKey(string $name): string
    {
        return Str::lower(Str::ascii((string) preg_replace('/\s+/u', ' ', trim($name)), 'pt'));
    }

    /**
     * @return BelongsTo<Patient, Consultation>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * @return HasOne<EyeExam>
     */
    public function eyeExam(): HasOne
    {
        return $this->hasOne(EyeExam::class);
    }

    /**
     * @return HasMany<ConsultationAttachment>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(ConsultationAttachment::class);
    }

    /**
     * Route model binding: consultations of a patient in the trash are not found (404).
     *
     * @param  mixed  $value
     * @param  string|null  $field
     */
    public function resolveRouteBinding($value, $field = null): ?self
    {
        return $this->newQuery()
            ->where($field ?? $this->getRouteKeyName(), $value)
            ->whereHas('patient')
            ->first();
    }

    /**
     * Consultations of the same professional (ignoring case and accents) that still occupy the time slot (not cancelled
     * nor no_show) and overlap the half-open interval [start, end): touching edges do not overlap.
     * Patients in the trash are ignored, as they are hidden from the agenda.
     *
     * @param  Builder<Consultation>  $query
     * @return Builder<Consultation>
     */
    public function scopeOccupyingSlot(
        Builder $query,
        string $professionalName,
        CarbonInterface $start,
        CarbonInterface $end,
        int $ignoreId = null
    ): Builder {
        return $query
            ->where('professional_key', self::professionalKey($professionalName))
            ->where('scheduled_at', '<', $end->format('Y-m-d H:i:s'))
            ->where('ends_at', '>', $start->format('Y-m-d H:i:s'))
            ->whereIn('status', ConsultationStatus::blockingValues())
            ->whereHas('patient')
            ->when($ignoreId !== null, fn (Builder $query) => $query->whereKeyNot($ignoreId));
    }

    /**
     * Consultations starting in [from, until).
     *
     * @param  Builder<Consultation>  $query
     * @return Builder<Consultation>
     */
    public function scopeStartingBetween(Builder $query, CarbonInterface $from, CarbonInterface $until): Builder
    {
        return $query
            ->where('scheduled_at', '>=', $from->format('Y-m-d H:i:s'))
            ->where('scheduled_at', '<', $until->format('Y-m-d H:i:s'));
    }

    /**
     * @param  Builder<Consultation>  $query
     * @param  array<int, string>  $statuses  empty = no filter
     * @return Builder<Consultation>
     */
    public function scopeWithStatuses(Builder $query, array $statuses): Builder
    {
        return $statuses === [] ? $query : $query->whereIn('status', $statuses);
    }

    /**
     * Partial professional name filter, ignoring case and accents (blank = no filter).
     *
     * @param  Builder<Consultation>  $query
     * @return Builder<Consultation>
     */
    public function scopeProfessionalLike(Builder $query, mixed $term): Builder
    {
        $term = is_string($term) ? self::professionalKey($term) : '';

        if ($term === '') {
            return $query;
        }

        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term);

        return $query->whereRaw("professional_key LIKE ? ESCAPE '!'", ['%'.$escaped.'%']);
    }
}
