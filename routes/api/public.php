<?php


use App\Http\Controllers\Public\DocumentController;
use App\Http\Controllers\Public\EnrollmentVerificationController;
use App\Http\Controllers\Public\ProcessSelectionController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HealthController;

Route::get('health/ready', [HealthController::class, 'ready']);
Route::apiResource('process_selections', ProcessSelectionController::class)->only(['index', 'show'])->names('admin.processSelection');
Route::apiResource('documents', DocumentController::class)->only(['index', 'show'])->names('admin.documents');
Route::apiResource('enrollment_verification', EnrollmentVerificationController::class)->only(['show']);