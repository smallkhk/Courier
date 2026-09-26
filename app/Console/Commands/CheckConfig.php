<?php

namespace App\Console\Commands;

use App\Support\ConfigCheck;
use Illuminate\Console\Command;

class CheckConfig extends Command
{
    protected $signature = 'courier:check-config';

    protected $description = 'Report missing or unsafe configuration for the current environment';

    public function handle(): int
    {
        $problems = ConfigCheck::problems();
        foreach ($problems as [$level, $msg]) {
            $level === 'error' ? $this->error("ERROR  {$msg}") : $this->warn("WARN   {$msg}");
        }
        if (! $problems) {
            $this->info('Configuration looks good.');
        }

        return collect($problems)->contains(fn ($p) => $p[0] === 'error') ? self::FAILURE : self::SUCCESS;
    }
}
