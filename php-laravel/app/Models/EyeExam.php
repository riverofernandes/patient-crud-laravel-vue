<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Ophthalmic record of a completed consultation: one column per eye (od = right, oe = left) and measure.
 *
 * @property int $id
 * @property int $consultation_id
 * @property string|null $od_visual_acuity_sc
 * @property string|null $od_visual_acuity_cc
 * @property float|null $od_spherical
 * @property float|null $od_cylindrical
 * @property int|null $od_axis
 * @property float|null $od_addition
 * @property int|null $od_iop
 * @property string|null $oe_visual_acuity_sc
 * @property string|null $oe_visual_acuity_cc
 * @property float|null $oe_spherical
 * @property float|null $oe_cylindrical
 * @property int|null $oe_axis
 * @property float|null $oe_addition
 * @property int|null $oe_iop
 * @property string|null $diagnosis
 * @property string|null $observations
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Consultation|null $consultation
 */
class EyeExam extends Model
{
    use HasFactory;

    public const EYES = ['od', 'oe'];

    /** Per-eye measures, in API order. */
    public const MEASURES = ['visual_acuity_sc', 'visual_acuity_cc', 'spherical', 'cylindrical', 'axis', 'addition', 'iop'];

    protected $fillable = [
        'od_visual_acuity_sc', 'od_visual_acuity_cc', 'od_spherical', 'od_cylindrical', 'od_axis', 'od_addition', 'od_iop',
        'oe_visual_acuity_sc', 'oe_visual_acuity_cc', 'oe_spherical', 'oe_cylindrical', 'oe_axis', 'oe_addition', 'oe_iop',
        'diagnosis',
        'observations',
    ];

    protected $casts = [
        // float (not decimal:2, which returns strings): the API returns JSON numbers.
        'od_spherical' => 'float',
        'od_cylindrical' => 'float',
        'od_axis' => 'integer',
        'od_addition' => 'float',
        'od_iop' => 'integer',
        'oe_spherical' => 'float',
        'oe_cylindrical' => 'float',
        'oe_axis' => 'integer',
        'oe_addition' => 'float',
        'oe_iop' => 'integer',
        // Clinical free text, encrypted at rest with APP_KEY.
        'diagnosis' => 'encrypted',
        'observations' => 'encrypted',
    ];

    /**
     * @return BelongsTo<Consultation, EyeExam>
     */
    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    /**
     * Measures of one eye ("od" or "oe"), keyed as in the API.
     *
     * @return array<string, string|float|int|null>
     */
    public function eye(string $eye): array
    {
        $values = [];

        foreach (self::MEASURES as $measure) {
            /** @var string|float|int|null $value */
            $value = $this->getAttribute("{$eye}_{$measure}");
            $values[$measure] = $value;
        }

        return $values;
    }

    /**
     * Refraction values of one eye ("od" or "oe").
     *
     * @return array{spherical: float|null, cylindrical: float|null, axis: int|null, addition: float|null}
     */
    public function refraction(string $eye): array
    {
        return $eye === 'od'
            ? ['spherical' => $this->od_spherical, 'cylindrical' => $this->od_cylindrical, 'axis' => $this->od_axis, 'addition' => $this->od_addition]
            : ['spherical' => $this->oe_spherical, 'cylindrical' => $this->oe_cylindrical, 'axis' => $this->oe_axis, 'addition' => $this->oe_addition];
    }

    /**
     * Whether at least one refraction value (spherical, cylindrical, axis, addition) is filled for either eye.
     */
    public function hasRefraction(): bool
    {
        foreach (self::EYES as $eye) {
            if (array_filter($this->refraction($eye), fn ($value) => $value !== null) !== []) {
                return true;
            }
        }

        return false;
    }
}
