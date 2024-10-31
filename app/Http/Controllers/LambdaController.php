<?php

namespace App\Http\Controllers;

use Aws\Lambda\LambdaClient;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;


class LambdaController extends Controller
{

    public function startLambda(Request $request)
    {
        $data = $request->header('User');

        if (!$data) {
            return [
                'data' => [],
                'status' => 'ERROR',
                'message' => 'User not found.',
                'errors' => null
            ];
        }

        $users = ['kishor', 'kaji', 'dharani', 'dilip', 'narmathan', 'lakshika'];

        $userData = decrypt($data);
        $parts = explode('_', $userData);
        $user = end($parts);

        if (!in_array($user, $users)) {
            return [
                'data' => [],
                'status' => 'ERROR',
                'message' => "User $user not found.",
                'errors' => null
            ];
        }

        $apis = $request->api;

        if ($apis == []) {
            return [
                'data' => [],
                'status' => 'OK',
                'message' => 'Lambda not found.',
                'errors' => null
            ];
        }

        $client = new LambdaClient([
            'region' => config('aws.stage_region'),
            'version' => 'latest',
            'credentials' => [
                'key' => config('aws.stage_key'),
                'secret' => config('aws.stage_secret'),
            ],
        ]);

        foreach ($apis as $api) {

            $functions = $this->getFunctionByPartialName($api);

            foreach ($functions as $function) {

                try {
                    $client->deleteFunctionConcurrency([
                        'FunctionName' => $function['FunctionName']
                    ]);
                    $client->updateFunctionConfiguration([
                        'FunctionName' => $function['FunctionName'],
                        'MemorySize' => 512,
                        'State' => 'Active',
                    ]);
                    
                    if (strpos($function['FunctionName'], 'cli') !== false
                        || strpos($function['FunctionName'], 'queue') !== false) {
                        continue;
                    }

                    $env = 'Staging';
                    $service = 'Lambda';
                    $name = $function['FunctionName'];
                    $trigger = 'started';
                    $this->slack($env, $service, $name, $trigger, $user);

                } catch (Exception $ex) {
                    Log::error('Lambda updateFunctionConfiguration ::: ' . $ex->getMessage());
                }
            }
        }

        return [
            'data' => [],
            'status' => 'OK',
            'message' => 'Lambda started successfully.',
            'errors' => null
        ];
    }

    public function getFunctionByPartialName($partialName)
    {
        $functions = [];
        $nextMarker = null;

        $client = new LambdaClient([
            'region' => config('aws.stage_region'),
            'version' => 'latest',
            'credentials' => [
                'key' => config('aws.stage_key'),
                'secret' => config('aws.stage_secret'),
            ],
        ]);

        do {
            $result = $client->listFunctions([
                'Marker' => $nextMarker
            ]);

            $functions = array_merge($functions, $result->get('Functions'));
            $nextMarker = $result->get('NextMarker');
        } while ($nextMarker);

        $filteredFunctions = array_filter($functions, function ($function) use ($partialName) {
            return strpos($function['FunctionName'], $partialName) !== false;
        });

        return $filteredFunctions;
    }

    function slack($env, $service, $name, $trigger, $user)
    {
        $client = new Client();

        $message = [
            'attachments' => [
                [
                    'title' => "$env $service",
                    'text' => "The *$name* has been *$trigger* by user *$user*"
                ],
            ],
        ];

        $client->post('https://hooks.slack.com/services/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', [
            'json' => $message,
        ]);
    }

    public function stopLambda(Request $request)
    {
        $data = $request->header('User');

        if (!$data) {
            return [
                'data' => [],
                'status' => 'ERROR',
                'message' => 'User not found.',
                'errors' => "user $data not found"
            ];
        }

        $userData = decrypt($data);
        $parts = explode('_', $userData);
        $user = end($parts);

        $apis = $request->api;

        if ($apis == []) {
            return [
                'data' => [],
                'status' => 'OK',
                'message' => 'API not found.',
                'errors' => null
            ];
        }

        $client = new LambdaClient([
            'region' => config('aws.stage_region'),
            'version' => 'latest',
            'credentials' => [
                'key' => config('aws.stage_key'),
                'secret' => config('aws.stage_secret'),
            ],
        ]);

        foreach ($apis as $api) {

            $functions = $this->getFunctionByPartialName($api);

            foreach ($functions as $function) {

                try {
                    $reservedConcurrentExecutions = 0;

                    try {
                        $client->putFunctionConcurrency([
                            'FunctionName' => $function['FunctionName'],
                            'ReservedConcurrentExecutions' => $reservedConcurrentExecutions,
                        ]);

                        if (strpos($function['FunctionName'], 'cli') !== false
                            || strpos($function['FunctionName'], 'queue') !== false) {
                            continue;
                        }

                        $env = 'Staging';
                        $service = 'Lambda';
                        $name = $function['FunctionName'];
                        $trigger = "stopped";
                        $this->slack($env, $service, $name, $trigger, $user);

                    } catch (Throwable $ex) {
                        Log::error($ex->getMessage());
                    }
                } catch (Exception $ex) {
                    Log::error('Lambda updateFunctionConfiguration ::: ' . $ex->getMessage());
                }
            }
        }

        return [
            'data' => [],
            'status' => 'OK',
            'message' => 'Lambda stopped successfully.',
            'errors' => null
        ];
    }

}
