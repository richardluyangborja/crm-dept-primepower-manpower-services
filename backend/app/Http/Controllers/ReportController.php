<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Services\ReportService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

/** Step 7 (specs/15): weekly/monthly packs, generate, list. Manager+ only. */
class ReportController extends Controller
{
    use ApiResponse;

    protected function gate(): ?\Illuminate\Http\JsonResponse
    {
        if (! in_array(auth('api')->user()->role, ['manager', 'admin', 'superadmin'], true)) {
            return $this->fail('Reports are available to managers and above.', 403);
        }

        return null;
    }

    protected function period(string $type, Request $request): array
    {
        if ($request->query('from') && $request->query('to')) {
            return [$request->query('from'), $request->query('to')];
        }
        $days = $type === 'weekly' ? 7 : 30;

        return [now()->subDays($days)->toDateString(), now()->toDateString()];
    }

    protected function teamId(Request $request): ?int
    {
        $user = auth('api')->user();
        if ($request->query('team_id')) {
            return (int) $request->query('team_id');
        }

        return $user->role === 'manager' ? $user->team_id : null;
    }

    public function weekly(Request $request, ReportService $service)
    {
        if ($err = $this->gate()) return $err;
        [$from, $to] = $this->period('weekly', $request);

        return $this->ok($service->pack(auth('api')->user(), 'weekly', $from, $to, $this->teamId($request)));
    }

    public function monthly(Request $request, ReportService $service)
    {
        if ($err = $this->gate()) return $err;
        [$from, $to] = $this->period('monthly', $request);

        return $this->ok($service->pack(auth('api')->user(), 'monthly', $from, $to, $this->teamId($request)));
    }

    public function index()
    {
        if ($err = $this->gate()) return $err;

        return $this->paginated(Report::orderByDesc('id')->paginate(15));
    }

    public function generate(Request $request, ReportService $service)
    {
        if ($err = $this->gate()) return $err;
        $data = $request->validate([
            'type' => ['required', 'in:weekly,monthly'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
            'team_id' => ['sometimes', 'integer', 'exists:teams,id'],
        ]);
        $type = $data['type'];
        $from = $data['from'] ?? now()->subDays($type === 'weekly' ? 7 : 30)->toDateString();
        $to = $data['to'] ?? now()->toDateString();
        $pack = $service->pack(auth('api')->user(), $type, $from, $to, $data['team_id'] ?? $this->teamId($request));

        $report = Report::create([
            'type' => $type,
            'period_from' => $from,
            'period_to' => $to,
            'team_id' => $data['team_id'] ?? $this->teamId($request),
            'payload' => $pack,
            'generated_by' => auth('api')->id(),
        ]);
        // v1 mock mail: in-app notification instead of queued email (specs/15).
        app(\App\Services\Contracts\NotifyServiceInterface::class)->send(
            auth('api')->id(), 'report', ucfirst($type).' pack ready', 'Download CSV or print from Reports.', '/reports'
        );

        return $this->created(['id' => $report->id, 'type' => $type], 'Weekly pack ready — download CSV.'.($type === 'weekly' ? '' : 'Monthly pack ready — download CSV.'));
    }
}
