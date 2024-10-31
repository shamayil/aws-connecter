<?php

namespace App\Action\Billing;

use Aws\CostExplorer\CostExplorerClient;
use Carbon\Carbon;
use GuzzleHttp\Client;

class CompareCostBilling
{
    protected $request;

    public function compare(string $env)
    {
        $differTotal = 0;
        $febBill = $this->febBilling($env);
        $currentBill = $this->currentBilling($env);
        $increasedCosts = $this->compareCosts($currentBill, $febBill, $env);
        $client = new Client();

        if ($increasedCosts != []) {
            foreach ($increasedCosts as $key => $increasedCost) {

                $vatRate = 0.20;

                $currentMonth = number_format($increasedCost['current_month']) !== null ? number_format($increasedCost['current_month']) : 0;
                $currentMonth = floatval($currentMonth);
                $vatAmount = floatval($currentMonth) * $vatRate;
                $totalWithVat = $vatAmount + $vatAmount;

                $febMonth = number_format($increasedCost['february_month']) !== null ? number_format($increasedCost['february_month']) : 0;
                $febMonth = floatval($febMonth);
                $vatRate = 0.20; // UK VAT rate is 20%
                $vatAmount = floatval($febMonth) * $vatRate;
                $totalWithVatFeb = $vatAmount + $vatAmount;

                $differ = $totalWithVat - $totalWithVatFeb;

                $differTotal = $differTotal + $differ;
                $differTotal = number_format($differTotal, 4, '.', '');

                if ($env == 'master' && (floatval(number_format($differ, 4, '.', '')) < 12.00)) {
                    continue;
                } else if ($env == 'staging' && (floatval(number_format($differ, 4, '.', '')) < 1.00)) {
                    continue;
                } else if ($env == 'production' && (floatval(number_format($differ, 4, '.', '')) < 10.00)) {
                    continue;
                }

                if ($env == 'staging') {
                    $title = '🔸💵 ' . $env . ' ' . $key . ' Billing';
                } else if ($env == 'production') {
                    $title = '🔹💵 ' . $env . ' ' . $key . ' Billing';
                } else if ($env == 'master') {
                    $title = '♦️💵 ' . $env . ' ' . $key . ' Billing';
                } else {
                    continue;
                }

                $message = [
                    'attachments' => [
                        [
                            'title' => $title,
                            'text' => 'February Billing: *$' . number_format($increasedCost['february_month'], 4, '.', '') . "*\n This Month Billing: *$" . number_format($increasedCost['current_month'], 4, '.', '') . "*\n Difference: *$" . number_format($differ, 4, '.', '') . '*'

                        ],
                    ],
                ];

                $client->post('https://hooks.slack.com/services/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', [
                    'json' => $message,
                ]);
            }
        }
    }

    function febBilling(string $env)
    {
        $thisMonth = Carbon::create(null, 2)->startOfMonth()->toDateString();
        $nextMonth = Carbon::create(null, 2)->endOfMonth()->toDateString();

        if ($env == 'staging') {
            $client = new CostExplorerClient([
                'region' => config('aws.stage_region'),
                'version' => 'latest',
                'credentials' => [
                    'key' => config('aws.stage_key'),
                    'secret' => config('aws.stage_secret'),
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
        } else {
            $client = new CostExplorerClient([
                'region' => config('aws.master_region'),
                'version' => 'latest',
                'credentials' => [
                    'key' => config('aws.master_key'),
                    'secret' => config('aws.master_secret'),
                ],
            ]);
        }

        $result = $client->getCostAndUsage([
            'TimePeriod' => [
                'Start' => $thisMonth,
                'End' => $nextMonth,
            ],
            'Granularity' => 'MONTHLY',
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
            $costs[$service] = $cost;
        }
        return $costs;
    }

    function currentBilling(string $env)
    {
        $thisMonth = Carbon::now()->startOfMonth()->toDateString();
        $nextMonth = Carbon::now()->endOfMonth()->toDateString();

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
            return "Not Found";
        }

        $result = $client->getCostAndUsage([
            'TimePeriod' => [
                'Start' => $thisMonth,
                'End' => $nextMonth,
            ],
            'Granularity' => 'MONTHLY',
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
            $costs[$service] = $cost;
        }
        return $costs;
    }

    public function compareCosts($costsThisMonth, $costsFebruary, $env)
    {
        $increasedCosts = [];

        foreach ($costsThisMonth as $service => $cost) {

            if (isset($costsFebruary[$service]) && $cost > $costsFebruary[$service]) {

                $increasedCosts[$service] = [
                    'enviroment' => $env,
                    'current_month' => $cost,
                    'february_month' => $costsFebruary[$service],
                    'difference' => ""
                ];
            }
        }

        return $increasedCosts;
    }

}
