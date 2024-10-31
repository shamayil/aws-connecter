<?php

namespace App\Http\Controllers;

use Aws\Ecs\EcsClient;
use GuzzleHttp\Client;
use Illuminate\Http\Request;

class FargateController extends Controller
{
    public function startFargate(Request $request)
    {
        $apis = $request->api;

        if ($apis == []) {
            return [
                'data' => [],
                'status' => 'OK',
                'message' => 'Fargate not found.',
                'errors' => null
            ];
        }

        foreach ($apis as $cluster) {
            $services = $this->listServices($cluster);
            foreach ($services as $service) {
                $this->updateServiceDesiredCount($cluster, $service, 1);
                $trigger = 'Started';
                $this->slack($trigger, $cluster);
            }
        }
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

    public function updateServiceDesiredCount($clusterName, $serviceName, $desireCount)
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
            'desiredCount' => $desireCount,
        ]);

        return $result;
    }

    function slack($trigger, $cluster)
    {
        $client = new Client();

        $message = [
            'attachments' => [
                [
                    'title' => 'Fargate Service - Staging',
                    'text' => "Fargate service(s) $cluster have been $trigger."
                ],
            ],
        ];

        $client->post('https://hooks.slack.com/services/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', [
            'json' => $message,
        ]);
    }

    public function stopFargate(Request $request)
    {
        $apis = $request->api;

        if ($apis == []) {
            return [
                'data' => [],
                'status' => 'OK',
                'message' => 'Fargate not found.',
                'errors' => null
            ];
        }

        foreach ($apis as $cluster) {
            $services = $this->listServices($cluster);
            foreach ($services as $service) {
                $this->updateServiceDesiredCount($cluster, $service, 0);
                $trigger = 'Stopped';
                $this->slack($trigger, $cluster);
            }
        }
    }
}
