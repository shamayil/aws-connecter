<?php

namespace App\Action\Billing;

use Aws\CostExplorer\CostExplorerClient;
use Carbon\Carbon;
use GuzzleHttp\Client;

class BlindedDailyBilling
{
    public function get(string $env)
    {

        if ($env == 'master') {
            $title = "♦️AWS $env bill update";

            $client = new CostExplorerClient([
                'region' => config('aws.master_region'),
                'version' => 'latest',
                'credentials' => [
                    'key' => config('aws.master_key'),
                    'secret' => config('aws.master_secret'),
                ],
            ]);
        } else if ($env == 'production') {
            $title = "🔹AWS $env bill update";

            $client = new CostExplorerClient([
                'region' => config('aws.prod_region'),
                'version' => 'latest',
                'credentials' => [
                    'key' => config('aws.prod_key'),
                    'secret' => config('aws.prod_secret'),
                ],
            ]);
        } else if ($env == 'staging') {
            $title = "🔸AWS $env bill update";

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

        $start = Carbon::yesterday()->format('Y-m-d');
        $end = Carbon::now()->format('Y-m-d');

        $result = $client->getCostAndUsage([
            'TimePeriod' => [
                'Start' => $start,
                'End' => $end,
            ],
            'Granularity' => 'DAILY',
            'Metrics' => ['UNBLENDED_COST'],
        ]);

        $dailyCost = $result['ResultsByTime'][0]['Total']['UnblendedCost']['Amount'];
        $dailyNumber = number_format((float)$dailyCost, 2, '.', '');

        $vatRate = 0.20; // UK VAT rate is 20%
        $totalWithVat = 0; //floatval($dailyNumber) * $vatRate;
        $formattedNumber = floatval($dailyNumber) + $totalWithVat;

        $costs = $this->getDailyCosts($env);
        $service = '';

        foreach ($costs as $key => $cost) {
            if ($env == 'staging' && $cost > 0.25) {
                $service = $service . "\n" . $key . ': ' . $cost;

            } else if ($env == 'production' && $cost > 2.00) {
                $service = $service . "\n" . $key . ': ' . $cost;
            } else if ($env == 'master' && $cost > 5.00) {
                $service = $service . "\n" . $key . ': ' . $cost;
            } else {
                continue;
            }
        }

        $client = new Client();

        $amuontOfBillStage = 5.00;
        $amuontOfBillProd = 120.00;
        $amuontOfBillMaster = 180.00;

        if ($env == 'staging' && $formattedNumber > $amuontOfBillStage) {

            $message = [
                'attachments' => [
                    [
                        'title' => $title,
                        'text' => "The daily $env bill is expected to be around *$$amuontOfBillStage* but *$start* it was *$" . $formattedNumber . '*' .
                            " below service(s) are effected \n $service"
                    ],
                ],
            ];
        } else if ($env == 'production' && $formattedNumber > $amuontOfBillProd) {

            $message = [
                'attachments' => [
                    [
                        'title' => $title,
                        'text' => "The daily $env bill is expected to be around *$$amuontOfBillProd* but *$start* it was *$" . $formattedNumber . '*' .
                            " below service(s) are effected \n $service"
                    ],
                ],
            ];
        } else if ($env == 'master' && $formattedNumber > $amuontOfBillMaster) {

            $message = [
                'attachments' => [
                    [
                        'title' => $title,
                        'text' => "The daily $env bill is expected to be around *$$amuontOfBillMaster* but *$start* it was *$" . $formattedNumber . '*' .
                            " below service(s) are effected \n $service"
                    ],
                ],
            ];
        } else {
            return;
        }

        $client->post('https://hooks.slack.com/services/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', [
            'json' => $message,
        ]);
    }

    public function getDailyCosts($env)
    {
        $start = Carbon::yesterday()->format('Y-m-d');
        $end = Carbon::now()->format('Y-m-d');

        if ($env == 'master') {
            $client = new CostExplorerClient([
                'region' => config('aws.master_region'),
                'version' => 'latest',
                'credentials' => [
                    'key' => config('aws.master_key'),
                    'secret' => config('aws.master_secret'),
                ],
            ]);
        } else if ($env == 'production') {
            $client = new CostExplorerClient([
                'region' => config('aws.prod_region'),
                'version' => 'latest',
                'credentials' => [
                    'key' => config('aws.prod_key'),
                    'secret' => config('aws.prod_secret'),
                ],
            ]);
        } else if ($env == 'staging') {
            $client = new CostExplorerClient([
                'region' => config('aws.stage_region'),
                'version' => 'latest',
                'credentials' => [
                    'key' => config('aws.stage_key'),
                    'secret' => config('aws.stage_secret'),
                ],
            ]);
        } else {
            return "not found";
        }

        $result = $client->getCostAndUsage([
            'TimePeriod' => [
                'Start' => $start,
                'End' => $end,
            ],
            'Granularity' => 'DAILY',
            'Metrics' => ['BlendedCost'],
            'GroupBy' => [
                [
                    'Type' => 'DIMENSION',
                    'Key' => 'SERVICE'
                ],
            ],
        ]);

        $costs = [];
        foreach ($result['ResultsByTime'][0]['Groups'] as $group) {
            $service = $group['Keys'][0];
            $cost = (float)$group['Metrics']['BlendedCost']['Amount'];

            $vatRate = 0.20; // UK VAT rate is 20%
            $vatAmount = floatval($cost) * $vatRate;
            $totalWithVat = $cost + $vatAmount;
            $costs[$service] = number_format($totalWithVat, 2);
        }

        return $costs;
    }

}
