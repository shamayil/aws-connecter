<?php

namespace App\Console\Commands\Billing;

use App\Action\Billing\BlindedDailyBilling;
use Illuminate\Console\Command;

class GetBillFunctions extends Command
{
    protected $signature = 'get:bill';

    public function handle()
    {
        $enviroments = ['master', 'production', 'staging'];

        foreach ($enviroments as $env) {
            (new BlindedDailyBilling())->get($env);
        }
    }

}
