<?php

namespace App\Http\Controllers;

use App\Http\Requests\PatientRequest;
use App\Http\Resources\PatientResource;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class PatientController extends Controller
{
    private const DEFAULT_PER_PAGE = 10;

    private const MAX_PER_PAGE = 100;

    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = filter_var($request->query('per_page'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
            ?: self::DEFAULT_PER_PAGE;
        $perPage = min($perPage, self::MAX_PER_PAGE);

        $patients = Patient::query()
            ->search($request->query('search'))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        return PatientResource::collection($patients);
    }

    public function store(PatientRequest $request): PatientResource
    {
        $patient = Patient::create($request->validated());

        return new PatientResource($patient);
    }

    public function show(Patient $patient): PatientResource
    {
        return new PatientResource($patient);
    }

    public function update(PatientRequest $request, Patient $patient): PatientResource
    {
        // PUT is a full replacement: optional fields left out are cleared.
        $patient->update(array_merge(['email' => null, 'description' => null], $request->validated()));

        return new PatientResource($patient);
    }

    public function destroy(Patient $patient): Response
    {
        $patient->delete();

        return response()->noContent();
    }
}
