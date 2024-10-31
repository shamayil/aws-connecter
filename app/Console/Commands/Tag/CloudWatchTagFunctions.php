<?php

namespace App\Console\Commands\Tag;

use Aws\CloudWatchLogs\CloudWatchLogsClient;
use Exception;
use Illuminate\Console\Command;

class CloudWatchTagFunctions extends Command
{
    protected $signature = 'cloud:watch:tag';

    public function handle()
    {
        $enviroments = ['master', 'production', 'staging'];

        foreach ($enviroments as $env) {
            $logGroups = $this->getAllLogGroups($env);

            if ($env == 'master') {
                $cloudWatch = new CloudWatchLogsClient([
                    'region' => config('aws.master_region'),
                    'version' => 'latest',
                    'credentials' => [
                        'key' => config('aws.master_key'),
                        'secret' => config('aws.master_secret'),
                    ],
                ]);
            } else if ($env == 'production') {
                $cloudWatch = new CloudWatchLogsClient([
                    'region' => config('aws.prod_region'),
                    'version' => 'latest',
                    'credentials' => [
                        'key' => config('aws.prod_key'),
                        'secret' => config('aws.prod_secret'),
                    ],
                ]);
            } else if ($env == 'staging') {
                $cloudWatch = new CloudWatchLogsClient([
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

            foreach ($logGroups as $logGroup) {

                $log = $logGroup['logGroupName'];

                $tags = [
                    'Environment' => env('APP_ENV'),
                    'Name' => basename($log),
                ];

                try {
                    $cloudWatch->tagLogGroup([
                        'logGroupName' => $log,
                        'tags' => $tags,
                    ]);
                    usleep(200000);
                } catch (Exception $ex) {
                    usleep(500000);
                    continue;
                }

                echo "Tagged cloudwatch: {basename($log)} enviroment $env\n";
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
