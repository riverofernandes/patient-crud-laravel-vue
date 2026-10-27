<?php

namespace App\Http\Resources;

use App\Models\Consultation;
use App\Models\PatientAudit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One item of the patient timeline: a consultation (occurred_at = scheduled_at) or a change-history entry
 * (occurred_at = created_at).
 *
 * @property Consultation|PatientAudit $resource
 */
class TimelineEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $entry = $this->resource;

        if ($entry instanceof Consultation) {
            return [
                'type' => 'consultation',
                'occurred_at' => $entry->scheduled_at->toIso8601String(),
                'consultation' => new ConsultationResource($entry),
            ];
        }

        return [
            'type' => 'audit',
            'occurred_at' => $entry->created_at?->toIso8601String(),
            'audit' => new AuditResource($entry),
        ];
    }
}
