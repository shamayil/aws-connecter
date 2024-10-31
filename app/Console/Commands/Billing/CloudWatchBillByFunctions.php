<?php

namespace App\Console\Commands\Billing;

use App\Action\Billing\CloudWatchBilling;
use Illuminate\Console\Command;

class CloudWatchBillByFunctions extends Command
{
    protected $signature = 'cloudwatch:bill';

    public function handle()
    {
        $enviroments = ['master', 'production', 'staging'];

        foreach ($enviroments as $env) {
            (new CloudWatchBilling())->get($env);
        }
    }

}
