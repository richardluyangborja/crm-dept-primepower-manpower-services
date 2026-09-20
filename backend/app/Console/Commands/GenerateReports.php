<?php

namespace App\Console\Commands;

use App\Models\Report;
use App\Models\User;
use App\Services\Contracts\NotifyServiceInterface;
use App\Services\ReportService;
use Illuminate\Console\Command;

class GenerateReports extends Command
{
    protected $signature = 'reports:generate
        {--type=weekly : Pack type (weekly|monthly)}
        {--team= : Limit to a single team id}
        {--notify : Send managers an in-app notice (mock mail in v1)}';

    protected $description = 'Build management report packs (specs/15) and optionally notify managers';

    public function handle(ReportService $reports, NotifyServiceInterface $notify): int
    {
        $type = $this->option('type');
        if (! in_array($type, ['weekly', 'monthly'], true)) {
            $this->error('Type must be weekly or monthly.');
            return self::FAILURE;
        }
        $days = $type === 'weekly' ? 7 : 30;
        $from = now()->subDays($days)->toDateString();
        $to = now()->toDateString();

        // One pack per team in scope (plus the org-wide pack when no team filter).
        $teamIds = $this->option('team') ? [(int) $this->option('team')] : [null];
        $system = User::where('role', 'superadmin')->orderBy('id')->first();
        $count = 0;
        foreach ($teamIds as $teamId) {
            $pack = $reports->pack($system, $type, $from, $to, $teamId);
            Report::create([
                'type' => $type,
                'period_from' => $from,
                'period_to' => $to,
                'team_id' => $teamId,
                'payload' => $pack,
                'generated_by' => $system?->id,
            ]);
            $count++;
        }

        if ($this->option('notify')) {
            $managers = User::whereIn('role', ['manager', 'admin', 'superadmin'])
                ->where('is_active', true)
                ->when($this->option('team'), fn ($q) => $q->where('team_id', $this->option('team')))
                ->get();
            foreach ($managers as $manager) {
                $notify->send($manager->id, 'report', ucfirst($type).' pack ready',
                    "Period {$from} → {$to}. Download CSV or print from Reports.", '/reports');
            }
            $this->info("Notified {$managers->count()} manager(s) (mock mail in v1).");
        }

        $this->info("Generated {$count} {$type} pack(s) for {$from} → {$to}.");

        return self::SUCCESS;
    }
}
