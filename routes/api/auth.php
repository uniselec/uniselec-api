<?php


use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\RegisterController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'auth'])->name('user.login');
Route::post('/register', [RegisterController::class, 'register'])->name('register');

Route::post('/password/forgot', [PasswordResetController::class, 'forgot'])->name('password.forgot');
Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('password.reset');

Route::prefix('admin')->group(function () {
    Route::post('/login', [AuthController::class, 'authAdmin'])->name('admin.login');
});
