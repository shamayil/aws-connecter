<?php

namespace App\Providers;

use Aws\CostExplorer\CostExplorerClient;
use Aws\Sdk;
use Illuminate\Support\ServiceProvider;

class AWSServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(CostExplorerClient::class, function ($app) {
            return new CostExplorerClient([
                'version' => 'latest',
                'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
                'credentials' => [
                    'key' => env('AWS_ACCESS_KEY_ID'),
                    'secret' => env('AWS_SECRET_ACCESS_KEY'),
                ],
            ]);
        });

        $this->app->singleton('aws', function ($app) {
            return new Sdk([
                'region' => config('aws.region'),
                'version' => 'latest',
                'credentials' => [
                    'key' => config('aws.key'),
                    'secret' => config('aws.secret'),
                ],
            ]);
        });
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

}
