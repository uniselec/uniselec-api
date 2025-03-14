<?php
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\User\ApplicationController as UserApplicationController;
use App\Http\Controllers\Admin\ApplicationController as AdminApplicationController;
use App\Http\Controllers\Admin\ApplicationOutcomeController;
use App\Http\Controllers\Admin\DocumentController as AdminDocumentController;
use App\Http\Controllers\Admin\EnemScoreController;
use App\Http\Controllers\Admin\ProcessApplicationOutcomeController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Public\DocumentController as PublicDocumentController;

use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\RegisterController;


use Illuminate\Support\Facades\Route;
use Illuminate\Support\Carbon;


Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/me', [AuthController::class, 'me'])->name('user.profile');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('applications', [UserApplicationController::class, 'index'])->name('applications.index');
    Route::get('applications/{application}', [UserApplicationController::class, 'show'])->name('applications.show');
    Route::post('applications', [UserApplicationController::class, 'store'])->name('applications.store');
});