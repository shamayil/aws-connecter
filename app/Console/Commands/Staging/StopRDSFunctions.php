<?php

namespace App\Console\Commands\Staging;

use Aws\Exception\AwsException;
use Aws\Rds\RdsClient;
use GuzzleHttp\Client;
use Illuminate\Console\Command;

class StopRDSFunctions extends Command
{
    protected $signature = 'rds:stop';

    public function handle()
    {
        $client = new RdsClient([
            'region' => config('aws.stage_region'),
            'version' => 'latest',
            'credentials' => [
                'key' => config('aws.stage_key'),
                'secret' => config('aws.stage_secret'),
            ],
        ]);

        $result = $client->describeDBInstances();
        $dbInstances = $result['DBInstances'];
        $dbInstanceIdentifier = $dbInstances[0]['DBInstanceIdentifier'];
        $dbClusterIdentifier = $dbInstances[0]['DBClusterIdentifier'];

        try {
            $result = $client->stopDBCluster([
                'DBInstanceIdentifier' => $dbInstanceIdentifier,
                'DBClusterIdentifier' => $dbClusterIdentifier
            ]);

            $this->slack();

            return $result;
        } catch (AwsException $e) {
            return [
                'data' => [],
                'status' => 'ERROR',
                'message' => 'Error on stop RDS.',
                'errors' => $e->getAwsErrorMessage()
            ];
        }
    }

    function slack()
    {
        $client = new Client();

        $message = [
            'attachments' => [
                [
                    'title' => 'RDS Service - Staging',
                    'text' => 'All the RDS clusters and services have been stopped.'
                ],
            ],
        ];

        $client->post('https://hooks.slack.com/services/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', [
            'json' => $message,
        ]);
    }

}
