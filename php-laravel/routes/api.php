<?php

use App\Http\Controllers\PatientController;
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

Route::apiResource('patients', PatientController::class);
