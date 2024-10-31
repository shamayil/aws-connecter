<?php

namespace App\Console\Commands\Tag;

use Aws\Ec2\Ec2Client;
use Aws\Ecs\EcsClient;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class EC2TagFunctions extends Command
{
    protected $signature = 'ec2:tag';

    public function handle()
    {
        $enviroments = ['master', 'production', 'staging'];

        foreach ($enviroments as $env) {
            $this->tagECS($env);
        }
    }

    public function tagECS($env)
    {
        if ($env == 'master') {
            $ec2Client = new Ec2Client([
                'region' => config('aws.master_region'),
                'version' => 'latest',
                'credentials' => [
                    'key' => config('aws.master_key'),
                    'secret' => config('aws.master_secret'),
                ],
            ]);

            $ecsClient = new EcsClient([
                'region' => config('aws.master_region'),
                'version' => 'latest',
                'credentials' => [
                    'key' => config('aws.master_key'),
                    'secret' => config('aws.master_secret'),
                ],
            ]);
        } else if ($env == 'production') {
            $ec2Client = new Ec2Client([
                'region' => config('aws.prod_region'),
                'version' => 'latest',
                'credentials' => [
                    'key' => config('aws.prod_key'),
                    'secret' => config('aws.prod_secret'),
                ],
            ]);

            $ecsClient = new EcsClient([
                'region' => config('aws.prod_region'),
                'version' => 'latest',
                'credentials' => [
                    'key' => config('aws.prod_key'),
                    'secret' => config('aws.prod_secret'),
                ],
            ]);
        } else if ($env == 'staging') {
            $ec2Client = new Ec2Client([
                'region' => config('aws.stage_region'),
                'version' => 'latest',
                'credentials' => [
                    'key' => config('aws.stage_key'),
                    'secret' => config('aws.stage_secret'),
                ],
            ]);

            $ecsClient = new EcsClient([
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

        $result = $ecsClient->listClusters();
        $clusters = $result->get('clusterArns');

        foreach ($clusters as $clusterArn) {

            $containerInstances = $ecsClient->listContainerInstances([
                'cluster' => $clusterArn,
            ])['containerInstanceArns'];

            try {
                $instances = $ecsClient->describeContainerInstances([
                    'cluster' => $clusterArn,
                    'containerInstances' => $containerInstances,
                ])['containerInstances'];
            } catch (Exception $ex) {
                Log::error("$env tagging error " . $ex->getMessage());
                continue;
            }

            $instanceIds = array_map(function ($instance) {
                $container = [
                    'containerInstanceArn' => $instance['containerInstanceArn'],
                    'ec2InstanceId' => $instance['ec2InstanceId']
                ];
                return $container;
            }, $instances);

            foreach ($instanceIds as $instanceId) {

                $instance = end($instanceId);
                $parts = explode('/', $instanceId['containerInstanceArn']);
                $clusterName = $parts[count($parts) - 2];

                $ec2Client->createTags([
                    'Resources' => [$instance],
                    'Tags' => [
                        [
                            'Key' => 'Name',
                            'Value' => $clusterName
                        ],
                        [
                            'Key' => 'Environment',
                            'Value' => $env
                        ],
                    ],
                ]);
            }
        }
    }
}
