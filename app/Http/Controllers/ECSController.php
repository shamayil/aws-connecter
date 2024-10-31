<?php

namespace App\Http\Controllers;

use Aws\AutoScaling\AutoScalingClient;
use Aws\Ecs\EcsClient;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;


class ECSController extends Controller
{

    public function startTask(Request $request)
    {
        $apis = $request->api;

        $ecs = new EcsClient([
            'region' => config('aws.stage_region'),
            'version' => 'latest',
            'credentials' => [
                'key' => config('aws.stage_key'),
                'secret' => config('aws.stage_secret'),
            ],
        ]);

        $scale = new AutoScalingClient([
            'region' => config('aws.stage_region'),
            'version' => 'latest',
            'credentials' => [
                'key' => config('aws.stage_key'),
                'secret' => config('aws.stage_secret'),
            ],
        ]);

        if ($apis == []) {
            return [
                'data' => [],
                'status' => 'OK',
                'message' => 'ECS not found.',
                'errors' => null
            ];
        }

        foreach ($apis as $api) {

            $cluster = $this->getClustersByPartialName($api)[0]['clusterName'];
            $service = $this->getServiceByPartialName($cluster)[0];
            $serviceDescriptions = isset($scale->describeAutoScalingGroups([0])->toArray()['AutoScalingGroups']) ? $scale->describeAutoScalingGroups([0])->toArray()['AutoScalingGroups'] : null;

            if ($serviceDescriptions) {

                foreach ($serviceDescriptions as $serviceDescript) {

                    if (Str::contains($serviceDescript['AutoScalingGroupName'], $cluster)) {
                        $scale->updateAutoScalingGroup([
                            'AutoScalingGroupName' => $serviceDescript['AutoScalingGroupName'],
                            'DesiredCapacity' => 1,
                            'MinSize' => 0,
                            'MaxSize' => 1,
                        ]);
                    }
                }
            }

            $params = [
                'cluster' => $cluster,
                'service' => $service,
                'desiredCount' => 1,
                'forceNewDeployment' => true,
            ];

            $env = 'Staging';
            $service = 'ECS';
            $name = $cluster;
            $trigger = 'started';
            $this->slack($env, $service, $name, $trigger);

            $ecs->updateService($params);
        }


        return [
            'data' => [],
            'status' => 'OK',
            'message' => 'ECS started successfully',
            'errors' => null
        ];
    }

    public function getClustersByPartialName($partialName)
    {
        $clusters = [];

        $client = new EcsClient([
            'region' => config('aws.stage_region'),
            'version' => 'latest',
            'credentials' => [
                'key' => config('aws.stage_key'),
                'secret' => config('aws.stage_secret'),
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
            Log::error('Error fetching clusters: ' . $e->getMessage());
        }

        return $clusters;
    }

    public function getServiceByPartialName($partialName)
    {
        $client = new EcsClient([
            'region' => config('aws.stage_region'),
            'version' => 'latest',
            'credentials' => [
                'key' => config('aws.stage_key'),
                'secret' => config('aws.stage_secret'),
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

    function slack($env, $service, $name, $trigger)
    {
        $client = new Client();

        $message = [
            'attachments' => [
                [
                    'title' => "$env $service",
                    'text' => "The *$name* has been *$trigger*."
                ],
            ],
        ];

        $client->post('https://hooks.slack.com/services/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', [
            'json' => $message,
        ]);
    }

    public function stopTask(Request $request)
    {
        $apis = $request->api;

        $ecs = new EcsClient([
            'region' => config('aws.stage_region'),
            'version' => 'latest',
            'credentials' => [
                'key' => config('aws.stage_key'),
                'secret' => config('aws.stage_secret'),
            ],
        ]);

        $scale = new AutoScalingClient([
            'region' => config('aws.stage_region'),
            'version' => 'latest',
            'credentials' => [
                'key' => config('aws.stage_key'),
                'secret' => config('aws.stage_secret'),
            ],
        ]);

        if ($apis == []) {
            return [
                'data' => [],
                'status' => 'OK',
                'message' => 'ECS not found.',
                'errors' => null
            ];
        }

        foreach ($apis as $api) {

            $cluster = $this->getClustersByPartialName($api)[0]['clusterName'];
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

            $env = 'Staging';
            $service = 'ECS';
            $name = $cluster;
            $trigger = 'stopped';
            $this->slack($env, $service, $name, $trigger);

            $ecs->updateService($params);
        }

        return [
            'data' => [],
            'status' => 'OK',
            'message' => 'ECS Stopped successfully',
            'errors' => null
        ];

    }

}
