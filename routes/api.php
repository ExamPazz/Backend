<?php

use App\Http\Controllers\Admin\SubscriptionPlanController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\ExamDetailController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\OTPController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\ResetPasswordController;
use App\Http\Controllers\UserProfileController;
use App\Http\Controllers\CsvImportController;
use App\Http\Controllers\MockExamController;
use App\Http\Controllers\PerfomanceAnalysisController;
use App\Http\Controllers\SubjectController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::group(
    [
        'prefix' => 'v1'
    ], function () {
        Route::post('login', [LoginController::class, 'login']);
        Route::post('register', [RegistrationController::class, 'register']);
        Route::post('email/verify', [EmailVerificationController::class, 'verifyOtp']);
        Route::post('email/verify/code/resend', [EmailVerificationController::class, 'resendOtp']);
        Route::post('password/forgot', [ResetPasswordController::class, 'forgotPassword']);
        Route::post('password/reset/code/verify', [ResetPasswordController::class, 'verifyOtp']);
        Route::post('password/reset', [ResetPasswordController::class, 'reset']);
        Route::post('password/reset/code/resend', [ResetPasswordController::class, 'resendOtp']);
        Route::post('code/send/whatsapp', [OTPController::class, 'sendViaWhatsApp']);
        Route::post('auth/google', [GoogleAuthController::class, 'store']);

        Route::post('/csv', [CsvImportController::class, 'importCsv']);

        Route::group(['prefix' => 'subscription-plan'], function () {
            Route::post('store', [SubscriptionPlanController::class, 'store']);
            Route::get('{uuid}/show', [SubscriptionPlanController::class, 'show']);
            Route::patch('{uuid}/update', [SubscriptionPlanController::class, 'update']);
            Route::delete('{uuid}/delete', [SubscriptionPlanController::class, 'delete']);
        });


        Route::middleware('auth:sanctum')->group(function () {
            Route::resource('exam-details', ExamDetailController::class)->except(['index']);
            Route::get('/user/profile', [UserProfileController::class, 'getAuthenticatedUser']);
            Route::put('/user/profile', [UserProfileController::class, 'updateUser']);
            Route::post('/mock-exam', [MockExamController::class, 'generateMockExam']);
            Route::post('/mock-exam/answers', [MockExamController::class, 'storeUserAnswer']);
            Route::post('/mock-exam/{mockExamId}/calculate', [MockExamController::class, 'calculateScore']);
            Route::post('/mock-exam/{mockExamId}/finalize', [MockExamController::class, 'finalizeExam']);
            Route::get('/mock-exam/{mockExamId}/details', [MockExamController::class, 'getMockExamDetails']);
            Route::resource('subjects', SubjectController::class);
            Route::get('/user/analysis', [PerfomanceAnalysisController::class, 'getUserExamAnalysis']);
        });
    });
