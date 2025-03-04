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


Route::get('documents/{id}', [PublicDocumentController::class, 'show'])->name('documents.show');
Route::get('documents', [PublicDocumentController::class, 'index'])->name('documents.index');

Route::get('/server-time', function () {
    return response()->json([
        'serverTime' => now()->toDateTimeString(),
    ]);
})->name('server.time');


Route::get('/student-selection', function () {
    $start = Carbon::parse(env('REGISTRATION_START', '2024-08-02 08:00:00'));
    $end = Carbon::parse(env('REGISTRATION_END', '2024-08-03 23:59:00'));
    $now = Carbon::now();
    $isAfterStart = $now->greaterThanOrEqualTo($start);
    $isInPeriod = $now->between($start, $end);
    return response()->json([
        'studentSelection' => [
            'start' => $start->toDateTimeString(),
            'end' => $end->toDateTimeString(),
            'isAfterStart' => $isAfterStart,
            'isInPeriod' => $isInPeriod,
        ]
    ]);
});

