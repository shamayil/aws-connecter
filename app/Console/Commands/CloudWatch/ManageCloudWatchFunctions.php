<?php

namespace App\Console\Commands\CloudWatch;

use Aws\CloudWatchLogs\CloudWatchLogsClient;
use Aws\Exception\AwsException;
use Illuminate\Console\Command;

class ManageCloudWatchFunctions extends Command
{
    protected $signature = 'cloudwatch:clear';

    public function handle()
    {
        $enviroments = ['staging', 'production', 'master'];

        foreach ($enviroments as $env) {
            $this->deleteAllLogs($env);
        }
    }

    public function deleteAllLogs($env)
    {
        if ($env == 'master') {
            $client = new CloudWatchLogsClient([
                'region' => config('aws.master_region'),
                'version' => 'latest',
                'credentials' => [
                    'key' => config('aws.master_key'),
                    'secret' => config('aws.master_secret'),
                ],
            ]);
        } else if ($env == 'production') {
            $client = new CloudWatchLogsClient([
                'region' => config('aws.prod_region'),
                'version' => 'latest',
                'credentials' => [
                    'key' => config('aws.prod_key'),
                    'secret' => config('aws.prod_secret'),
                ],
            ]);
        } else if ($env == 'staging') {
            $client = new CloudWatchLogsClient([
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

        $logGroups = $this->getAllLogGroups($env);

        foreach ($logGroups as $logGroup) {

            $logGroupName = $logGroup['logGroupName'];

            try {
                $results = [];
                $nextToken = null;
                $maxResults = 50000;
                $backoff = 1;

                do {
                    $params = [
                        'logGroupName' => $logGroupName,
                        'orderBy' => 'LastEventTime',
                        'descending' => true,
                        'limit' => min(500, $maxResults - count($results)),
                    ];

                    if ($nextToken !== null) {
                        $params['nextToken'] = $nextToken;
                    }

                    $result = $client->describeLogStreams($params);
                    $results = array_merge($results, $result['logStreams']);
                    $nextToken = isset($result['nextToken']) ? $result['nextToken'] : null;
                } while ($nextToken !== null && count($results) < $maxResults);

                foreach ($results as $logStream) {
                    $logStreamName = $logStream['logStreamName'];
                    $creationTime = $logStream['creationTime'] / 100000;
                    $currentTime = time();
                    $streamAgeInSeconds = $currentTime - $creationTime;

                    $expirationThresholdInSeconds = 604800 * 3;
                    if ($streamAgeInSeconds > $expirationThresholdInSeconds) {
                        try {
                            $client->deleteLogStream([
                                'logGroupName' => $logGroupName,
                                'logStreamName' => $logStreamName,
                            ]);
                            echo "Log Group '$logGroupName' LogStream '$logStreamName' deleted successfully.\n";
                        } catch (AwsException $e) {
                            if ($e->getAwsErrorCode() == 'ThrottlingException') {
                                sleep($backoff);
                                $backoff *= 2;
                            } else {
                                throw $e;
                            }
                            echo "Error deleting LogStream '$logStreamName': " . $e->getMessage() . "\n";
                        }
                    }

                }
            } catch (AwsException $e) {
                echo 'Error retrieving LogStreams: ' . $e->getMessage() . "\n";
            }
        }
    }

    public function getAllLogGroups($env)
    {
        if ($env == 'master') {
            $client = new CloudWatchLogsClient([
                'region' => config('aws.master_region'),
                'version' => 'latest',
                'credentials' => [
                    'key' => config('aws.master_key'),
                    'secret' => config('aws.master_secret'),
                ],
            ]);
        } else if ($env == 'production') {
            $client = new CloudWatchLogsClient([
                'region' => config('aws.prod_region'),
                'version' => 'latest',
                'credentials' => [
                    'key' => config('aws.prod_key'),
                    'secret' => config('aws.prod_secret'),
                ],
            ]);
        } else if ($env == 'staging') {
            $client = new CloudWatchLogsClient([
                'region' => config('aws.stage_region'),
                'version' => 'latest',
                'credentials' => [
                    'key' => config('aws.stage_key'),
                    'secret' => config('aws.stage_secret'),
                ],
            ]);
        } else {
            return "Not Found";
        }

        $logGroups = [];
        $nextToken = null;

        do {
            $params = [];
            if ($nextToken) {
                $params['nextToken'] = $nextToken;
            }

            $result = $client->describeLogGroups($params);

            $logGroups = array_merge($logGroups, $result['logGroups']);
            $nextToken = isset($result['nextToken']) ? $result['nextToken'] : null;
        } while ($nextToken);

        return $logGroups;
    }


}
