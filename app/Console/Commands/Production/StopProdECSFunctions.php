<?php

namespace App\Console\Commands\Production;

use Aws\AutoScaling\AutoScalingClient;
use Aws\Ecs\EcsClient;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class StopProdECSFunctions extends Command
{
    protected $signature = 'stop-prod-ecs';

    public function handle()
    {
        $ecs = new EcsClient([
            'region' => config('aws.prod_region'),
            'version' => 'latest',
            'credentials' => [
                'key' => config('aws.prod_key'),
                'secret' => config('aws.prod_secret'),
            ],
        ]);

        $scale = new AutoScalingClient([
            'region' => config('aws.prod_region'),
            'version' => 'latest',
            'credentials' => [
                'key' => config('aws.prod_key'),
                'secret' => config('aws.prod_secret'),
            ],
        ]);

        $clusterNames = ['intouch-data-cluster', 'talysis-data-cluster',
            'cloud-data-trans-cluster', 'cloud-data-trans-dls-cluster',
            'headoffice-data-trans-cluster', 'headoffice-data-trans-dls-cluster'];

        foreach ($clusterNames as $clusterName) {
            $cluster = $this->getClustersByPartialName($clusterName)[0]['clusterName'];
            $service = $this->getServiceByPartialName($cluster)[0];

            $serviceDescriptions = isset($scale->describeAutoScalingGroups([0])->toArray()['AutoScalingGroups']) ? $scale->describeAutoScalingGroups([0])->toArray()['AutoScalingGroups'] : null;

            if ($serviceDescriptions) {

                foreach ($serviceDescriptions as $serviceDescript) {

                    if (Str::contains($serviceDescript['AutoScalingGroupName'], $cluster)) {
                        $scale->updateAutoScalingGroup([
                            'AutoScalingGroupName' => $serviceDescript['AutoScalingGroupName'],
                            'DesiredCapacity' => 0,
                            'MinSize' => 0,
                            'MaxSize' => 0,
                        ]);
                    }
                }
            }

            $params = [
                'cluster' => $cluster,
                'service' => $service,
                'desiredCount' => 0,
                'forceNewDeployment' => true,
            ];

            $ecs->updateService($params);

            $this->slack($clusterName);
        }

    }

    public function getClustersByPartialName($partialName)
    {
        $clusters = [];

        $client = new EcsClient([
            'region' => config('aws.prod_region'),
            'version' => 'latest',
            'credentials' => [
                'key' => config('aws.prod_key'),
                'secret' => config('aws.prod_secret'),
            ],
        ]);

        try {
            $result = $client->listClusters();
            $clusterArns = $result->get('clusterArns');

            if (empty($clusterArns)) {
                return $clusters;
            }

            $describedClusters = $client->describeClusters([
                'clusters' => $clusterArns
            ]);

            foreach ($describedClusters['clusters'] as $cluster) {
                if (strpos($cluster['clusterName'], $partialName) !== false) {
                    $clusters[] = $cluster;
                }
            }
        } catch (Exception $e) {
            return [
                'data' => [],
                'status' => 'ERROR',
                'message' => 'Error on stop ECS Cluster.',
                'errors' => $e->getAwsErrorMessage()
            ];
        }

        return $clusters;
    }

    public function getServiceByPartialName($partialName)
    {
        $client = new EcsClient([
            'region' => config('aws.prod_region'),
            'version' => 'latest',
            'credentials' => [
                'key' => config('aws.prod_key'),
                'secret' => config('aws.prod_secret'),
            ],
        ]);

        $result = $client->listServices([
            'cluster' => $partialName,
        ]);

        $services = $result->get('serviceArns');
        $matchedServices = [];

        foreach ($services as $serviceArn) {
            if (strpos($serviceArn, $partialName) !== false) {
                $matchedServices[] = $serviceArn;
            }
        }

        return $matchedServices;
    }

    function slack($clusterName)
    {
        $client = new Client();

        $message = [
            'attachments' => [
                [
                    'title' => 'ECS Service - Production',
                    'text' => "Data ECS cluster - $clusterName and it's services have been stopped."
                ],
            ],
        ];

        $client->post('https://hooks.slack.com/services/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', [
            'json' => $message,
        ]);
    }
}
