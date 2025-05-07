<?php


use App\Http\Controllers\Public\DocumentController;
use App\Http\Controllers\Public\ProcessSelectionController;
use Illuminate\Support\Facades\Route;



Route::apiResource('process_selections', ProcessSelectionController::class)->only(['index', 'show'])->names('admin.processSelection');
Route::apiResource('documents', DocumentController::class)->only(['index', 'show'])->names('admin.documents');