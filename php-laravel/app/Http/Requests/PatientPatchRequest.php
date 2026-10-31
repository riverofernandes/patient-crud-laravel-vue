<?php

namespace App\Http\Requests;

/**
 * PATCH /patients/{id}: same rules as PatientRequest, but always partial (every top-level field `sometimes`).
 * A separate class so the API docs (Scribe builds the request without the real HTTP method) list the PATCH
 * fields as optional.
 */
class PatientPatchRequest extends PatientRequest
{
    public function isPartial(): bool
    {
        return true;
    }
}
