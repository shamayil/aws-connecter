<?php

namespace App\Console\Commands\Staging;

use Aws\Exception\AwsException;
use Aws\Lambda\LambdaClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateLambdaFunctions extends Command
{
    protected $signature = 'lambda:update:memory';

    public function handle()
    {
        $enviroments = ['staging', 'production'];

        foreach ($enviroments as $env) {

            $this->compare($env);
        }
    }

    private function compare($env)
    {
        if ($env == 'staging') {
            $client = new LambdaClient([
                'region' => config('aws.stage_region'),
                'version' => 'latest',
                'credentials' => [
                    'key' => config('aws.stage_key'),
                    'secret' => config('aws.stage_secret'),
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
        }

        $listAllFunction = $this->listAllFunctions($env);

        foreach ($listAllFunction as $function) {

            $functionName = $function['FunctionName'];

            $this->updateLambdaFunction($client, $functionName);
        }
    }

    public function listAllFunctions($env)
    {
        if ($env == 'staging') {
            $client = new LambdaClient([
                'region' => config('aws.stage_region'),
                'version' => 'latest',
                'credentials' => [
                    'key' => config('aws.stage_key'),
                    'secret' => config('aws.stage_secret'),
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
            } catch (AwsException $e) {
                Log::error('Error listing Lambda functions: ' . $e->getMessage());
                break;
            }
        } while ($nextMarker);

        return $functions;
    }

    private function updateLambdaFunction(LambdaClient $lambda, $functionName)
    {
        $memorySize = 512;

        try {
            $lambda->updateFunctionConfiguration([
                'FunctionName' => $functionName,
                'MemorySize' => $memorySize,
            ]);
        } catch (AwsException $ex) {
            return [
                'data' => [],
                'status' => 'ERROR',
                'message' => 'Error on update Lambda.',
                'errors' => $ex->getAwsErrorMessage()
            ];
        }
    }
}
