<?php

namespace App\Support;

use App\Models\Consultation;
use App\Models\Patient;
use App\Models\PatientAudit;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Patient timeline: consultations and change-history entries merged in one paginated list, newest first.
 *
 * Paginated in the database, not in PHP: a UNION ALL of two narrow selects (type, id, occurred_at, type_rank) is
 * wrapped in a derived table, ordered by `occurred_at desc, type_rank asc, id desc` (consultations before audits
 * on ties, then id desc) and paginated with LIMIT/OFFSET; the count runs on the same derived table. Only the ids
 * of the requested page are then hydrated as models (one query per type). Both sources are indexed by
 * (patient_id, date). Datetimes of both tables hold São Paulo local time, so they compare directly.
 */
final class PatientTimeline
{
    public const CONSULTATION = 'consultation';

    public const AUDIT = 'audit';

    /**
     * @return LengthAwarePaginator<Consultation|PatientAudit>
     */
    public static function paginate(Patient $patient, int $perPage): LengthAwarePaginator
    {
        $consultations = DB::table('consultations')
            ->where('patient_id', $patient->id)
            ->select(['id', 'scheduled_at as occurred_at'])
            ->selectRaw('? as type, 0 as type_rank', [self::CONSULTATION]);

        $audits = DB::table('patient_audits')
            ->where('patient_id', $patient->id)
            ->select(['id', 'created_at as occurred_at'])
            ->selectRaw('? as type, 1 as type_rank', [self::AUDIT]);

        /** @var LengthAwarePaginator<object{id: int, occurred_at: string, type: string, type_rank: int}> $page */
        $page = DB::query()
            ->fromSub($consultations->unionAll($audits), 'timeline')
            ->orderByDesc('occurred_at')
            ->orderBy('type_rank')
            ->orderByDesc('id')
            ->paginate($perPage);

        $rows = collect($page->items());
        $ids = fn (string $type) => $rows->where('type', $type)->pluck('id')->map(fn ($id) => (int) $id)->all();

        $models = [
            self::CONSULTATION => Consultation::with('eyeExam')->findMany($ids(self::CONSULTATION))->keyBy('id'),
            self::AUDIT => PatientAudit::query()->findMany($ids(self::AUDIT))->keyBy('id'),
        ];

        /** @var LengthAwarePaginator<Consultation|PatientAudit> $entries */
        $entries = $page->through(function (object $row) use ($models) {
            /** @var object{id: int, type: string} $row */
            return $models[$row->type]->get((int) $row->id);
        });

        return $entries;
    }
}
