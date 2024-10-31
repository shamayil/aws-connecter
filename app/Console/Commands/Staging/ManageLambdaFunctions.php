<?php

namespace App\Console\Commands\Staging;

use Aws\Lambda\LambdaClient;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class ManageLambdaFunctions extends Command
{
    protected $signature = 'lambda:manage {action}';

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

        $action = $this->argument('action');

        foreach ($listAllFunction as $function) {

            $functionName = $function['FunctionName'];

            if (strpos($functionName, 'iposg-aws-connect') != false) {
                continue;
            }

            if ($action == 'stop') {
                $this->stopLambdaFunction($client, $functionName);
            } elseif ($action == 'start') {
                $this->startLambdaFunction($client, $functionName);
            } else {
                Log::error('Invalid action. Use "stop" or "start".');
                continue;
            }
        }

        Log::info("All Lambda functions have been {$action}ed.");
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
                'message' => 'Error on manage list stop Lambda.',
                'errors' => $ex->getAwsErrorMessage()
            ];
        }
    }

    private function startLambdaFunction(LambdaClient $lambda, $functionName)
    {
        try {

            $lambda->deleteFunctionConcurrency([
                'FunctionName' => $functionName,
            ]);
        } catch (Throwable $ex) {
            return [
                'data' => [],
                'status' => 'ERROR',
                'message' => 'Error on manage list start lambda.',
                'errors' => $ex->getAwsErrorMessage()
            ];
        }
    }
}
