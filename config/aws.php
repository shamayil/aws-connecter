<?php

return [

    'prod_key' => env('PROD_AWS_ACCESS_KEY_ID'),
    'prod_secret' => env('PROD_AWS_SECRET_ACCESS_KEY'),
    'prod_region' => env('PROD_AWS_DEFAULT_REGION', 'eu-west-2'),

    'stage_key' => env('STAGE_AWS_ACCESS_KEY_ID'),
    'stage_secret' => env('STAGE_AWS_SECRET_ACCESS_KEY'),
    'stage_region' => env('STAGE_AWS_DEFAULT_REGION', 'eu-west-2'),

    'master_key' => env('MASTER_AWS_ACCESS_KEY_ID'),
    'master_secret' => env('MASTER_AWS_SECRET_ACCESS_KEY'),
    'master_region' => env('MASTER_AWS_DEFAULT_REGION', 'eu-west-2'),
];
