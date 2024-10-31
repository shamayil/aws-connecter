<?php

namespace App\Console\Commands\Billing;

use App\Action\Billing\ForcastDailyYesterdayBilling;
use App\Action\Billing\ForcastMonthlyBilling;
use Illuminate\Console\Command;

class ForcastBillFunctions extends Command
{
    protected $signature = 'forcast:bill';

    public function handle()
    {
        $enviroments = ['master', 'production', 'staging'];

        foreach ($enviroments as $env) {

            (new ForcastDailyYesterdayBilling())->get($env);
            (new ForcastMonthlyBilling())->get($env);

            // (new ForcastDailyBilling())->get($env);
            // (new ForcastLastMonthBilling())->get($env);
        }
    }

}
