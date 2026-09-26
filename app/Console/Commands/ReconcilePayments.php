<?php

namespace App\Console\Commands;

use App\Services\Payments\PaymentService;
use Illuminate\Console\Command;

class ReconcilePayments extends Command
{
    protected $signature = 'payments:reconcile';

    protected $description = 'Verify expired pending payments with the provider; cancel abandoned checkouts';

    public function handle(PaymentService $payments): int
    {
        $this->info('Reconciled '.$payments->reconcileStale().' payment(s).');

        return self::SUCCESS;
    }
}
