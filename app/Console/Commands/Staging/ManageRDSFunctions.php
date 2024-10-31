<?php

namespace App\Console\Commands\Staging;

use Aws\Exception\AwsException;
use Aws\Rds\RdsClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ManageRDSFunctions extends Command
{
    protected $signature = 'rds:manage {action}';

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

        $action = $this->argument('action');

        if ($action == 'stop') {
            $this->stopDbInstance($dbInstanceIdentifier);

        } elseif ($action == 'start') {
            $this->startDbInstance($dbInstanceIdentifier);

        } else {
            return [
                'data' => [],
                'status' => 'ERROR',
                'message' => 'Error on manage RDS.',
                'errors' => 'Invalid action. Use "stop" or "start".'
            ];
        }

        Log::info("All Rds functions have been {$action}ed.");
    }

    public function stopDbInstance($dbInstanceIdentifier)
    {

        $dbInstanceIdentifier = 'staging-db-serverless-cluster';

        $client = new RdsClient([
            'region' => config('aws.stage_region'),
            'version' => 'latest',
            'credentials' => [
                'key' => config('aws.stage_key'),
                'secret' => config('aws.stage_secret'),
            ],
        ]);

        try {
            $result = $client->stopDBCluster([
                'DBInstanceIdentifier' => $dbInstanceIdentifier,
                'DBClusterIdentifier' => $dbInstanceIdentifier
            ]);
            return $result;
        } catch (AwsException $ex) {
            return [
                'data' => [],
                'status' => 'ERROR',
                'message' => 'Error on manage RDS.',
                'errors' => $ex->getAwsErrorMessage()
            ];
        }
    }

    public function startDbInstance()
    {
        $dbInstanceIdentifier = 'staging-db-serverless-cluster';

        $client = new RdsClient([
            'region' => config('aws.stage_region'),
            'version' => 'latest',
            'credentials' => [
                'key' => config('aws.stage_key'),
                'secret' => config('aws.stage_secret'),
            ],
        ]);

        try {
            $result = $client->startDBInstance([
                'DBInstanceIdentifier' => $dbInstanceIdentifier,
                'DBClusterIdentifier' => $dbInstanceIdentifier
            ]);
            return $result;
        } catch (AwsException $ex) {
            return [
                'data' => [],
                'status' => 'ERROR',
                'message' => 'Error on manage start RDS.',
                'errors' => $ex->getAwsErrorMessage()
            ];
        }
    }
}
