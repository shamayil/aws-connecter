<?php

namespace App\Console\Commands\Billing;

use App\Action\Billing\LambdaFunctionsBilling;
use Illuminate\Console\Command;

class LambdaBillByFunctions extends Command
{
    protected $signature = 'lambda:bill';

    public function handle()
    {
        $enviroments = ['master', 'production', 'staging'];

        foreach ($enviroments as $env) {
            (new LambdaFunctionsBilling())->get($env);
        }

    }

}
