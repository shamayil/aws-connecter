<?php

namespace App\Console\Commands\Billing;

use App\Action\Billing\S3Billing;
use Illuminate\Console\Command;

class BillingS3Functions extends Command
{
    protected $signature = 'billing:s3';

    public function handle()
    {
        $enviroments = ['production', 'staging'];

        foreach ($enviroments as $env) {
            (new S3Billing())->get($env);
        }
    }
}
