<?php

namespace App\Action\Billing;

use Aws\CostExplorer\CostExplorerClient;
use Carbon\Carbon;
use GuzzleHttp\Client;

class ForcastDailyYesterdayBilling
{
    protected $request;

    public function get(string $env)
    {
        if ($env == 'master') {
            $title = "♦️AWS $env yesterday bill!";

            $client = new CostExplorerClient([
                'region' => config('aws.master_region'),
                'version' => 'latest',
                'credentials' => [
                    'key' => config('aws.master_key'),
                    'secret' => config('aws.master_secret'),
                ],
            ]);
        } else if ($env == 'production') {
            $title = "🔹AWS $env yesterday bill!";

            $client = new CostExplorerClient([
                'region' => config('aws.prod_region'),
                'version' => 'latest',
                'credentials' => [
                    'key' => config('aws.prod_key'),
                    'secret' => config('aws.prod_secret'),
                ],
            ]);
        } else if ($env == 'staging') {
            $title = "🔸AWS $env yesterday bill!";

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
        $result = $dailyCost;

        $vatRate = 0.20; // UK VAT rate is 20%
        $vatAmount = floatval($result) * $vatRate;
        $totalWithVat = $result + $vatAmount;

        $withoutVat = number_format($result, 2);
        $withVat = number_format($totalWithVat, 2);

        $client = new Client();

        $message = [
            'attachments' => [
                [
                    'title' => $title,
                    'text' => "The daily bill for yesterday, *$end*, is *$$withoutVat*."
                    // 'text' => "The daily bill for yesterday, *$end*, is *$$withoutVat*. Including VAT, the total is *$$withVat*."
                ],
            ],
        ];

        $client->post('https://hooks.slack.com/services/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', [
            'json' => $message,
        ]);

    }

}
