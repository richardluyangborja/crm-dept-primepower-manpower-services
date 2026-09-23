<?php

namespace App\Services\Mocks;

use App\Services\Contracts\AttendanceServiceInterface;

/**
 * Core-2 timekeeping mock. Daily statuses are deterministic per user+date
 * (stable demos, green tests); allowances/overrides come from fixtures/hr.json.
 */
class MockAttendanceService implements AttendanceServiceInterface
{
    public function monthly(int $userId, string $month): array
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            return ['days' => [], 'summary' => $this->emptySummary(), 'leave' => $this->leave($userId), 'mock' => true];
        }
        [$y, $m] = array_map('intval', explode('-', $month));
        // NOTE: date('t') instead of cal_days_in_month() — deployment PHP
        // builds may strip ext-calendar, which 500'd every HR endpoint in prod.
        $daysIn = (int) date('t', strtotime(sprintf('%04d-%02d-01', $y, $m)));
        $today = date('Y-m-d');

        $days = [];
        $present = $late = $absent = $leave = 0;
        $lateMins = 0;
        $lateCount = 0;
        for ($d = 1; $d <= $daysIn; $d++) {
            $date = sprintf('%04d-%02d-%02d', $y, $m, $d);
            $dow = (int) date('N', strtotime($date));
            if ($dow >= 6 || $date > $today) {
                continue; // weekends + future are not workdays
            }
            $h = crc32($userId . '|' . $date) % 100;
            if ($h < 4) {
                $status = 'leave';
                $leave++;
                $days[] = ['date' => $date, 'status' => $status, 'check_in' => null, 'kind' => ($h % 2 ? 'vacation' : 'sick')];
            } elseif ($h < 6) {
                $status = 'absent';
                $absent++;
                $days[] = ['date' => $date, 'status' => $status, 'check_in' => null, 'kind' => null];
            } elseif ($h < 13) {
                $status = 'late';
                $late++;
                $mins = 5 + ($h % 40);
                $lateMins += $mins;
                $lateCount++;
                $days[] = ['date' => $date, 'status' => $status, 'check_in' => sprintf('09:%02d', $mins), 'kind' => null];
            } else {
                $status = 'present';
                $present++;
                $days[] = ['date' => $date, 'status' => $status, 'check_in' => sprintf('08:%02d', $h % 50), 'kind' => null];
            }
        }
        $workdays = $present + $late + $absent + $leave;

        return [
            'days' => $days,
            'summary' => [
                'present' => $present,
                'late' => $late,
                'absent' => $absent,
                'leave' => $leave,
                'workdays' => $workdays,
                'attendance_pct' => $workdays > 0 ? round(($present + $late) / $workdays * 100, 1) : null,
                'punctuality_pct' => ($present + $late) > 0 ? round($present / ($present + $late) * 100, 1) : null,
                'avg_late_mins' => $lateCount > 0 ? round($lateMins / $lateCount, 1) : 0,
            ],
            'leave' => $this->leave($userId),
            'mock' => true,
        ];
    }

    protected function emptySummary(): array
    {
        return ['present' => 0, 'late' => 0, 'absent' => 0, 'leave' => 0, 'workdays' => 0, 'attendance_pct' => null, 'punctuality_pct' => null, 'avg_late_mins' => 0];
    }

    protected function config(): array
    {
        $file = database_path('fixtures/hr.json');
        if (file_exists($file)) {
            return json_decode(file_get_contents($file), true) ?? [];
        }

        return [];
    }

    protected function leave(int $userId): array
    {
        $cfg = $this->config();
        $allow = $cfg['leave_allowance'] ?? ['vacation' => 15, 'sick' => 15];
        $used = $cfg['leave_used_fallback'] ?? ['vacation' => 3, 'sick' => 1];
        $email = \App\Models\User::whereKey($userId)->value('email');
        if ($email && isset($cfg['overrides'][$email]['leave_used'])) {
            $used = $cfg['overrides'][$email]['leave_used'];
        }

        return [
            'vacation' => ['allowed' => $allow['vacation'], 'used' => $used['vacation'] ?? 0],
            'sick' => ['allowed' => $allow['sick'], 'used' => $used['sick'] ?? 0],
        ];
    }
}
