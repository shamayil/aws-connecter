<?php

namespace App\Console\Commands\Production;

use Aws\Exception\AwsException;
use Aws\Rds\RdsClient;
use GuzzleHttp\Client;
use Illuminate\Console\Command;

class StopProdRDSFunctions extends Command
{
    protected $signature = 'prod:rds:stop';

    public function handle()
    {
        $client = new RdsClient([
            'region' => config('aws.prod_region'),
            'version' => 'latest',
            'credentials' => [
                'key' => config('aws.prod_key'),
                'secret' => config('aws.prod_secret'),
            ],
        ]);

        try {
            $client->stopDBCluster([
                'DBClusterIdentifier' => 'iposg-data-cluster',
                'DBInstanceIdentifier' => "iposg-data-cluster-instance-1"
            ]);
            $this->slack();
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
                    'title' => 'RDS Service - Production',
                    'text' => 'RDS cluster for data have been stopped.'
                ],
            ],
        ];

        $client->post('https://hooks.slack.com/services/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', [
            'json' => $message,
        ]);
    }

}
