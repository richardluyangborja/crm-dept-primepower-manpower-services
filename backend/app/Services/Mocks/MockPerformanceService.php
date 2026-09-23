<?php

namespace App\Services\Mocks;

use App\Models\Activity;
use App\Models\Followup;
use App\Models\Opportunity;
use App\Services\Contracts\AttendanceServiceInterface;
use App\Services\Contracts\PerformanceServiceInterface;

/**
 * Core-2 performance mock. Real CRM output (trailing period) blended with
 * mock HR modifiers (attendance, punctuality, quarterly rating).
 */
class MockPerformanceService implements PerformanceServiceInterface
{
    public function __construct(protected AttendanceServiceInterface $attendance) {}

    public function score(int $userId, string $period): array
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            return ['crm' => [], 'hr' => [], 'composite' => null, 'mock' => true];
        }
        [$y, $m] = array_map('intval', explode('-', $period));
        $from = sprintf('%04d-%02d-01 00:00:00', $y, $m);
        $to = date('Y-m-t 23:59:59', strtotime($from));

        $wonValue = (int) Opportunity::where('owner_id', $userId)->where('stage', 'won')
            ->whereBetween('won_at', [$from, $to])->sum('value_centavos');
        $touches = Activity::where('owner_id', $userId)->whereBetween('occurred_at', [$from, $to])->count();
        $done = Followup::where('owner_id', $userId)->where('status', 'done')
            ->whereBetween('updated_at', [$from, $to])->count();
        $open = Followup::where('owner_id', $userId)->whereIn('status', ['open', 'snoozed'])
            ->where('due_at', '<=', $to)->count();
        $overdue = Followup::where('owner_id', $userId)->whereIn('status', ['overdue', 'escalated'])->count();
        $completion = ($done + $open) > 0 ? round($done / ($done + $open) * 100, 1) : null;

        $att = $this->attendance->monthly($userId, $period);
        $cfg = $this->config();
        $targets = $cfg['targets'] ?? ['won_value_centavos' => 50000000, 'touches' => 40, 'completion_pct' => 90];
        $weights = $cfg['weights'] ?? ['won' => 0.35, 'touches' => 0.2, 'completion' => 0.2, 'attendance' => 0.15, 'punctuality' => 0.1];

        $norm = fn ($v, $t) => $t > 0 ? min(100, round($v / $t * 100, 1)) : null;
        $parts = [
            'won' => $norm($wonValue, $targets['won_value_centavos']),
            'touches' => $norm($touches, $targets['touches']),
            'completion' => $completion !== null ? $norm($completion, $targets['completion_pct']) : null,
            'attendance' => $att['summary']['attendance_pct'],
            'punctuality' => $att['summary']['punctuality_pct'],
        ];
        $scored = array_filter($parts, fn ($v) => $v !== null);
        $composite = $scored === [] ? null : round(
            array_sum(array_map(fn ($k) => $scored[$k] * ($weights[$k] ?? 0), array_keys($scored)))
            / max(0.01, array_sum(array_map(fn ($k) => $weights[$k] ?? 0, array_keys($scored)))),
            1
        );

        return [
            'crm' => [
                'won_value_centavos' => $wonValue,
                'touches' => $touches,
                'followups_done' => $done,
                'completion_pct' => $completion,
                'overdue' => $overdue,
            ],
            'hr' => [
                'attendance_pct' => $att['summary']['attendance_pct'],
                'punctuality_pct' => $att['summary']['punctuality_pct'],
                'leave_used' => $att['leave'],
                'rating' => $this->rating($userId, $composite, $cfg),
            ],
            'parts' => $parts,
            'composite' => $composite,
            'mock' => true,
        ];
    }

    protected function config(): array
    {
        $file = database_path('fixtures/hr.json');
        if (file_exists($file)) {
            return json_decode(file_get_contents($file), true) ?? [];
        }

        return [];
    }

    protected function rating(int $userId, ?float $composite, array $cfg): ?float
    {
        $email = \App\Models\User::whereKey($userId)->value('email');
        if ($email && isset($cfg['overrides'][$email]['rating'])) {
            return (float) $cfg['overrides'][$email]['rating'];
        }
        if ($composite === null) {
            return null;
        }
        foreach ($cfg['rating_bands'] ?? [] as [$min, $rating]) {
            if ($composite >= $min) {
                return (float) $rating;
            }
        }

        return 3.0;
    }
}
