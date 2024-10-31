<?php

namespace App\Console\Commands\Extra;

use Aws\Ecs\EcsClient;
use Illuminate\Console\Command;

class CheckS3Bucket extends Command
{
    protected $signature = 'check:bucket';

    public function handle()
    {
        $variableName = "multiple-api-store";

        $taskDefinitions = $this->getAllTaskDefinitions();

        $results = [];

        foreach ($taskDefinitions as $taskDefinitionArn) {
            $taskDefinition = $this->getTaskDefinitionDetails($taskDefinitionArn);
            $variableValue = $this->checkEnvironmentVariable($taskDefinition, $variableName);

            if ($variableValue !== null) {
                $results[$taskDefinitionArn] = $variableValue;
            }
        }
    }

    public function getAllTaskDefinitions()
    {
        $ecsClient = new EcsClient([
            'region' => config('aws.prod_region'),
            'version' => 'latest',
            'credentials' => [
                'key' => config('aws.prod_key'),
                'secret' => config('aws.prod_secret'),
            ],
        ]);

        $taskDefinitions = [];
        $nextToken = null;

        do {
            $result = $ecsClient->listTaskDefinitions([
                'nextToken' => $nextToken,
            ]);

            $taskDefinitions = array_merge($taskDefinitions, $result->get('taskDefinitionArns'));
            $nextToken = $result->get('nextToken');
        } while ($nextToken);

        return $taskDefinitions;
    }

    public function getTaskDefinitionDetails($taskDefinitionArn)
    {
        $ecsClient = new EcsClient([
            'region' => config('aws.prod_region'),
            'version' => 'latest',
            'credentials' => [
                'key' => config('aws.prod_key'),
                'secret' => config('aws.prod_secret'),
            ],
        ]);

        $result = $ecsClient->describeTaskDefinition([
            'taskDefinition' => $taskDefinitionArn,
        ]);

        return $result->get('taskDefinition');
    }

    public function checkEnvironmentVariable($taskDefinition, $variableName)
    {
        foreach ($taskDefinition['containerDefinitions'] as $containerDefinition) {
            foreach ($containerDefinition['environment'] as $envVar) {
                $parts = explode('/', $containerDefinition['image']);
                $appServiceName = end($parts);

//                if ($envVar['name'] === $variableName) {
//                    return $envVar['value'];
//                }

                if ($envVar['value'] === $variableName) {
                    return $appServiceName;
                }
            }
        }

        return null;
    }

}
