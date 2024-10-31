<?php

namespace App\Action\Billing;

use Aws\CostExplorer\CostExplorerClient;
use Carbon\Carbon;
use GuzzleHttp\Client;

class ForcastLastMonthBilling
{
    public function get(string $env)
    {

        if ($env == 'master') {
            $title = "♦️AWS $env last month bill!";

            $client = new CostExplorerClient([
                'region' => config('aws.master_region'),
                'version' => 'latest',
                'credentials' => [
                    'key' => config('aws.master_key'),
                    'secret' => config('aws.master_secret'),
                ],
            ]);
        } else if ($env == 'production') {
            $title = "🔹AWS $env last month bill!";

            $client = new CostExplorerClient([
                'region' => config('aws.prod_region'),
                'version' => 'latest',
                'credentials' => [
                    'key' => config('aws.prod_key'),
                    'secret' => config('aws.prod_secret'),
                ],
            ]);
        } else if ($env == 'staging') {
            $title = "🔸AWS $env last month bill!";

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

        $month = Carbon::now()->subMonth()->format('F');

        $result = $client->getCostAndUsage([
            'TimePeriod' => [
                'Start' => Carbon::now()->subMonth()->startOfMonth()->toDateString(),
                'End' => Carbon::now()->startOfMonth()->toDateString()
            ],
            'Granularity' => 'MONTHLY',
            'Metrics' => ['UnblendedCost'],
        ]);

        $totalCost = $result['ResultsByTime'][0]['Total']['UnblendedCost']['Amount'];
        $totalCost = (float)$totalCost;
        $totalCost = number_format($totalCost, 2);

        $client = new Client();

        $message = [
            'attachments' => [
                [
                    'title' => $title,
                    'text' => "The bill for end of the last month , *$month*, is *$$totalCost*."
                    // 'text' => "The bill forecast for end of the month , *$month*, is *$$withoutVat* Including VAT, the total forecast is *$$withVat*."

                ],
            ],
        ];

        $client->post('https://hooks.slack.com/services/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', [
            'json' => $message,
        ]);
    }

}
