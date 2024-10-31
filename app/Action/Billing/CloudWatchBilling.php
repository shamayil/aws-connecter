<?php

namespace App\Action\Billing;

use Aws\CloudWatchLogs\CloudWatchLogsClient;
use Aws\CostExplorer\CostExplorerClient;
use Carbon\Carbon;
use GuzzleHttp\Client;

class CloudWatchBilling
{
    public function get(string $env)
    {
        if ($env == 'master') {
            $title = '♦️AWS Master CloudWatch Monthly Cost';

            $client = new CostExplorerClient([
                'region' => config('aws.master_region'),
                'version' => 'latest',
                'credentials' => [
                    'key' => config('aws.master_key'),
                    'secret' => config('aws.master_secret'),
                ],
            ]);
        } else if ($env == 'production') {
            $title = '🔹AWS Production CloudWatch Monthly Cost';

            $client = new CostExplorerClient([
                'region' => config('aws.prod_region'),
                'version' => 'latest',
                'credentials' => [
                    'key' => config('aws.prod_key'),
                    'secret' => config('aws.prod_secret'),
                ],
            ]);
        } else if ($env == 'staging') {
            $title = "🔸AWS Staging CloudWatch Monthly Cost";

            $client = new CostExplorerClient([
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

            $log = $logGroup['logGroupName'];
            $tagName = 'Name';
            $tagValue = basename($log);

            $start = Carbon::now()->startOfMonth()->format('Y-m-d');
            $end = Carbon::now()->endOfMonth()->format('Y-m-d');

            $result = $client->getCostAndUsage([
                'TimePeriod' => [
                    'Start' => $start,
                    'End' => $end,
                ],
                'Granularity' => 'MONTHLY',
                'Filter' => [
                    'And' => [
                        [
                            'Dimensions' => [
                                'Key' => 'SERVICE',
                                'Values' => ['AmazonCloudWatch'],
                            ],
                        ],
                        [
                            'Tags' => [
                                'Key' => $tagName,
                                'Values' => [$tagValue],
                            ],
                        ],
                    ],
                ],
                'Metrics' => ['UnblendedCost'],
            ]);

            $cost = $result['ResultsByTime'][0]['Total']['UnblendedCost']['Amount'];

            $vatRate = 0.20; // UK VAT rate is 20%
            $vatAmount = floatval($cost) * $vatRate;
            $totalWithVat = $cost + $vatAmount;

            if ($env == 'staging' && $totalWithVat > 0.1) {
                $showMessages = "The cost for the cloudwatch name *$tagValue* is *$" . number_format($totalWithVat, 2) . '*.';
            } else if (env('APP_ENV') == 'production' && $totalWithVat > 0.5) {
                $showMessages = "The cost for the cloudwatch name *$tagValue* is *$" . number_format($totalWithVat, 2) . '*.';
            } else if (env('APP_ENV') == 'master' && $totalWithVat > 0.5) {
                $showMessages = "The cost for the cloudwatch name *$tagValue* is *$" . number_format($totalWithVat, 2) . '*.';
            } else {
                continue;
            }

            $message = [
                'attachments' => [
                    [
                        'title' => $title,
                        'text' => $showMessages
                    ]
                ]
            ];

            $slack = new Client();
            $slack->post('https://hooks.slack.com/services/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', [
                'json' => $message,
            ]);
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
            return 'not found';
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
