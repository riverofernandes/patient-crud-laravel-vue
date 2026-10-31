<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\CepController;
use App\Http\Controllers\ConsultationController;
use App\Http\Controllers\EyeExamController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\PatientPhotoController;
use App\Http\Controllers\PatientTimelineController;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Routes loaded by the RouteServiceProvider under the "api" middleware
| group and the /api prefix.
|
*/

// Liveness/readiness probe: not rate limited, so frequent container healthchecks never get a 429.
Route::get('health', HealthController::class)
    ->withoutMiddleware(ThrottleRequests::class.':api')
    ->name('health');

// Registered before the resource; {patient} only matches numeric ids anyway (see RouteServiceProvider).
Route::get('patients/cpf-availability', [PatientController::class, 'cpfAvailability'])
    ->name('patients.cpf-availability');

Route::get('patients/export', [PatientController::class, 'export'])
    ->name('patients.export');

// History is also available for patients in the trash.
Route::get('patients/{patient}/audits', [PatientController::class, 'audits'])
    ->withTrashed()
    ->name('patients.audits');

Route::post('patients/{patient}/restore', [PatientController::class, 'restore'])
    ->withTrashed()
    ->name('patients.restore');

// PUT and PATCH are separate routes (instead of the resource's PUT|PATCH one) so the API docs list both operations.
Route::apiResource('patients', PatientController::class)->except('update');

Route::put('patients/{patient}', [PatientController::class, 'update'])
    ->name('patients.update');

Route::patch('patients/{patient}', [PatientController::class, 'partialUpdate'])
    ->name('patients.partial-update');

// Atendimentos (Sprint 4). Nested patient routes do not use withTrashed(): a patient in the trash is a 404.
Route::get('patients/{patient}/consultations', [ConsultationController::class, 'index'])
    ->name('patients.consultations.index');

Route::post('patients/{patient}/consultations', [ConsultationController::class, 'store'])
    ->name('patients.consultations.store');

Route::get('patients/{patient}/timeline', PatientTimelineController::class)
    ->name('patients.timeline');

Route::get('consultations', [ConsultationController::class, 'agenda'])
    ->name('consultations.agenda');

// {consultation} only matches numeric ids and is not found when the patient is in the trash (see Consultation).
Route::get('consultations/{consultation}', [ConsultationController::class, 'show'])
    ->name('consultations.show');

Route::put('consultations/{consultation}', [ConsultationController::class, 'update'])
    ->name('consultations.update');

Route::patch('consultations/{consultation}/status', [ConsultationController::class, 'updateStatus'])
    ->name('consultations.status');

Route::put('consultations/{consultation}/eye-exam', [EyeExamController::class, 'update'])
    ->name('consultations.eye-exam');

Route::get('consultations/{consultation}/prescription', [EyeExamController::class, 'prescription'])
    ->name('consultations.prescription');

// Sprint 5: CEP lookup, patient photo, exam attachments. A patient in the trash is a 404 on all of them.
// {code} rather than {cep}: Scribe would rename `cep/{cep}` to `cep/{id}` in the docs.
Route::get('cep/{code}', [CepController::class, 'show'])
    ->name('cep.show');

// Own limiter: a listing renders one avatar per row, which would otherwise exhaust the API budget
// and make the next GET /patients fail with 429.
Route::get('patients/{patient}/photo', [PatientPhotoController::class, 'show'])
    ->withoutMiddleware(ThrottleRequests::class.':api')
    ->middleware(ThrottleRequests::class.':photos')
    ->name('patients.photo.show');

Route::post('patients/{patient}/photo', [PatientPhotoController::class, 'store'])
    ->name('patients.photo.store');

Route::delete('patients/{patient}/photo', [PatientPhotoController::class, 'destroy'])
    ->name('patients.photo.destroy');

Route::get('consultations/{consultation}/attachments', [AttachmentController::class, 'index'])
    ->name('consultations.attachments.index');

Route::post('consultations/{consultation}/attachments', [AttachmentController::class, 'store'])
    ->name('consultations.attachments.store');

// {attachment} only matches numeric ids and is not found when the patient is in the trash (see ConsultationAttachment).
Route::get('attachments/{attachment}/download', [AttachmentController::class, 'download'])
    ->name('attachments.download');

Route::delete('attachments/{attachment}', [AttachmentController::class, 'destroy'])
    ->name('attachments.destroy');
