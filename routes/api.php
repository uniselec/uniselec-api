<?php

use App\Http\Controllers\Api\ApplicationController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/
Route::prefix('backoffice')->group(function() {
    Route::post('/login', [AuthController::class, 'authAdmin'])->name('backoffice.login');
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/me', [AuthController::class, 'me'])->name('user.profile');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    // Route::apiResource('users', UserController::class)->names('users');
    Route::apiResource('applications', ApplicationController::class)->names('applications');

});

Route::middleware(['auth:sanctum', 'abilities:admin'])->prefix('backoffice')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout'])->name('backoffice.logout');
    Route::get('/me', [AuthController::class, 'me'])->name('user.profile');
    Route::post('/register', [RegisterController::class, 'registerAdmin'])->name('backoffice.register');

    Route::get('/sou-admin', function () {
        echo "sou sim";
    });
});

Route::post('/login', [AuthController::class, 'auth'])->name('user.login');
Route::post('/register', [RegisterController::class, 'register'])->name('register');