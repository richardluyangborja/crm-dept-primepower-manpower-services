<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FollowupController;
use App\Http\Controllers\ActivityController;
use App\Http\Controllers\InsightController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\OpportunityController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OtpController;
use Illuminate\Support\Facades\Route;

/**
 * FROZEN endpoint index (specs/14). Agents: implement your resource controller
 * and swap ONLY your lines — never reorder or rename another stream's routes.
 */
Route::prefix('v1')->group(function () {
    Route::get('/health', fn () => response()->json(['ok' => true, 'time' => now()->toIso8601String()]));

    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:auth-login');
    Route::post('/auth/refresh', [AuthController::class, 'refresh']);
    Route::post('/auth/otp/send', [OtpController::class, 'send'])->middleware('throttle:otp-send');
    Route::post('/auth/otp/verify', [OtpController::class, 'verify'])->middleware('throttle:otp-verify');
    // Step 5 — Public survey respond (specs/06): no auth, 30/min per IP.
    Route::get('/s/{token}', [\App\Http\Controllers\SurveyController::class, 'publicShow'])->middleware('throttle:survey-public');
    Route::post('/s/{token}/respond', [\App\Http\Controllers\SurveyController::class, 'respond'])->middleware('throttle:survey-public');
    Route::put('/s/{token}/respond', [\App\Http\Controllers\SurveyController::class, 'updateResponse'])->middleware('throttle:survey-public');

    Route::middleware(['auth:api', \App\Http\Middleware\SessionTimeout::class])->group(function () {
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
        Route::get('clients/{client}/operations', [ClientController::class, 'operations']); // v2 journey A
        // v2 journey A — visible mock job-order timeline (specs/18).
        Route::apiResource('job-orders', \App\Http\Controllers\JobOrderController::class)->only(['index', 'show']);
        Route::post('job-orders/{job_order}/advance', [\App\Http\Controllers\JobOrderController::class, 'advance']);
        // Step 2 — Opportunity Pipeline (specs/05).
        Route::apiResource('opportunities', OpportunityController::class);
        Route::post('opportunities/{opportunity}/move', [OpportunityController::class, 'move']);
        Route::post('opportunities/{opportunity}/win', [OpportunityController::class, 'win']);
        Route::post('opportunities/{opportunity}/lose', [OpportunityController::class, 'lose']);
        // Step 4 — Communication History (specs/07).
        Route::get('message-templates', [ActivityController::class, 'templates']);
        Route::apiResource('activities', ActivityController::class);
        // Step 5 — Templates + Surveys (specs/06).
        Route::apiResource('survey-templates', \App\Http\Controllers\SurveyTemplateController::class);
        Route::apiResource('surveys', \App\Http\Controllers\SurveyController::class)->only(['index', 'store', 'show']);
        Route::get('surveys-analytics', [\App\Http\Controllers\SurveyController::class, 'analytics']);
        // Step 3 — Follow-up Reminders (specs/08).
        Route::apiResource('followups', FollowupController::class);
        Route::post('followups/{followup}/done', [FollowupController::class, 'done']);
        Route::post('followups/{followup}/snooze', [FollowupController::class, 'snooze']);
        Route::post('followups/{followup}/escalate', [FollowupController::class, 'escalate']);
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::post('notifications/read-all', [NotificationController::class, 'readAll']);
        Route::post('notifications/{notification}/read', [NotificationController::class, 'read']);
        // Step 6 — Accounts & Settings (specs/09).
        Route::apiResource('users', \App\Http\Controllers\UserController::class)->only(['index', 'store', 'show', 'update']);
        Route::post('users/{user}/deactivate', [\App\Http\Controllers\UserController::class, 'deactivate']);
        Route::post('users/{user}/reset-password', [\App\Http\Controllers\UserController::class, 'resetPassword']);
        Route::get('me/preferences', [\App\Http\Controllers\UserController::class, 'preferences']);
        Route::put('me/preferences', [\App\Http\Controllers\UserController::class, 'updatePreferences']);
        Route::post('me/password', [\App\Http\Controllers\UserController::class, 'changePassword']);
        Route::get('me/logins', [\App\Http\Controllers\UserController::class, 'logins']);
        Route::get('users-sessions', [\App\Http\Controllers\UserController::class, 'sessions']);
        Route::delete('users-sessions/{user_session}', [\App\Http\Controllers\UserController::class, 'revokeSession']);
        Route::apiResource('teams', \App\Http\Controllers\TeamController::class)->only(['index', 'store', 'show', 'update']);
        Route::get('settings', [\App\Http\Controllers\SettingController::class, 'index']);
        Route::put('settings', [\App\Http\Controllers\SettingController::class, 'update']);
        Route::get('integrations/status', [\App\Http\Controllers\IntegrationController::class, 'status']);
        Route::get('integrations/{service}/test', [\App\Http\Controllers\IntegrationController::class, 'test']);
        Route::put('integrations/mode', [\App\Http\Controllers\IntegrationController::class, 'updateMode']);
        Route::get('exports/{entity}.csv', [\App\Http\Controllers\ExportController::class, 'csv']);
        // Step 7 — AI Analytics + Reports (specs/15).
        Route::get('/reports/weekly', [\App\Http\Controllers\ReportController::class, 'weekly']);
        Route::get('/reports/monthly', [\App\Http\Controllers\ReportController::class, 'monthly']);
        Route::get('/reports', [\App\Http\Controllers\ReportController::class, 'index']);
        Route::post('/reports/generate', [\App\Http\Controllers\ReportController::class, 'generate']);
        Route::post('/insights/feedback', [\App\Http\Controllers\InsightFeedbackController::class, 'store']);
    });
});
