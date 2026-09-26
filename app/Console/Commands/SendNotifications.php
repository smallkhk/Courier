<?php

namespace App\Console\Commands;

use App\Models\NotificationLog;
use App\Services\Notifications\NotificationService;
use Illuminate\Console\Command;

class SendNotifications extends Command
{
    protected $signature = 'notifications:send {--limit=50}';

    protected $description = 'Deliver queued email/SMS notifications with retry and backoff';

    public function handle(NotificationService $service): int
    {
        // Recover rows left "sending" by a run that crashed mid-delivery.
        NotificationLog::where('status', 'sending')->where('updated_at', '<', now()->subMinutes(10))
            ->update(['status' => 'queued', 'next_attempt_at' => now()]);

        $stats = $service->processDue((int) $this->option('limit'));
        $this->info("sent={$stats['sent']} retrying={$stats['retrying']} failed={$stats['failed']}");

        return self::SUCCESS;
    }
}
