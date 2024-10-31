<?php

namespace App\Console\Commands\Tag;

use Aws\S3\S3Client;
use Illuminate\Console\Command;
use Throwable;

class S3BucketTagFunctions extends Command
{
    protected $signature = 's3:tag';

    public function handle()
    {
        $enviroments = ['master', 'production', 'staging'];
        $bucketName = [];

        foreach ($enviroments as $env) {

            if ($env == 'master') {
                $s3 = new S3Client([
                    'region' => config('aws.master_region'),
                    'version' => 'latest',
                    'credentials' => [
                        'key' => config('aws.master_key'),
                        'secret' => config('aws.master_secret'),
                    ],
                ]);
            } else if ($env == 'production') {
                $s3 = new S3Client([
                    'region' => config('aws.prod_region'),
                    'version' => 'latest',
                    'credentials' => [
                        'key' => config('aws.prod_key'),
                        'secret' => config('aws.prod_secret'),
                    ],
                ]);
            } else if ($env == 'staging') {
                $s3 = new S3Client([
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

            $result = $s3->listBuckets();

            foreach ($result['Buckets'] as $key => $bucket) {

                $location = $s3->getBucketLocation(['Bucket' => $bucket['Name']]);
                $region = $location['LocationConstraint'];

                try {

                    $bucketName[$key] = $bucket['Name'];

                    $s3->putBucketTagging([
                        'Bucket' => $bucket['Name'],
                        'Tagging' => [
                            'TagSet' => [
                                [
                                    'Key' => 'Name',
                                    'Value' => $bucket['Name'],
                                ],
                                [
                                    'Key' => 'Environment',
                                    'Value' => $env,
                                ],
                            ],
                        ],
                    ]);
                } catch (Throwable $ex) {
                    echo ' region - ' . $region . "\n";
                    continue;
                }
            }
        }
    }

}
