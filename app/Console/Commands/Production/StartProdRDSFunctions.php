<?php

namespace App\Console\Commands\Production;

use Aws\Exception\AwsException;
use Aws\Rds\RdsClient;
use GuzzleHttp\Client;
use Illuminate\Console\Command;

class StartProdRDSFunctions extends Command
{
    protected $signature = 'prod:rds:start';

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

        $maxRetries = 5;
        $attempt = 0;

        while ($attempt < $maxRetries) {
            try {
                $client->startDBCluster([
                    'DBClusterIdentifier' => 'iposg-data-cluster',
                    'DBInstanceIdentifier' => "iposg-data-cluster-instance-1",
                ]);

                $this->slack();
                break;
            } catch (AwsException $e) {
                $attempt++;
                if ($attempt >= $maxRetries) {
                    return [
                        'data' => [],
                        'status' => 'ERROR',
                        'message' => 'Error on start RDS.',
                        'errors' => $e->getAwsErrorMessage()
                    ];
                }
                sleep(5);
            }
        }
    }

    function slack()
    {
        $client = new Client();

        $message = [
            'attachments' => [
                [
                    'title' => 'RDS Service - Production',
                    'text' => 'RDS cluster for data have been started.'
                ],
            ],
        ];

        $client->post('https://hooks.slack.com/services/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', [
            'json' => $message,
        ]);
    }

}
