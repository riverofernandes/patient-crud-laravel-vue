<?php

use App\Http\Controllers\HealthController;
use App\Http\Controllers\PatientController;
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

Route::apiResource('patients', PatientController::class);
