<?php

namespace App\Console\Commands;

use App\Models\Quote;
use App\Services\LocationService;
use Illuminate\Console\Command;

class PruneData extends Command
{
    protected $signature = 'courier:prune';

    protected $description = 'Apply retention: rider location history, expired quotes';

    public function handle(LocationService $locations): int
    {
        $loc = $locations->prune();
        $quotes = Quote::where('expires_at', '<', now()->subDays(7))->whereNotIn('id', fn ($q) => $q->select('quote_id')->from('shipments')->whereNotNull('quote_id'))->delete();
        $this->info("Pruned {$loc} rider location(s), {$quotes} expired quote(s).");

        return self::SUCCESS;
    }
}
