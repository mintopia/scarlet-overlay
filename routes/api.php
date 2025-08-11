<?php

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Controllers\Api\V1\PingController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('gps', [ApiController::class, 'gps']);
    Route::get('weather', [ApiController::class, 'weather']);
    Route::get('ping', [PingController::class, 'ping']);
});
