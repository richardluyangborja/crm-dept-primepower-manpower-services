<?php

namespace App\Console\Commands;

use App\Services\FollowupService;
use Illuminate\Console\Command;

class DispatchDueReminders extends Command
{
    protected $signature = 'reminders:dispatch';
    protected $description = 'Send due-soon notices, flip overdue, nudge at 24h, auto-escalate at 72h (specs/08)';

    public function handle(FollowupService $service): int
    {
        $counts = $service->dispatch();
        $this->info('reminders dispatched: '.json_encode($counts));

        return self::SUCCESS;
    }
}
