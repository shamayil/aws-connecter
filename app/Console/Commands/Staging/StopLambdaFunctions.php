<?php

namespace App\Console\Commands\Staging;

use Aws\Lambda\LambdaClient;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class StopLambdaFunctions extends Command
{
    protected $signature = 'lambda:stop:all';

    public function handle()
    {
        $client = new LambdaClient([
            'region' => config('aws.stage_region'),
            'version' => 'latest',
            'credentials' => [
                'key' => config('aws.stage_key'),
                'secret' => config('aws.stage_secret'),
            ],
        ]);

        $listAllFunction = $this->listAllFunctions();

        foreach ($listAllFunction as $function) {
            $functionName = $function['FunctionName'];
            $this->stopLambdaFunction($client, $functionName);
        }

        $this->slack();
    }

    public function listAllFunctions()
    {
        $client = new LambdaClient([
            'region' => config('aws.stage_region'),
            'version' => 'latest',
            'credentials' => [
                'key' => config('aws.stage_key'),
                'secret' => config('aws.stage_secret'),
            ],
        ]);

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

    private function stopLambdaFunction(LambdaClient $lambda, $functionName)
    {
        $reservedConcurrentExecutions = 0;

        try {
            $lambda->putFunctionConcurrency([
                'FunctionName' => $functionName,
                'ReservedConcurrentExecutions' => $reservedConcurrentExecutions,
            ]);
        } catch (Throwable $ex) {
            return [
                'data' => [],
                'status' => 'ERROR',
                'message' => 'Error on stop Lambda.',
                'errors' => $ex->getAwsErrorMessage()
            ];
        }
    }

    function slack()
    {
        $client = new Client();

        $message = [
            'attachments' => [
                [
                    'title' => 'Lambda Service - Staging',
                    'text' => 'All the Lambda functions have been stopped.'
                ],
            ],
        ];

        $client->post('https://hooks.slack.com/services/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', [
            'json' => $message,
        ]);
    }
}
