<?php

use App\Http\Controllers\Admin\AcademicUnitController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AdmissionCategoryController;
use App\Http\Controllers\Admin\ApplicationController;
use App\Http\Controllers\Admin\ApplicationOutcomeController;
use App\Http\Controllers\Admin\BonusOptionController;
use App\Http\Controllers\Admin\ConvocationCallerController;
use App\Http\Controllers\Admin\ConvocationListApplicationController;
use App\Http\Controllers\Admin\ConvocationListApplicationGenerationController;
use App\Http\Controllers\Admin\ConvocationListApplicationResponseController;
use App\Http\Controllers\Admin\ConvocationListController;
use App\Http\Controllers\Admin\ConvocationListSeatAllocationController;
use App\Http\Controllers\Admin\ConvocationListSeatController;
use App\Http\Controllers\Admin\ConvocationListSeatGenerationController;
use App\Http\Controllers\Admin\ConvocationListSeatRedistributionController;
use App\Http\Controllers\Admin\CourseController;
use App\Http\Controllers\Admin\DocumentController;
use App\Http\Controllers\Admin\EnemScoreController;
use App\Http\Controllers\Admin\EnemScoreImportController;
use App\Http\Controllers\Admin\KnowledgeAreaController;
use App\Http\Controllers\Admin\ProcessApplicationOutcomeController;
use App\Http\Controllers\Admin\ProcessSelectionController;
use App\Http\Controllers\Admin\ProcessSelectionNotifyController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\RegisterController;


use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->prefix('admin')->group(function () {
    Route::middleware(['abilities:super_user'])->prefix('super_user')->group(function () {
        Route::post('/resend-password-link', [AdminController::class, 'resendPasswordResetLink'])->name('admin.resend-password-link');
        Route::post('/resend-password-link-user', [UserController::class, 'resendPasswordResetLink'])->name('admin.resend-password-link-user');



        Route::apiResource('admins', AdminController::class)->names('admin.admins');
        Route::apiResource('academic_units', AcademicUnitController::class)->names('admin.academic_units');
        Route::apiResource('courses', CourseController::class)->names('admin.courses');
        Route::apiResource('process_selections', ProcessSelectionController::class)->names('admin.processSelection');
        Route::apiResource('admission_categories', AdmissionCategoryController::class)->names('admin.admission_categories');
        Route::apiResource('bonus_options', BonusOptionController::class)->names('admin.bonus_options');
        Route::apiResource('documents', DocumentController::class)->names('documents.api');
        Route::apiResource('knowledge_areas', KnowledgeAreaController::class)->names('admin.knowledge_areas');

        Route::patch('documents/{id}/status', [DocumentController::class, 'updateStatus'])->name('documents.updateStatus');

        Route::apiResource('applications', ApplicationController::class)->only(['index', 'show'])->names('admin.applications');
        Route::apiResource('application_outcomes', ApplicationOutcomeController::class)->only(['index', 'show'])->names('admin.applications');
        Route::apiResource('users', UserController::class)->only(['index', 'show']);
        Route::apiResource('enem_scores', EnemScoreController::class)->only(['index', 'show'])->names('enem_scores.api');;
        Route::put('profile', [RegisterController::class, 'updateProfileAdmin'])->name('admin.super_user.profile.update');

        Route::apiResource('convocation_list_applications', ConvocationListApplicationController::class)->names('admin.super_user.convocation_list_applications');
        Route::apiResource('convocation_lists', ConvocationListController::class)->names('admin.super_user.convocation_lists');
        Route::apiResource('convocation_list_seats', ConvocationListSeatController::class)->names('admin.super_user.convocation_list_seats');

        Route::post('convocation_lists/{list}/generate-seats', [ConvocationListSeatGenerationController::class, 'store'])->name('admin.super_user.cconvocation_lists.generate_seats');
        Route::post('convocation_lists/{convocationList}/generate-applications', [ConvocationListApplicationGenerationController::class, 'store'])->name('convocation_lists.generate_applications');
        Route::post('convocation_lists/{convocationList}/allocate-seats', [ConvocationListSeatAllocationController::class, 'store'])->name('convocation_lists.allocate_seats');
        Route::post('convocation_lists/{convocationList}/redistribute-seats', [ConvocationListSeatRedistributionController::class, 'store'])->name('convocation_lists.redistribute_seats');
        Route::post(
            'convocation_list_applications/{cla}/call',
            ConvocationCallerController::class
        )->name('admin.super_user.convocation_list_applications.call');

        Route::post(
            'convocation_list_seats/{seat}/redistribute',
            [ConvocationListSeatRedistributionController::class, 'redistribute']
        )->name('convocation_list_seats.redistribute');

        Route::prefix('convocation_list_applications')->group(function () {
            Route::post('{cla}/call',    [ConvocationListApplicationResponseController::class, 'call']);
            Route::post('{cla}/accept',  [ConvocationListApplicationResponseController::class, 'accept']);
            Route::post('{cla}/decline', [ConvocationListApplicationResponseController::class, 'decline']);
            Route::post('{cla}/reject',  [ConvocationListApplicationResponseController::class, 'reject']);
        });

        Route::post('process_selections/{selection}/outcomes', [ProcessApplicationOutcomeController::class, 'processOutcomes']);
        // Route::post('process_selections/{selection}/outcomes_without_pending', [ProcessApplicationOutcomeController::class, 'processOutcomesWithoutPending']);

        Route::patch('application_outcomes/{id}', [ApplicationOutcomeController::class, 'patchUpdate'])->name('application-outcomes.patch');
        Route::get('/process_selections/{selection}/notify-status', [ProcessSelectionNotifyController::class, 'notifyByStatus']);

        Route::post('enem_scores/import', EnemScoreImportController::class)->name('enem_scores.import');
        Route::post('/logout', [AuthController::class, 'logout'])->name('admin.logout');
        Route::get('/me', [AuthController::class, 'me'])->name('user.profile');
        Route::post('/register', [RegisterController::class, 'registerAdmin'])->name('admin.register');
    });
});
