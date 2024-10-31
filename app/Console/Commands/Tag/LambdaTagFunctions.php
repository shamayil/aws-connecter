<?php

namespace App\Console\Commands\Tag;

use Aws\Lambda\LambdaClient;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class LambdaTagFunctions extends Command
{
    protected $signature = 'lambda:tag';

    public function handle()
    {
        $enviroments = ['master', 'production', 'staging'];

        foreach ($enviroments as $env) {

            if ($env == 'master') {
                $lambda = new LambdaClient([
                    'region' => config('aws.master_region'),
                    'version' => 'latest',
                    'credentials' => [
                        'key' => config('aws.master_key'),
                        'secret' => config('aws.master_secret'),
                    ],
                ]);
            } else if ($env == 'production') {
                $lambda = new LambdaClient([
                    'region' => config('aws.prod_region'),
                    'version' => 'latest',
                    'credentials' => [
                        'key' => config('aws.prod_key'),
                        'secret' => config('aws.prod_secret'),
                    ],
                ]);
            } else if ($env == 'staging') {
                $lambda = new LambdaClient([
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

            foreach ($results as $result) {

                $functionName = $result['FunctionName'];
                $tags = [
                    'Name' => $functionName,
                    'Environment' => env('APP_ENV'),
                ];
                $lambda->tagResource([
                    'Resource' => $result['FunctionArn'],
                    'Tags' => $tags,
                ]);
                echo "Tagged function: {$functionName}\n";
            }
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
            return "Not Found";
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
