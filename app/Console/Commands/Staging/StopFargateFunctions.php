<?php

namespace App\Console\Commands\Staging;

use Aws\Ecs\EcsClient;
use GuzzleHttp\Client;
use Illuminate\Console\Command;

class StopFargateFunctions extends Command
{
    protected $signature = 'fargate:stop';

    public function handle()
    {
        $clusters = ['iposg-cloud-staging-fargate-cluster'];

        foreach ($clusters as $cluster) {
            $services = $this->listServices($cluster);
            foreach ($services as $service) {
                $this->updateServiceDesiredCount($cluster, $service);
            }
        }
        $this->slack();
    }

    public function listServices($clusterName)
    {
        $fargate = new EcsClient([
            'region' => config('aws.stage_region'),
            'version' => 'latest',
            'credentials' => [
                'key' => config('aws.stage_key'),
                'secret' => config('aws.stage_secret'),
            ],
        ]);

        $result = $fargate->listServices([
            'cluster' => $clusterName,
        ]);

        return $result->get('serviceArns');
    }

    public function updateServiceDesiredCount($clusterName, $serviceName)
    {
        $fargate = new EcsClient([
            'region' => config('aws.stage_region'),
            'version' => 'latest',
            'credentials' => [
                'key' => config('aws.stage_key'),
                'secret' => config('aws.stage_secret'),
            ],
        ]);

        $result = $fargate->updateService([
            'cluster' => $clusterName,
            'service' => $serviceName,
            'desiredCount' => 0,
        ]);

        return $result;
    }

    function slack()
    {
        $client = new Client();

        $message = [
            'attachments' => [
                [
                    'title' => "Fargate Service - Staging",
                    'text' => "All the Fargate services have been stopped."
                ],
            ],
        ];

        $client->post('https://hooks.slack.com/services/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', [
            'json' => $message,
        ]);
    }


}
