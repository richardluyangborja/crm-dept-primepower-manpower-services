<?php

namespace App\Providers;

use App\Services\Contracts\AiServiceInterface;
use App\Services\Contracts\AttendanceServiceInterface;
use App\Services\Contracts\BillingServiceInterface;
use App\Services\Contracts\JobOrderServiceInterface;
use App\Services\Contracts\NotifyServiceInterface;
use App\Services\Contracts\OtpServiceInterface;
use App\Services\Contracts\PerformanceServiceInterface;
use App\Services\Contracts\WorkforceServiceInterface;
use App\Services\Mocks\MockAiService;
use App\Services\Mocks\MockAttendanceService;
use App\Services\Mocks\MockBillingService;
use App\Services\Mocks\MockJobOrderService;
use App\Services\Mocks\MockNotifyService;
use App\Services\Mocks\MockOtpService;
use App\Services\Mocks\MockPerformanceService;
use App\Services\Mocks\MockWorkforceService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // v1: all external/AI deps resolve to mocks (specs/11 + 15 + 16).
        // Flip to live implementations per-service in v2 — no controller changes.
        $this->app->bind(JobOrderServiceInterface::class, MockJobOrderService::class);
        $this->app->bind(BillingServiceInterface::class, MockBillingService::class);
        $this->app->bind(WorkforceServiceInterface::class, MockWorkforceService::class);
        $this->app->bind(NotifyServiceInterface::class, MockNotifyService::class);
        $this->app->bind(OtpServiceInterface::class, MockOtpService::class);
        $this->app->bind(AiServiceInterface::class, MockAiService::class);
        $this->app->bind(AttendanceServiceInterface::class, MockAttendanceService::class);
        $this->app->bind(PerformanceServiceInterface::class, MockPerformanceService::class);
    }

    public function boot(): void
    {
        // Named per-endpoint limiters (specs/16): unnamed `throttle:N,1` limiters
        // share one bucket per user/IP, so login attempts would eat the OTP
        // budget and vice versa. Names isolate each endpoint's budget.
        $byUserOrIp = fn (\Illuminate\Http\Request $r) => $r->user()?->id ?: $r->ip();
        \Illuminate\Support\Facades\RateLimiter::for('auth-login', fn (\Illuminate\Http\Request $r) => \Illuminate\Cache\RateLimiting\Limit::perMinute(10)->by($r->ip()));
        \Illuminate\Support\Facades\RateLimiter::for('otp-send', fn (\Illuminate\Http\Request $r) => \Illuminate\Cache\RateLimiting\Limit::perMinute(5)->by($byUserOrIp($r)));
        \Illuminate\Support\Facades\RateLimiter::for('otp-verify', fn (\Illuminate\Http\Request $r) => \Illuminate\Cache\RateLimiting\Limit::perMinute(10)->by($byUserOrIp($r)));
        \Illuminate\Support\Facades\RateLimiter::for('survey-public', fn (\Illuminate\Http\Request $r) => \Illuminate\Cache\RateLimiting\Limit::perMinute(30)->by($r->ip()));

        // Explicit policy map (Gate also auto-discovers *Policy by convention).
        Gate::policy(\App\Models\Lead::class, \App\Policies\LeadPolicy::class);
        Gate::policy(\App\Models\Client::class, \App\Policies\ClientPolicy::class);
        Gate::policy(\App\Models\Opportunity::class, \App\Policies\OpportunityPolicy::class);
        Gate::policy(\App\Models\Contract::class, \App\Policies\ContractPolicy::class);
        Gate::policy(\App\Models\Followup::class, \App\Policies\FollowupPolicy::class);
        Gate::policy(\App\Models\Notification::class, \App\Policies\NotificationPolicy::class);
        Gate::policy(\App\Models\Activity::class, \App\Policies\ActivityPolicy::class);
        Gate::policy(\App\Models\User::class, \App\Policies\UserPolicy::class);
        Gate::policy(\App\Models\Team::class, \App\Policies\TeamPolicy::class);
        Gate::policy(\App\Models\SurveyTemplate::class, \App\Policies\SurveyTemplatePolicy::class);
        Gate::policy(\App\Models\Survey::class, \App\Policies\SurveyPolicy::class);
        Gate::policy(\App\Models\JobOrder::class, \App\Policies\JobOrderPolicy::class);
        Gate::policy(\App\Models\Invoice::class, \App\Policies\InvoicePolicy::class);
    }
}
