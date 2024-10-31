<?php

namespace App\Console\Commands\Master;

use Aws\CostExplorer\CostExplorerClient;
use Aws\Exception\AwsException;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Console\Command;

class BillByServiceFunctions extends Command
{
    protected $signature = 'master:bill';

    public function handle()
    {
        $startOfMonth = Carbon::now()->startOfMonth()->toDateString();
        $endOfMonth = Carbon::now()->endOfMonth()->toDateString();

        $client = new CostExplorerClient([
            'region' => config('aws.master_region'),
            'version' => 'latest',
            'credentials' => [
                'key' => config('aws.master_key'),
                'secret' => config('aws.master_secret'),
            ],
        ]);

        try {
            $result = $client->getCostAndUsage([
                'TimePeriod' => [
                    'Start' => $startOfMonth,
                    'End' => $endOfMonth,
                ],
                'Granularity' => 'MONTHLY',
                'Metrics' => ['BlendedCost'],
                'GroupBy' => [
                    [
                        'Type' => 'DIMENSION',
                        'Key' => 'SERVICE',
                    ],
                ],
            ]);

            $results = $result['ResultsByTime'][0]['Groups'];
            $totalCost = 0;

            foreach ($results as $group) {
                $metrics = $group['Metrics']['BlendedCost']['Amount'];
                $totalCost += (float)$metrics;
            }

            $output = "-------------------------------------------------------------\n";
            $output .= "Bill from *$startOfMonth* to " . Carbon::now()->toDateString() . "*\n";
            $output .= "-------------------------------------------------------------\n";

            foreach ($results as $result) {

                $service = $result['Keys'][0];
                $amount = $result['Metrics']['BlendedCost']['Amount'];
                $amount = number_format($amount, 2);
                if ($amount <= 20) {
                    continue;
                }
                $output .= "$service - *$" . number_format($amount, 2) . "*\n";
                $output .= "-------------------------------------------------------------\n";
            }

            $message = [
                'attachments' => [
                    [
                        'title' => "The current bill for this month up to now - " . '* : *$' . number_format($totalCost, 2) . "*",
                        'text' => $output
                    ],
                ],
            ];

            $slack = new Client();
            $slack->post('https://hooks.slack.com/services/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', [
                'json' => $message,
            ]);

        } catch (AwsException $e) {
            echo $e->getMessage();
            echo "\n";
            return null;
        }
    }
}
