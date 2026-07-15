<?php

use App\Http\Controllers\LocalSessionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/login', [LocalSessionController::class, 'store'])
    ->middleware('throttle:local-login')
    ->name('login.store');

Route::post('/logout', [LocalSessionController::class, 'destroy'])
    ->name('logout');

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'sicode-core',
    ]);
});
