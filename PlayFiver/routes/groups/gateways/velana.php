<?php

use App\Http\Controllers\Gateway\VelanaController;
use Illuminate\Support\Facades\Route;

// This route is for the logged-in user to generate a PIX
Route::middleware(['auth:sanctum'])->prefix('velana')->group(function () {
    Route::post('/deposit', [VelanaController::class, 'deposit'])->name('velana.deposit');
});

// This route is for Velana to send a notification. It must be public.
Route::prefix('velana')->group(function () {
    Route::post('/webhook', [VelanaController::class, 'webhook'])->name('velana.webhook');
}); 