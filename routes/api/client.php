<?php

use App\Http\Controllers\Admin\DocumentController;
use App\Http\Controllers\Admin\ProcessSelectionController;
use App\Http\Controllers\Client\ApplicationController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Public\EnrollmentVerificationController;
use Illuminate\Support\Facades\Route;



Route::middleware(['auth:sanctum'])->prefix('client')->group(function () {
    Route::get('/me', [AuthController::class, 'me'])->name('user.profile');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::apiResource('process_selections', ProcessSelectionController::class)->only(['index', 'show'])->names('admin.processSelection');
    Route::apiResource('documents', DocumentController::class)->only(['index', 'show'])->names('admin.documents');
    Route::apiResource('applications', ApplicationController::class)->only(['index', 'show', 'store'])->names('admin.documents');

    Route::put('profile', [RegisterController::class, 'updateProfileClient'])->name('client.profile.update');

    Route::apiResource('enrollment_verification', EnrollmentVerificationController::class)->only(['show']);

});