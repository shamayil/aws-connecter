<?php

namespace App\Console\Commands\Billing;

use App\Action\Billing\CompareCostBilling;
use Illuminate\Console\Command;

class BillingCompareFunctions extends Command
{
    protected $signature = 'billing:compare';

    public function handle()
    {
        $enviroments = ['master', 'production', 'staging'];

        foreach ($enviroments as $env) {
            (new CompareCostBilling())->compare($env);
        }
    }
}
