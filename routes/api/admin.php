<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\User\ApplicationController as UserApplicationController;
use App\Http\Controllers\Admin\ApplicationController as AdminApplicationController;
use App\Http\Controllers\Admin\ApplicationOutcomeController;
use App\Http\Controllers\Admin\CourseController;
use App\Http\Controllers\Admin\DocumentController;
use App\Http\Controllers\Admin\EnemScoreController;
use App\Http\Controllers\Admin\ProcessApplicationOutcomeController;
use App\Http\Controllers\Admin\ProcessSelectionController;
use App\Http\Controllers\Admin\ProcessSelectionCourseController;
use App\Http\Controllers\Admin\UserController as AdminUserController;

use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\RegisterController;


use Illuminate\Support\Facades\Route;
use Illuminate\Support\Carbon;

Route::middleware(['auth:sanctum', 'abilities:admin'])->prefix('admin')->group(function () {
    Route::put('change-password', [AuthController::class, 'changeAdminPassword'])->name('admin.changePassword');
    Route::get('applications', [AdminApplicationController::class, 'index'])->name('applications.index');

    Route::post('/process-outcomes', [ProcessApplicationOutcomeController::class, 'processOutcomes']);
    Route::post('/process-outcomes-without-pending', [ProcessApplicationOutcomeController::class, 'processOutcomesWithoutPending']);
    Route::post('process-selection/{processSelection}/courses', [ProcessSelectionCourseController::class, 'sync'])
        ->name('admin.process_selection.courses.sync');
    Route::delete('process-selection/course/remove', [ProcessSelectionCourseController::class, 'remove'])
        ->name('admin.process_selection.courses.remove');

    Route::apiResource('process_selections', ProcessSelectionController::class)->names('admin.processSelection');


    Route::apiResource('courses', CourseController::class)->names('admin.courses');
    Route::apiResource('enem-scores', EnemScoreController::class)->names('enem_scores.api');

    Route::apiResource('admins', AdminController::class)->names('admins.api');

    Route::get('application-outcomes', [ApplicationOutcomeController::class, 'index'])->name('application-outcomes.api.index');

    Route::get('application-outcomes/{id}', [ApplicationOutcomeController::class, 'show'])->name('application-outcomes.api.show');
    Route::patch('application-outcomes/{id}', [ApplicationOutcomeController::class, 'patchUpdate'])->name('application-outcomes.patch');


    Route::get('applications/{application}', [AdminApplicationController::class, 'show'])->name('applications.api.show');

    Route::post('/logout', [AuthController::class, 'logout'])->name('admin.logout');
    Route::get('/me', [AuthController::class, 'me'])->name('user.profile');
    Route::post('/register', [RegisterController::class, 'registerAdmin'])->name('admin.register');

    Route::apiResource('documents', DocumentController::class)->names('documents.api');
    Route::patch('documents/{id}/status', [DocumentController::class, 'updateStatus'])->name('documents.updateStatus');

    Route::get('users', [AdminUserController::class, 'index'])->name('admin.users.index');
});
