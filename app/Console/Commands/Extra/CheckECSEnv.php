<?php

namespace App\Console\Commands\Extra;

use Aws\Ecs\EcsClient;
use Illuminate\Console\Command;

class CheckECSEnv extends Command
{
    protected $signature = 'check:ecs:env';

    public function handle()
    {
        $ecsClient = new EcsClient([
            'region' => config('aws.prod_region'),
            'version' => 'latest',
            'credentials' => [
                'key' => config('aws.prod_key'),
                'secret' => config('aws.prod_secret'),
            ],
        ]);

        $clusters = $ecsClient->listClusters();
        $clusterArns = $clusters->get('clusterArns');

        $data = [];

        foreach ($clusterArns as $clusterArn) {
            $services = $ecsClient->listServices(['cluster' => $clusterArn]);
            $serviceArns = $services->get('serviceArns');

            foreach ($serviceArns as $serviceArn) {
                $taskDefinitions = $ecsClient->describeServices([
                    'cluster' => $clusterArn,
                    'services' => [$serviceArn],
                ]);

                foreach ($taskDefinitions->get('services') as $service) {
                    $taskDefinitionArn = $service['taskDefinition'];
                    $taskDefinition = $ecsClient->describeTaskDefinition([
                        'taskDefinition' => $taskDefinitionArn,
                    ]);

                    $containerDefinitions = $taskDefinition->get('taskDefinition')['containerDefinitions'];
                    $envVars = [];

                    foreach ($containerDefinitions as $containerDefinition) {
                        if (isset($containerDefinition['environment'])) {
                            foreach ($containerDefinition['environment'] as $envVar) {
                                if ($envVar['value'] == 'multiple-api-store') {
                                    $envVars[$envVar['name']] = $envVar['value'];
                                }
                            }
                        }
                    }

                    $data[] = [
                        'cluster' => $clusterArn,
                        'service' => $serviceArn,
                        'env' => $envVars,
                    ];
                }
            }
        }
        dd($data);
    }
}
