<?php

namespace App\Action\Billing;

use Aws\CostExplorer\CostExplorerClient;
use Carbon\Carbon;
use GuzzleHttp\Client;

class ForcastDailyBilling
{
    protected $request;

    public function get(string $env)
    {
        if ($env == 'master') {
            $title = "♦️AWS $env today forcast!";

            $client = new CostExplorerClient([
                'region' => config('aws.master_region'),
                'version' => 'latest',
                'credentials' => [
                    'key' => config('aws.master_key'),
                    'secret' => config('aws.master_secret'),
                ],
            ]);
        } else if ($env == 'production') {
            $title = "🔹AWS $env today forcast!";

            $client = new CostExplorerClient([
                'region' => config('aws.prod_region'),
                'version' => 'latest',
                'credentials' => [
                    'key' => config('aws.prod_key'),
                    'secret' => config('aws.prod_secret'),
                ],
            ]);
        } else if ($env == 'staging') {
            $title = "🔸AWS $env today forcast!";

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

        $currentDate = Carbon::now();
        $nextDayDate = $currentDate->copy()->addDay();

        $params = [
            'TimePeriod' => [
                'Start' => $currentDate->toDateString(),
                'End' => $nextDayDate->toDateString()
            ],
            'Granularity' => 'DAILY',
            'Metric' => 'UNBLENDED_COST',
        ];

        $dateString = Carbon::now()->toDateString();

        $result = $client->getCostForecast($params);
        $result = $result['Total']['Amount'];

        $client = new Client();

        $vatRate = 0.20; // UK VAT rate is 20%
        $vatAmount = floatval($result) * $vatRate;
        $totalWithVat = $result + $vatAmount;

        $withoutVat = number_format($result, 2);
        $withVat = number_format($totalWithVat, 2);

        $message = [
            'attachments' => [
                [
                    'title' => $title,
                    'text' => "The daily bill forecast for today, *$dateString*, is *$$withoutVat*."
                    // 'text' => "The daily bill forecast for today, *$dateString*, is *$$withoutVat*. Including VAT, the total forecast is *$$withVat*."
                ],
            ],
        ];

        $client->post('https://hooks.slack.com/services/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', [
            'json' => $message,
        ]);

    }

}
