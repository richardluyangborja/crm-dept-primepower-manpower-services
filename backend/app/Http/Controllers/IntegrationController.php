<?php

namespace App\Http\Controllers;

use App\Traits\ApiResponse;
use Illuminate\Http\Request;

/** Step 6 (specs/09 + 11): integration status per dept; v1 is mock-only. */
class IntegrationController extends Controller
{
    use ApiResponse;

    protected const SERVICES = [
        'job_orders' => ['dept' => 'Client acquisition, recruitment, deployment', 'contract' => 'JobOrderServiceInterface', 'fixture' => 'job_orders.json'],
        'billing' => ['dept' => 'Financial management', 'contract' => 'BillingServiceInterface', 'fixture' => 'invoices.json'],
        'workforce' => ['dept' => 'HR info & operations', 'contract' => 'WorkforceServiceInterface', 'fixture' => 'headcount.json'],
        'notify' => ['dept' => 'Internal notifications', 'contract' => 'NotifyServiceInterface', 'fixture' => 'mock_outbox.json'],
        'otp' => ['dept' => 'Auth second factor (v2)', 'contract' => 'OtpServiceInterface', 'fixture' => null],
        'ai' => ['dept' => 'BI & analytics (Step 7)', 'contract' => 'AiServiceInterface', 'fixture' => 'ai/baseline.json'],
    ];

    public function status(Request $request)
    {
        if (! in_array($request->user()->role, ['superadmin', 'admin'], true)) {
            return $this->fail('Only superadmin/admin can view integrations.', 403);
        }
        $mode = config('integrations.mode', 'mock');
        $rows = [];
        foreach (self::SERVICES as $key => $meta) {
            $fixture = $meta['fixture'] ? database_path('fixtures/'.$meta['fixture']) : null;
            $rows[] = $meta + [
                'key' => $key,
                'mode' => $mode,
                'fixture_present' => $fixture ? file_exists($fixture) : null,
                'test_url' => "/api/v1/integrations/{$key}/test",
            ];
        }

        return $this->ok(['mode' => $mode, 'services' => $rows]);
    }

    public function test(string $service, Request $request)
    {
        if (! in_array($request->user()->role, ['superadmin', 'admin'], true)) {
            return $this->fail('Only superadmin/admin can test integrations.', 403);
        }
        if (! isset(self::SERVICES[$service])) {
            return $this->fail('Unknown integration.', 404);
        }
        // Mock test-connection: resolve the contract and ping a safe read.
        $map = [
            'job_orders' => [\App\Services\Contracts\JobOrderServiceInterface::class, fn ($s) => $s->deploymentStatus(0)],
            'billing' => [\App\Services\Contracts\BillingServiceInterface::class, fn ($s) => $s->paymentStatus(0)],
            'workforce' => [\App\Services\Contracts\WorkforceServiceInterface::class, fn ($s) => $s->headcountByClient(0)],
            'notify' => [\App\Services\Contracts\NotifyServiceInterface::class, fn ($s) => 'notify channel writable (no send performed)'],
            'otp' => [\App\Services\Contracts\OtpServiceInterface::class, fn ($s) => 'otp mock ready (OTP_MODE='.config('otp.mode', 'mock').')'],
            'ai' => [\App\Services\Contracts\AiServiceInterface::class, fn ($s) => $s->sentiment('Mabilis ang deployment, salamat!')],
        ];
        try {
            $result = $map[$service][1](app($map[$service][0]));
        } catch (\Throwable $e) {
            return $this->fail('Test connection failed: '.$e->getMessage(), 502);
        }

        return $this->ok(['service' => $service, 'mode' => config('integrations.mode', 'mock'), 'result' => $result], 'Test connection OK (mock).');
    }

    public function updateMode(Request $request)
    {
        if ($request->user()->role !== 'superadmin') {
            return $this->fail('Only superadmin can change integration mode.', 403);
        }
        $request->validate(['mode' => ['required', 'in:mock,live']]);
        if ($request->input('mode') === 'live') {
            return $this->fail('Live integrations are not available in v1 — contracts stay on mocks until Step 9 hardening.', 422);
        }

        return $this->ok(['mode' => 'mock'], 'Integrations already on mock.');
    }
}
