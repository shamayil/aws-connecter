<?php

namespace App\Console;

use App\Console\Commands\Billing\ForcastBillFunctions;
use App\Console\Commands\Billing\GetBillFunctions;
use App\Console\Commands\CloudWatch\CloudWatchRetentionFunctions;
use App\Console\Commands\CloudWatch\ManageCloudWatchFunctions;
use App\Console\Commands\Extra\RDSPerformanceInsights;
use App\Console\Commands\Master\BillByServiceFunctions;
use App\Console\Commands\Production\StartProdECSFunctions;
use App\Console\Commands\Production\StartProdRDSFunctions;
use App\Console\Commands\Production\StopProdECSFunctions;
use App\Console\Commands\Production\StopProdRDSFunctions;
use App\Console\Commands\Production\UpdateProdLambdaFunctions;
use App\Console\Commands\Staging\StopECSFunctions;
use App\Console\Commands\Staging\StopFargateFunctions;
use App\Console\Commands\Staging\StopLambdaFunctions;
use App\Console\Commands\Staging\StopRDSFunctions;
use App\Console\Commands\Staging\UpdateLambdaFunctions;
use App\Console\Commands\Tag\CloudWatchTagFunctions;
use App\Console\Commands\Tag\EC2TagFunctions;
use App\Console\Commands\Tag\LambdaTagFunctions;
use App\Console\Commands\Tag\RDSTagFunctions;
use App\Console\Commands\Tag\S3BucketTagFunctions;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{

    protected function schedule(Schedule $schedule): void
    {
        ///////////////////////
        // aws billing jobs ///
        //////////////////////

        $schedule->command(RDSPerformanceInsights::class)
            ->timezone('Europe/London')
            ->cron('*/5 * * * *');

        $schedule->command(BillByServiceFunctions::class)
            ->timezone('Europe/London')
            ->cron('0 6 * * 1');

        $schedule->command(ForcastBillFunctions::class)
            ->timezone('Europe/London')
            ->cron('50 7 * * *');

        $schedule->command(GetBillFunctions::class)
            ->timezone('Europe/London')
            ->cron('00 8 * * *');

        //////////////////////////////////////
        // aws staging function(s) stopping //
        /////////////////////////////////////

        $schedule->command(StopRDSFunctions::class)
            ->timezone('Europe/London')
            ->cron('00 15 * * *');

        $schedule->command(StopECSFunctions::class)
            ->timezone('Europe/London')
            ->cron('00 15 * * *');

        $schedule->command(StopFargateFunctions::class)
            ->timezone('Europe/London')
            ->cron('00 15 * * *');

        $schedule->command(StopLambdaFunctions::class)
            ->timezone('Europe/London')
            ->cron('00 15 * * *');

        /////////////////////////////
        // aws services management //
        ////////////////////////////

        $schedule->command(ManageCloudWatchFunctions::class)
            ->timezone('Europe/London')
            ->cron('15 * * * *');

        $schedule->command(CloudWatchRetentionFunctions::class)
            ->timezone('Europe/London')
            ->cron('0 */3 * * *');

        $schedule->command(UpdateLambdaFunctions::class)
            ->timezone('Europe/London')
            ->cron('0 */4 * * *');

        ///////////////////////////
        // aws functions tagging //
        //////////////////////////

        $schedule->command(LambdaTagFunctions::class)
            ->timezone('Europe/London')
            ->cron('30 6 * * *');

        $schedule->command(CloudWatchTagFunctions::class)
            ->timezone('Europe/London')
            ->cron('0 23 * * *');

        $schedule->command(EC2TagFunctions::class)
            ->timezone('Europe/London')
            ->cron('10 23 * * *');

        $schedule->command(RDSTagFunctions::class)
            ->timezone('Europe/London')
            ->cron('20 23 * * *');

        $schedule->command(S3BucketTagFunctions::class)
            ->timezone('Europe/London')
            ->cron('25 23 * * *');

        /////////////////////////////////////
        // AWS Production Lambda services //
        ////////////////////////////////////

        $schedule->command(UpdateProdLambdaFunctions::class)
            ->timezone('Europe/London')
            ->cron('30 00 * * *');

        //////////////////////////////////
        // AWS Production Data Services //
        /////////////////////////////////

        $schedule->command(StartProdECSFunctions::class)
            ->timezone('Europe/London')
            ->cron('45 01 * * *');

        $schedule->command(StartProdRDSFunctions::class)
            ->timezone('Europe/London')
            ->cron('30-45/2 1 * * *');

        $schedule->command(StopProdRDSFunctions::class)
            ->timezone('Europe/London')
            ->cron('30 15 * * *');

        $schedule->command(StopProdECSFunctions::class)
            ->timezone('Europe/London')
            ->cron('30 15 * * *');
    }

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');
        require base_path('routes/console.php');
    }
}
