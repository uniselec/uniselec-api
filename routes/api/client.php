<?php

use App\Http\Controllers\Admin\DocumentController;
use App\Http\Controllers\Admin\ProcessSelectionController;
use App\Http\Controllers\Client\ApplicationController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Client\AppealController;
use App\Http\Controllers\Client\AppealDocumentController;
use App\Http\Controllers\Public\EnrollmentVerificationController;
use Illuminate\Support\Facades\Route;



Route::middleware(['auth:sanctum'])->prefix('client')->group(function () {
    // Route::get('/me', [AuthController::class, 'me'])->name('user.profile');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::apiResource('process_selections', ProcessSelectionController::class)->only(['index', 'show'])->names('client.processSelection');
    Route::apiResource('documents', DocumentController::class)->only(['index', 'show'])->names('client.documents');
    Route::apiResource('applications', ApplicationController::class)->only(['index', 'show', 'store'])->names('client.applications');
    Route::apiResource('process_selections', ProcessSelectionController::class)->only(['index', 'show'])->names('admin.processSelection');
    Route::apiResource('documents', DocumentController::class)->only(['index', 'show'])->names('admin.documents');
    Route::apiResource('applications', ApplicationController::class)->only(['index', 'show', 'store'])->names('admin.documents');
    Route::apiResource('appeals', AppealController::class)->names('client.appeals');
    Route::prefix('appeals/{appeal}')->group(function () {
        Route::get('/appeal_documents/{appealDocument}', [AppealDocumentController::class, 'show'])
        ->name('appeal.documents.show');
        Route::post('/appeal_documents', [AppealDocumentController::class, 'store'])
        ->name('appeal.documents.store');
        Route::delete('/appeal_documents/{appealDocument}', [AppealDocumentController::class, 'destroy'])
        ->name('appeal.documents.destroy');
        Route::get('/appeal_documents/{appealDocument}/download', [AppealDocumentController::class, 'download']);
    });


    Route::put('profile', [RegisterController::class, 'updateProfileClient'])->name('client.profile.update');

    Route::apiResource('enrollment_verification', EnrollmentVerificationController::class)->only(['show'])->names('client.enrollment_verification');

});