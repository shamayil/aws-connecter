<?php

namespace App\Console\Commands\Tag;

use Aws\Rds\RdsClient;
use Illuminate\Console\Command;

class RDSTagFunctions extends Command
{
    protected $signature = 'rds:tag';

    public function handle()
    {
        $enviroments = ['production', 'staging'];

        foreach ($enviroments as $env) {

            if ($env == 'production') {
                $rds = new RdsClient([
                    'region' => config('aws.prod_region'),
                    'version' => 'latest',
                    'credentials' => [
                        'key' => config('aws.prod_key'),
                        'secret' => config('aws.prod_secret'),
                    ],
                ]);
            } else if ($env == 'staging') {
                $rds = new RdsClient([
                    'region' => config('aws.stage_region'),
                    'version' => 'latest',
                    'credentials' => [
                        'key' => config('aws.stage_key'),
                        'secret' => config('aws.stage_secret'),
                    ],
                ]);
            } else {
                return;
            }

            $result = $rds->describeDBInstances();
            $instances = $result['DBInstances'];

            foreach ($instances as $instance) {

                $rds->addTagsToResource([
                    'ResourceName' => $instance['DBInstanceArn'],
                    'Tags' => [
                        [
                            'Key' => 'Name',
                            'Value' => $instance['DBInstanceIdentifier']
                        ],
                        [
                            'Key' => 'Environment',
                            'Value' => env('APP_ENV')
                        ],
                    ],
                ]);
            }
        }
    }
}
