<?php

namespace App\Console\Commands;

use App\Services\Updates\UpdateException;
use App\Services\Updates\UpdateService;
use App\Support\Audit;
use Illuminate\Console\Command;

/**
 * Safety net for one-click updates: if the admin's browser closed before the finishing
 * step ran, the scheduler completes it (migrations, caches, maintenance off).
 */
class FinishUpdate extends Command
{
    protected $signature = 'courier:finish-update {--now : Finish immediately instead of waiting for the browser}';

    protected $description = 'Finish a one-click update that was left half-way';

    public function handle(UpdateService $updates): int
    {
        $state = $updates->pending();
        if (! $state || $state['status'] !== 'files_installed') {
            return self::SUCCESS;
        }
        // Give the browser's own finishing request a head start.
        if (! $this->option('now') && now()->diffInSeconds($state['started_at'], true) < 90) {
            return self::SUCCESS;
        }
        try {
            $done = $updates->finish();
        } catch (UpdateException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        Audit::log('system.updated', $done, ['from' => $done->from_version, 'to' => $done->to_version, 'finished_by' => 'scheduler'], $done->user_id);
        $this->info("Finished update to {$done->to_version}.");

        return self::SUCCESS;
    }
}
