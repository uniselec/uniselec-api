<?php

use App\Http\Controllers\Admin\AcademicUnitController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AdmissionCategoryController;
use App\Http\Controllers\Admin\ApplicationController;
use App\Http\Controllers\Admin\ApplicationOutcomeController;
use App\Http\Controllers\Admin\BonusOptionController;
use App\Http\Controllers\Admin\ConvocationListApplicationController;
use App\Http\Controllers\Admin\ConvocationListController;
use App\Http\Controllers\Admin\ConvocationListSeatController;
use App\Http\Controllers\Admin\ConvocationListSeatRedistributionController;
use App\Http\Controllers\Admin\CourseController;
use App\Http\Controllers\Admin\DocumentController;
use App\Http\Controllers\Admin\EnemScoreController;
use App\Http\Controllers\Admin\EnemScoreImportController;
use App\Http\Controllers\Admin\KnowledgeAreaController;
use App\Http\Controllers\Admin\ProcessApplicationOutcomeController;
use App\Http\Controllers\Admin\ProcessSelectionController;
use App\Http\Controllers\Admin\ProcessSelectionNotifyController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\RegisterController;


use Illuminate\Support\Facades\Route;
use Illuminate\Support\Carbon;

Route::middleware(['auth:sanctum'])->prefix('admin')->group(function () {
    Route::middleware(['abilities:promoter'])->prefix('promoter')->group(function () {
        Route::post('/resend-password-link', [AdminController::class, 'resendPasswordResetLink'])->name('admin.promoter.resend-password-link');
        Route::post('/resend-password-link-user', [UserController::class, 'resendPasswordResetLink'])->name('admin.resend-password-link-user');


        Route::get('process_selections/{selection}/applications/export',[ReportController::class, 'exportApplications'])->name('admin.promoter.processSelection.applications.export');


        Route::apiResource('admins', AdminController::class)->names('admin.promoter.admins');
        Route::apiResource('academic_units', AcademicUnitController::class)->names('admin.promoter.academic_units');
        Route::apiResource('courses', CourseController::class)->names('admin.promoter.courses');
        Route::apiResource('process_selections', ProcessSelectionController::class)->names('admin.promoter.processSelection');
        Route::apiResource('admission_categories', AdmissionCategoryController::class)->names('admin.promoter.admission_categories');
        Route::apiResource('bonus_options', BonusOptionController::class)->names('admin.promoter.bonus_options');
        Route::apiResource('documents', DocumentController::class)->names('documents.api');
        Route::apiResource('knowledge_areas', KnowledgeAreaController::class)->names('admin.promoter.knowledge_areas');

        Route::patch('documents/{id}/status', [DocumentController::class, 'updateStatus'])->name('documents.updateStatus');

        Route::apiResource('applications', ApplicationController::class)->only(['index', 'show'])->names('admin.promoter.applications');


        Route::apiResource('application_outcomes', ApplicationOutcomeController::class)->only(['index', 'show'])->names('admin.promoter.application_outcomes');
        Route::patch('applications/{application_id}/resolve-inconsistencies', [ApplicationController::class, 'resolveInconsistencies'])->name('admin.promoter.applications.resolve-inconsistencies');



        Route::apiResource('users', UserController::class)->only(['index', 'show']);
        Route::apiResource('enem_scores', EnemScoreController::class)->only(['index', 'show'])->names('enem_scores.api');;
        Route::put('profile', [RegisterController::class, 'updateProfileAdmin'])->name('admin.promoter.profile.update');

        Route::apiResource('convocation_list_applications', ConvocationListApplicationController::class)->names('admin.promoter.convocation_list_applications');
        Route::apiResource('convocation_lists', ConvocationListController::class)->names('admin.promoter.convocation_lists');
        Route::apiResource('convocation_list_seats', ConvocationListSeatController::class)->names('admin.promoter.convocation_list_seats');
        Route::post('convocation_lists/{convocationList}/redistribute-seats',[ConvocationListSeatRedistributionController::class, 'store'])->name('convocation_lists.redistribute_seats');

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
