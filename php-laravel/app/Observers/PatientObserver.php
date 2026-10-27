<?php

namespace App\Observers;

use App\Models\Patient;
use App\Models\PatientAudit;
use DateTimeInterface;

/**
 * Writes the patient change history (patient_audits): created / updated / deleted / restored.
 *
 * Besides the patient's own fields, `changes` may hold:
 * - `address.<field>` (cep, street, ...): handed over by the controller through Patient::$pendingAuditChanges,
 *   so a request that changes the patient and the address writes a single audit;
 * - `photo`: {"old": null, "new": "adicionada" | "alterada" | "removida"} when the photo changes (never the image).
 */
class PatientObserver
{
    /** Fields recorded in `changes` (raw values, as returned by the API). */
    public const AUDITED_FIELDS = ['name', 'cpf', 'rg', 'birth_date', 'sex', 'phone', 'email', 'description'];

    public const PHOTO_ADDED = 'adicionada';

    public const PHOTO_CHANGED = 'alterada';

    public const PHOTO_REMOVED = 'removida';

    private const USER_AGENT_MAX = 255;

    private static bool $enabled = true;

    /**
     * Runs $callback without recording audits (e.g. seeding demo data). Unlike Model::withoutEvents(),
     * the model's own `saving` hook (which fills cpf_hash) still runs.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function withoutAuditing(callable $callback): mixed
    {
        $previous = self::$enabled;
        self::$enabled = false;

        try {
            return $callback();
        } finally {
            self::$enabled = $previous;
        }
    }

    public function created(Patient $patient): void
    {
        $changes = [];

        foreach (self::AUDITED_FIELDS as $field) {
            $changes[$field] = ['old' => null, 'new' => self::value($patient->getAttribute($field))];
        }

        $this->record($patient, PatientAudit::CREATED, $changes + self::pullPending($patient));
    }

    public function updated(Patient $patient): void
    {
        $changes = [];

        // getChanges() holds raw (encrypted) values: only its keys are used, values come from the casts.
        foreach (array_intersect(self::AUDITED_FIELDS, array_keys($patient->getChanges())) as $field) {
            $changes[$field] = [
                'old' => self::value($patient->getOriginal($field)),
                'new' => self::value($patient->getAttribute($field)),
            ];
        }

        if ($patient->wasChanged('photo_path')) {
            $changes['photo'] = ['old' => null, 'new' => self::photoMarker($patient)];
        }

        $changes += self::pullPending($patient);

        // restore() saves deleted_at only: that is the "restored" event, not an update.
        if ($changes !== []) {
            $this->record($patient, PatientAudit::UPDATED, $changes);
        }
    }

    /**
     * Records the pending changes the model events did not consume (e.g. a PUT that changes only the address
     * leaves the patient row clean, so no `updated` event fires) as one `updated` entry.
     */
    public static function flushPending(Patient $patient): void
    {
        $pending = self::pullPending($patient);

        if ($pending !== []) {
            (new self())->record($patient, PatientAudit::UPDATED, $pending);
        }
    }

    public function deleted(Patient $patient): void
    {
        // A hard delete removes the history with the row (cascade), so there is nothing to attach it to.
        if (! $patient->isForceDeleting()) {
            $this->record($patient, PatientAudit::DELETED, []);
        }
    }

    public function restored(Patient $patient): void
    {
        $this->record($patient, PatientAudit::RESTORED, []);
    }

    /**
     * @param  array<string, array{old: mixed, new: mixed}>  $changes
     */
    private function record(Patient $patient, string $event, array $changes): void
    {
        if (! self::$enabled) {
            return;
        }

        $request = request();
        // Only a routed HTTP request has a meaningful client; artisan (tinker, seeders) records null.
        $isHttp = $request->route() !== null;
        $userAgent = $isHttp ? $request->userAgent() : null;

        $patient->audits()->create([
            'event' => $event,
            'changes' => $changes,
            'ip' => $isHttp ? $request->ip() : null,
            'user_agent' => $userAgent === null || $userAgent === '' ? null : mb_substr($userAgent, 0, self::USER_AGENT_MAX),
            'user_id' => null,
        ]);
    }

    /**
     * @return array<string, array{old: mixed, new: mixed}>
     */
    private static function pullPending(Patient $patient): array
    {
        $pending = $patient->pendingAuditChanges;
        $patient->pendingAuditChanges = [];

        return $pending;
    }

    private static function photoMarker(Patient $patient): string
    {
        return match (true) {
            $patient->photo_path === null => self::PHOTO_REMOVED,
            $patient->getOriginal('photo_path') === null => self::PHOTO_ADDED,
            default => self::PHOTO_CHANGED,
        };
    }

    private static function value(mixed $value): mixed
    {
        return $value instanceof DateTimeInterface ? $value->format('Y-m-d') : $value;
    }
}
