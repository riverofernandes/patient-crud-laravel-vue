<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A patient's address (1:1). Street, number, complement and neighborhood are encrypted at rest; CEP, city and
 * state stay in clear.
 *
 * @property int $id
 * @property int $patient_id
 * @property string $cep
 * @property string $street
 * @property string $number
 * @property string|null $complement
 * @property string $neighborhood
 * @property string $city
 * @property string $state
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PatientAddress extends Model
{
    /** Fields of the `Address` resource, in API order (also the audited `address.<field>` keys). */
    public const FIELDS = ['cep', 'street', 'number', 'complement', 'neighborhood', 'city', 'state'];

    /** Brazilian states (UF) plus the Federal District. */
    public const STATES = [
        'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA',
        'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO',
    ];

    protected $fillable = self::FIELDS;

    protected $casts = [
        'street' => 'encrypted',
        'number' => 'encrypted',
        'complement' => 'encrypted',
        'neighborhood' => 'encrypted',
    ];

    /**
     * @return BelongsTo<Patient, PatientAddress>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class)->withTrashed();
    }

    /**
     * The `Address` resource (raw values, as stored).
     *
     * @return array<string, string|null>
     */
    public function toResource(): array
    {
        $values = [];

        foreach (self::FIELDS as $field) {
            $value = $this->getAttribute($field);
            $values[$field] = $value === null ? null : (string) $value;
        }

        return $values;
    }

    /**
     * Audit `changes` between two addresses (`Address` arrays, or null for no address), keyed `address.<field>`;
     * only the fields that differ.
     *
     * @param  array<string, string|null>|null  $old
     * @param  array<string, string|null>|null  $new
     * @return array<string, array{old: string|null, new: string|null}>
     */
    public static function auditChanges(?array $old, ?array $new): array
    {
        $changes = [];

        foreach (self::FIELDS as $field) {
            $before = $old[$field] ?? null;
            $after = $new[$field] ?? null;

            if ($before !== $after) {
                $changes['address.'.$field] = ['old' => $before, 'new' => $after];
            }
        }

        return $changes;
    }

    /**
     * One-line address for the CSV export: "street, number - complement - neighborhood" (empty parts skipped).
     */
    public function oneLine(): string
    {
        $parts = array_filter(
            [$this->street.', '.$this->number, $this->complement, $this->neighborhood],
            fn (?string $part) => $part !== null && $part !== ''
        );

        return implode(' - ', $parts);
    }
}
