<?php

use Illuminate\Support\Facades\Route;


Route::middleware(['check.weekend.admin'])->group(function () {

    Route::patch('/rds/start', [App\Http\Controllers\RDSController::class, 'startDbInstance']);
    Route::patch('/rds/stop', [App\Http\Controllers\RDSController::class, 'stopDbInstance']);

    Route::patch('/lambda/start', [App\Http\Controllers\LambdaController::class, 'startLambda']);
    Route::patch('/lambda/stop', [App\Http\Controllers\LambdaController::class, 'stopLambda']);

    Route::patch('/ecs/start', [App\Http\Controllers\ECSController::class, 'startTask']);
    Route::patch('/ecs/stop', [App\Http\Controllers\ECSController::class, 'stopTask']);

    Route::patch('/fargate/start', [App\Http\Controllers\FargateController::class, 'startFargate']);
    Route::patch('/fargate/stop', [App\Http\Controllers\FargateController::class, 'stopFargate']);

});
