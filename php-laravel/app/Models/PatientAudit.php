<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One entry of a patient's change history (see App\Observers\PatientObserver).
 *
 * @property int $id
 * @property int $patient_id
 * @property string $event
 * @property array<string, array{old: mixed, new: mixed}> $changes
 * @property string|null $ip
 * @property string|null $user_agent
 * @property int|null $user_id
 * @property Carbon|null $created_at
 */
class PatientAudit extends Model
{
    public const CREATED = 'created';

    public const UPDATED = 'updated';

    public const DELETED = 'deleted';

    public const RESTORED = 'restored';

    /** Append-only: there is no updated_at. */
    public const UPDATED_AT = null;

    protected $fillable = [
        'event',
        'changes',
        'ip',
        'user_agent',
        'user_id',
    ];

    protected $casts = [
        // Old/new values include CPF, RG and phone, so the whole payload is encrypted at rest.
        'changes' => 'encrypted:array',
        'created_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Patient, PatientAudit>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class)->withTrashed();
    }
}
