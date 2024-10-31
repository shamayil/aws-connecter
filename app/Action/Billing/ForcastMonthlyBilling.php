<?php

namespace App\Action\Billing;

use Aws\CostExplorer\CostExplorerClient;
use Carbon\Carbon;
use GuzzleHttp\Client;

class ForcastMonthlyBilling
{
    public function get(string $env)
    {

        if ($env == 'master') {
            $title = "♦️AWS $env current month forcast!";

            $client = new CostExplorerClient([
                'region' => config('aws.master_region'),
                'version' => 'latest',
                'credentials' => [
                    'key' => config('aws.master_key'),
                    'secret' => config('aws.master_secret'),
                ],
            ]);
        } else if ($env == 'production') {
            $title = "🔹AWS $env current month forcast!";

            $client = new CostExplorerClient([
                'region' => config('aws.prod_region'),
                'version' => 'latest',
                'credentials' => [
                    'key' => config('aws.prod_key'),
                    'secret' => config('aws.prod_secret'),
                ],
            ]);
        } else if ($env == 'staging') {
            $title = "🔸AWS $env current month forcast!";

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

        $params = [
            'TimePeriod' => [
                'Start' => Carbon::now()->toDateString(),
                'End' => Carbon::now()->copy()->addDay()->toDateString()
            ],
            'Granularity' => 'MONTHLY',
            'Metric' => 'UNBLENDED_COST',
        ];

        $month = Carbon::now()->format('F');

        $result = $client->getCostForecast($params);
        $result = $result['Total']['Amount'];

        $client = new Client();

        $vatRate = 0.20; // UK VAT rate is 20%
        $vatAmount = $result * $vatRate;
        $totalWithVat = floatval($result) + $vatAmount;

        $withoutVat = number_format($result, 2);
        $withVat = number_format($totalWithVat, 2);

        $message = [
            'attachments' => [
                [
                    'title' => $title,
                    'text' => "The bill forecast for end of the month , *$month*, is *$$withoutVat*."
                    // 'text' => "The bill forecast for end of the month , *$month*, is *$$withoutVat* Including VAT, the total forecast is *$$withVat*."

                ],
            ],
        ];

        $client->post('https://hooks.slack.com/services/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', [
            'json' => $message,
        ]);
    }

}
