<?php

namespace App\Console\Commands\CloudWatch;

use Aws\CloudWatchLogs\CloudWatchLogsClient;
use Aws\Exception\AwsException;
use Illuminate\Console\Command;

class CloudWatchRetentionFunctions extends Command
{
    protected $signature = 'cloudwatch:retention';

    public function handle()
    {
        $enviroments = ['staging', 'production', 'master'];

        foreach ($enviroments as $env) {
            $this->updateRetentionDays($env);
        }
    }

    public function updateRetentionDays($env)
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
            $retentionInDays = 1;
            $maxRetries = 3;
            $retryCount = 0;
            $retryDelay = 100;

            while ($retryCount < $maxRetries) {
                try {
                    $client->putRetentionPolicy([
                        'logGroupName' => $logGroupName,
                        'retentionInDays' => $retentionInDays,
                    ]);
                    break;
                } catch (AwsException $e) {
                    if ($e->getAwsErrorCode() == 'ThrottlingException') {
                        $retryCount++;
                        usleep($retryDelay * 500);
                        $retryDelay *= 2;
                    } else {
                        echo $e->getMessage();
                        break;
                    }
                }
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
