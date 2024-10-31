<?php

namespace App\Console\Commands\Extra;

use Aws\CloudWatch\CloudWatchClient;
use Aws\Exception\AwsException;
use DateTime;
use GuzzleHttp\Client;
use Illuminate\Console\Command;

class RDSPerformanceInsights extends Command
{
    protected $signature = 'rds-performance-insights';

    public function handle()
    {
        $dbInstanceIdentifier = 'iposg-cloud-db-instance-1';
        $startTime = (new DateTime('-10 minutes'))->format(DateTime::ISO8601);
        $endTime = (new DateTime())->format(DateTime::ISO8601);


        $cloudWatchClient = new CloudWatchClient([
            'region' => config('aws.prod_region'),
            'version' => 'latest',
            'credentials' => [
                'key' => config('aws.prod_key'),
                'secret' => config('aws.prod_secret'),
            ],
        ]);

        try {
            $result = $cloudWatchClient->getMetricStatistics([
                'Namespace' => 'AWS/RDS',
                'MetricName' => 'CPUUtilization',
                'Dimensions' => [
                    [
                        'Name' => 'DBInstanceIdentifier',
                        'Value' => $dbInstanceIdentifier,
                    ],
                ],
                'StartTime' => strtotime($startTime),
                'EndTime' => strtotime($endTime),
                'Period' => 600,
                'Statistics' => ['Average'],
            ]);

            $averageCpu = $result['Datapoints'][0]['Average'];
            $cpuOutput = round($averageCpu, 2);

            if ($cpuOutput > 85) {
                $text = ('Cloud database CPU usage: ' . $cpuOutput . "%\n");

                $client = new Client();

                $message = [
                    'attachments' => [
                        [
                            'title' => 'RDS - Cloud DB',
                            'text' => $text
                        ],
                    ],
                ];

                $client->post('https://hooks.slack.com/services/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', [
                    'json' => $message,
                ]);
            }

        } catch (AwsException $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
