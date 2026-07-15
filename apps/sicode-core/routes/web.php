<?php

use App\Http\Controllers\ApplicationLaunchController;
use App\Http\Controllers\HubController;
use App\Http\Controllers\LocalSessionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('hub');
});

Route::get('/login', [LocalSessionController::class, 'create'])
    ->name('login');

Route::post('/login', [LocalSessionController::class, 'store'])
    ->middleware('throttle:local-login')
    ->name('login.store');

Route::post('/logout', [LocalSessionController::class, 'destroy'])
    ->name('logout');

Route::get('/hub', HubController::class)
    ->name('hub');

Route::post('/applications/{application}/launch', [ApplicationLaunchController::class, 'store'])
    ->name('applications.launch');

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'sicode-core',
    ]);
});
