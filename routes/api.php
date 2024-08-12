<?php

use App\Http\Controllers\User\ApplicationController as UserApplicationController;
use App\Http\Controllers\Admin\ApplicationController as AdminApplicationController;
use App\Http\Controllers\Admin\ApplicationStatusController;
use App\Http\Controllers\Admin\DocumentController as AdminDocumentController;
use App\Http\Controllers\Admin\EnemScoreController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Public\DocumentController as PublicDocumentController;

use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\RegisterController;


use Illuminate\Support\Facades\Route;
use Illuminate\Support\Carbon;
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

Route::prefix('backoffice')->group(function () {
    Route::post('/login', [AuthController::class, 'authAdmin'])->name('backoffice.login');
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/me', [AuthController::class, 'me'])->name('user.profile');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('applications', [UserApplicationController::class, 'index'])->name('applications.index');
    Route::get('applications/{application}', [UserApplicationController::class, 'show'])->name('applications.show');
    Route::post('applications', [UserApplicationController::class, 'store'])->name('applications.store');
});

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

Route::middleware(['auth:sanctum', 'abilities:admin'])->prefix('backoffice')->group(function () {
    Route::put('change-password', [AuthController::class, 'changeAdminPassword'])->name('admin.changePassword');
    Route::get('applications', [AdminApplicationController::class, 'index'])->name('applications.index');


    Route::apiResource('enem-scores', EnemScoreController::class)->names('enem_scores.api');
    Route::apiResource('application-status', ApplicationStatusController::class)->names('application-status.api');

    Route::get('applications/{application}', [AdminApplicationController::class, 'show'])->name('applications.api.show');

    Route::post('/logout', [AuthController::class, 'logout'])->name('backoffice.logout');
    Route::get('/me', [AuthController::class, 'me'])->name('user.profile');
    Route::post('/register', [RegisterController::class, 'registerAdmin'])->name('backoffice.register');

    Route::post('documents', [AdminDocumentController::class, 'store'])->name('documents.store');
    Route::put('documents/{id}', [AdminDocumentController::class, 'update'])->name('documents.update');
    Route::delete('documents/{id}', [AdminDocumentController::class, 'destroy'])->name('documents.destroy');
    Route::get('users', [AdminUserController::class, 'index'])->name('backoffice.users.index');


});
Route::get('documents/{id}', [PublicDocumentController::class, 'show'])->name('documents.show');
Route::get('documents', [PublicDocumentController::class, 'index'])->name('documents.index');
Route::post('/login', [AuthController::class, 'auth'])->name('user.login');
Route::post('/register', [RegisterController::class, 'register'])->name('register');

Route::post('/password/forgot', [PasswordResetController::class, 'forgot'])->name('password.forgot');
Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('password.reset');

Route::get('/server-time', function () {
    return response()->json([
        'serverTime' => now()->toDateTimeString(),
    ]);
})->name('server.time');