<?php

// use App\Http\Controllers\Client\AppealDocumentController;

use App\Http\Controllers\Admin\AppealController as AdminAppealController;
use App\Http\Controllers\Client\AppealDocumentController;
use App\Http\Controllers\Client\AppealController;
use App\Http\Controllers\Public\DocumentController;
use App\Http\Controllers\Public\EnrollmentVerificationController;
use App\Http\Controllers\Public\ProcessSelectionController;
use Illuminate\Support\Facades\Route;



Route::apiResource('process_selections', ProcessSelectionController::class)->only(['index', 'show'])->names('admin.processSelection');
Route::apiResource('documents', DocumentController::class)->only(['index', 'show'])->names('admin.documents');
Route::apiResource('enrollment_verification', EnrollmentVerificationController::class)->only(['show']);


Route::apiResource('appeals', AppealController::class)->names('client.appeals');
Route::prefix('appeals/{appeal}')->group(function () {
  Route::post('/document', [AppealDocumentController::class, 'store'])->name('appeal.document.store');
  Route::delete('/document/{document}', [AppealDocumentController::class, 'destroy'])->name('appeal.document.destroy');
});

Route::apiResource('admin/appeals', AdminAppealController::class)->names('admin.appeals');