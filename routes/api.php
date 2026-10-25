<?php

use App\Http\Controllers\ApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| JSON API (prefix /api, bearer-token auth via Laravel Sanctum)
|--------------------------------------------------------------------------
| Always send:  Accept: application/json
| Optional on project endpoints:  ?status_date=YYYY-MM-DD
*/
Route::post('/login', [ApiController::class, 'login'])->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [ApiController::class, 'logout']);
    Route::get('/me', [ApiController::class, 'me']);

    Route::get('/projects', [ApiController::class, 'projects']);
    Route::get('/projects/{project}', [ApiController::class, 'project']);
    Route::get('/projects/{project}/dashboard', [ApiController::class, 'dashboard']);
    Route::get('/projects/{project}/schedule', [ApiController::class, 'schedule']);
    Route::get('/projects/{project}/evm', [ApiController::class, 'evm']);
    Route::get('/projects/{project}/requirements', [ApiController::class, 'requirements']);
    Route::get('/projects/{project}/change-requests', [ApiController::class, 'changeRequests']);
    Route::get('/projects/{project}/defects', [ApiController::class, 'defects']);
    Route::get('/projects/{project}/risks', [ApiController::class, 'risks']);

    Route::get('/change-requests/{changeRequest}/impact', [ApiController::class, 'changeRequestImpact']);
});
