<?php

use App\Http\Controllers\ApplicationLaunchExchangeController;
use Illuminate\Support\Facades\Route;

Route::post('/core/launch/exchange', [ApplicationLaunchExchangeController::class, 'store'])
    ->name('core.launch.exchange');
