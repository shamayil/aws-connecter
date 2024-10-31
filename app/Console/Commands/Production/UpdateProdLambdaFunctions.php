<?php

namespace App\Console\Commands\Production;

use Aws\Exception\AwsException;
use Aws\Lambda\LambdaClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateProdLambdaFunctions extends Command
{
    protected $signature = 'update-prod-lambda';

    public function handle()
    {
        $listAllFunction = $this->listAllFunctions();

        foreach ($listAllFunction as $function) {

            $functionName = $function['FunctionName'];

            $this->updateLambdaFunction($functionName);
        }
    }

    public function listAllFunctions()
    {

        $client = new LambdaClient([
            'region' => config('aws.prod_region'),
            'version' => 'latest',
            'credentials' => [
                'key' => config('aws.prod_key'),
                'secret' => config('aws.prod_secret'),
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
            } catch (AwsException $e) {
                Log::error('Error listing Lambda functions: ' . $e->getMessage());
                break;
            }
        } while ($nextMarker);

        return $functions;
    }

    private function updateLambdaFunction($functionName)
    {
        $lambda = new LambdaClient([
            'region' => config('aws.prod_region'),
            'version' => 'latest',
            'credentials' => [
                'key' => config('aws.prod_key'),
                'secret' => config('aws.prod_secret'),
            ],
        ]);

        try {

            $lambda->deleteFunctionConcurrency([
                'FunctionName' => $functionName
            ]);

            $lambda->updateFunctionConfiguration([
                'FunctionName' => $functionName,
                'MemorySize' => 512,
                'State' => 'Active',
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
