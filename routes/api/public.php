<?php


use App\Http\Controllers\Public\DocumentController as PublicDocumentController;
use App\Http\Controllers\Public\ProcessSelectionController;
use Illuminate\Support\Facades\Route;

Route::apiResource('process_selections', ProcessSelectionController::class)->names('admin.processSelection');
Route::get('documents/{id}', [PublicDocumentController::class, 'show'])->name('documents.show');
Route::get('documents', [PublicDocumentController::class, 'index'])->name('documents.index');