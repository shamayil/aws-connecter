<?php

namespace App\Http\Controllers;

use Aws\Exception\AwsException;
use Aws\Rds\RdsClient;
use GuzzleHttp\Client;


class RDSController extends Controller
{

    public function startDbInstance()
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
            $client->startDBCluster([
                'DBInstanceIdentifier' => $dbInstanceIdentifier,
                'DBClusterIdentifier' => $dbClusterIdentifier
            ]);

            $env = 'Staging';
            $service = 'RDS DB';
            $name = $dbClusterIdentifier;
            $trigger = 'started';
            $this->slack($env, $service, $name, $trigger);

            return [
                'data' => [],
                'status' => 'OK',
                'message' => 'RDS started successfully.',
                'errors' => null
            ];

        } catch (AwsException $e) {

            return [
                'data' => [],
                'status' => 'ERROR',
                'message' => 'Error on start RDS.',
                'errors' => $e->getAwsErrorMessage()
            ];
        }

    }

    function slack($env, $service, $name, $trigger)
    {
        $client = new Client();

        $message = [
            'attachments' => [
                [
                    'title' => "$env $service",
                    'text' => "The *$name* has been *$trigger*."
                ],
            ],
        ];

        $client->post('https://hooks.slack.com/services/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', [
            'json' => $message,
        ]);
    }

    public function stopDbInstance()
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
            $client->stopDBCluster([
                'DBInstanceIdentifier' => $dbInstanceIdentifier,
                'DBClusterIdentifier' => $dbClusterIdentifier
            ]);

            $env = 'Staging';
            $service = 'RDS DB';
            $name = $dbClusterIdentifier;
            $trigger = 'stopped';
            $this->slack($env, $service, $name, $trigger);

            return [
                'data' => [],
                'status' => 'OK',
                'message' => 'RDS stopped successfully.',
                'errors' => null
            ];
        } catch (AwsException $e) {
            return [
                'data' => [],
                'status' => 'ERROR',
                'message' => 'Error on start RDS.',
                'errors' => $e->getAwsErrorMessage()
            ];
        }
    }

}
