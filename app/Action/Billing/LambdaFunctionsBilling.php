<?php

namespace App\Action\Billing;

use Aws\CostExplorer\CostExplorerClient;
use Aws\Lambda\LambdaClient;
use Carbon\Carbon;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class LambdaFunctionsBilling
{
    public function get(string $env)
    {
        if ($env == 'master') {
            $title = '♦️AWS Master Lambda Monthly Cost';

            $client = new CostExplorerClient([
                'region' => config('aws.master_region'),
                'version' => 'latest',
                'credentials' => [
                    'key' => config('aws.master_key'),
                    'secret' => config('aws.master_secret'),
                ],
            ]);
        } else if ($env == 'production') {
            $title = '🔹AWS Production Lambda Monthly Cost';

            $client = new CostExplorerClient([
                'region' => config('aws.prod_region'),
                'version' => 'latest',
                'credentials' => [
                    'key' => config('aws.prod_key'),
                    'secret' => config('aws.prod_secret'),
                ],
            ]);
        } else if ($env == 'staging') {
            $title = "🔸AWS Staging Lambda Monthly Cost";

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

        $results = $this->listAllFunctions($env);


//        $lamdaList = [];
//        $no = 0;
//        foreach ($results as $result) {
//            $function = $result['FunctionName'];
//            if (strpos($function, '-queue') !== false || strpos($function, '-cli') !== false) {
//                continue;
//            }
//            $no++;
//            $lamdaList[$no] = $function;
//        }
//        Log::info(json_encode($lamdaList));
//        dd("Done");


        foreach ($results as $result) {

            $tagName = 'Name';
            $tagValue = $result['FunctionName'];

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
                                'Values' => ['AWS Lambda'],
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

            if ($env == 'staging' && $totalWithVat > 0.2) {
                $showMessages = "The cost for the Lambda function name *$tagValue* is *$" . number_format($totalWithVat, 2) . '*.';
            } else if ($env == 'production' && $totalWithVat > 1.0) {
                $showMessages = "The cost for the Lambda function name *$tagValue* is *$" . number_format($totalWithVat, 2) . '*.';
            } else if ($env == 'master' && $totalWithVat > 0.5) {
                $showMessages = "The cost for the Lambda function name *$tagValue* is *$" . number_format($totalWithVat, 2) . '*.';
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

    public function listAllFunctions($env)
    {
        if ($env == 'master') {
            $client = new LambdaClient([
                'region' => config('aws.master_region'),
                'version' => 'latest',
                'credentials' => [
                    'key' => config('aws.master_key'),
                    'secret' => config('aws.master_secret'),
                ],
            ]);
        } else if ($env == 'production') {
            $client = new LambdaClient([
                'region' => config('aws.prod_region'),
                'version' => 'latest',
                'credentials' => [
                    'key' => config('aws.prod_key'),
                    'secret' => config('aws.prod_secret'),
                ],
            ]);
        } else if ($env == 'staging') {
            $client = new LambdaClient([
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

        $functions = [];
        $nextMarker = null;

        do {
            $params = [];
            if ($nextMarker) {
                $params['Marker'] = $nextMarker;
            }

            try {
                $result = $client->listFunctions($params);
                $functions = array_merge($functions, $result->get('Functions'));
                $nextMarker = $result->get('NextMarker');
            } catch (Exception $e) {
                Log::error('Error listing Lambda functions: ' . $e->getMessage());
                break;
            }
        } while ($nextMarker);

        return $functions;
    }
}
