<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InsightController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\ModuleStubController;
use App\Http\Controllers\OtpController;
use Illuminate\Support\Facades\Route;

/**
 * FROZEN endpoint index (specs/14). Agents: implement your resource controller
 * and swap ONLY your lines — never reorder or rename another stream's routes.
 */
Route::prefix('v1')->group(function () {
    Route::get('/health', fn () => response()->json(['ok' => true, 'time' => now()->toIso8601String()]));

    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('/auth/refresh', [AuthController::class, 'refresh']);
    Route::post('/auth/otp/send', [OtpController::class, 'send'])->middleware('throttle:5,1');
    Route::post('/auth/otp/verify', [OtpController::class, 'verify'])->middleware('throttle:10,1');
    Route::get('/s/{token}', [ModuleStubController::class]); // Agent C: public survey respond page

    Route::middleware('auth:api')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        Route::get('/dashboard/summary', [DashboardController::class, 'summary']);
        Route::get('/insights/clients/{id}', [InsightController::class, 'client']);
        Route::get('/insights/opportunities/{id}', [InsightController::class, 'opportunity']);

        // Step 1 — Lead & Client Tracking (specs/04). Other resources stay 501 stubs for their steps.
        Route::apiResource('leads', LeadController::class);
        Route::post('leads/{lead}/convert', [LeadController::class, 'convert']);
        Route::apiResource('clients', ClientController::class);
        Route::get('clients/{client}/contacts', [ClientController::class, 'contacts']);
        Route::post('clients/{client}/contacts', [ClientController::class, 'storeContact']);
        Route::apiResource('opportunities', ModuleStubController::class);  // Agent B
        Route::apiResource('activities', ModuleStubController::class);     // Agent D
        Route::apiResource('survey-templates', ModuleStubController::class); // Agent C
        Route::apiResource('surveys', ModuleStubController::class);        // Agent C
        Route::apiResource('followups', ModuleStubController::class);      // Agent E
        Route::apiResource('notifications', ModuleStubController::class)->only(['index', 'show']); // Agent E
        Route::apiResource('users', ModuleStubController::class)->only(['index', 'store', 'show', 'update']); // Agent F
        Route::get('/reports/weekly', [ModuleStubController::class]);      // Agent G
        Route::get('/reports/monthly', [ModuleStubController::class]);     // Agent G
    });
});
