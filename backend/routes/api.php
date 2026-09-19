<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FollowupController;
use App\Http\Controllers\ActivityController;
use App\Http\Controllers\InsightController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\OpportunityController;
use App\Http\Controllers\ModuleStubController;
use App\Http\Controllers\NotificationController;
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
        // Step 2 — Opportunity Pipeline (specs/05).
        Route::apiResource('opportunities', OpportunityController::class);
        Route::post('opportunities/{opportunity}/move', [OpportunityController::class, 'move']);
        Route::post('opportunities/{opportunity}/win', [OpportunityController::class, 'win']);
        Route::post('opportunities/{opportunity}/lose', [OpportunityController::class, 'lose']);
        // Step 4 — Communication History (specs/07).
        Route::get('message-templates', [ActivityController::class, 'templates']);
        Route::apiResource('activities', ActivityController::class);
        Route::apiResource('survey-templates', ModuleStubController::class); // Agent C
        Route::apiResource('surveys', ModuleStubController::class);        // Agent C
        // Step 3 — Follow-up Reminders (specs/08).
        Route::apiResource('followups', FollowupController::class);
        Route::post('followups/{followup}/done', [FollowupController::class, 'done']);
        Route::post('followups/{followup}/snooze', [FollowupController::class, 'snooze']);
        Route::post('followups/{followup}/escalate', [FollowupController::class, 'escalate']);
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::post('notifications/read-all', [NotificationController::class, 'readAll']);
        Route::post('notifications/{notification}/read', [NotificationController::class, 'read']);
        Route::apiResource('users', ModuleStubController::class)->only(['index', 'store', 'show', 'update']); // Agent F
        Route::get('/reports/weekly', [ModuleStubController::class]);      // Agent G
        Route::get('/reports/monthly', [ModuleStubController::class]);     // Agent G
    });
});
