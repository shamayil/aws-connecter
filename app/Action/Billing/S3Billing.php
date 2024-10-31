<?php

namespace App\Action\Billing;

use Aws\CostExplorer\CostExplorerClient;
use Carbon\Carbon;
use GuzzleHttp\Client;

class S3Billing
{
    public function get(string $env)
    {

        if ($env == 'master') {
            $ceClient = new CostExplorerClient([
                'region' => config('aws.master_region'),
                'version' => 'latest',
                'credentials' => [
                    'key' => config('aws.master_key'),
                    'secret' => config('aws.master_secret'),
                ],
            ]);
        } else if ($env == 'production') {
            $ceClient = new CostExplorerClient([
                'region' => config('aws.prod_region'),
                'version' => 'latest',
                'credentials' => [
                    'key' => config('aws.prod_key'),
                    'secret' => config('aws.prod_secret'),
                ],
            ]);
        } else if ($env == 'staging') {
            $ceClient = new CostExplorerClient([
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

        $tagName = 'Name';
        $start = Carbon::now()->startOfMonth()->format('Y-m-d');
        $end = Carbon::now()->endOfMonth()->format('Y-m-d');

        $params = [
            'TimePeriod' => [
                'Start' => $start,
                'End' => $end,
            ],
            'Granularity' => 'MONTHLY',
            'Metrics' => ['BlendedCost'],
            'GroupBy' => [
                [
                    'Type' => 'TAG',
                    'Key' => $tagName,
                ],
            ],
            'Filter' => [
                'Dimensions' => [
                    'Key' => 'SERVICE',
                    'Values' => ['Amazon Simple Storage Service'],
                ],
            ],
        ];

        $result = $ceClient->getCostAndUsage($params);

        $costs = [];
        foreach ($result['ResultsByTime'][0]['Groups'] as $group) {
            $service = $group['Keys'][0];
            $stringToRemove = 'Name$';

            $newString = str_replace($stringToRemove, '', $service);
            if ($newString == "") {
                $newString = "NO NAME";
            }
            $cost = (float)$group['Metrics']['BlendedCost']['Amount'];
            $costs[$newString] = number_format($cost, 4);

            if ($costs[$newString] > 1.0) {
                if ($env == 'master') {
                    $title = '♦️AWS master Monthly S3 Cost!';
                } else if ($env == 'production') {
                    $title = '🔹AWS Production Monthly S3 Cost!';
                } else if ($env == 'staging') {
                    $title = '🔸AWS Staging Monthly S3 Cost!';
                } else {
                    return;
                }

                $message = [
                    'attachments' => [
                        [
                            'title' => $title,
                            'text' => "The expense for the *$newString* of the S3 Bucket amounts to *$" . number_format($cost, 4) . "*"
                        ]
                    ]
                ];

                $slack = new Client();
                $slack->post('https://hooks.slack.com/services/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', [
                    'json' => $message,
                ]);
            }
        }
    }
}
