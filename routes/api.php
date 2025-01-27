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
use App\Http\Controllers\ImportKeyController;
use App\Http\Controllers\MockExamController;
use App\Http\Controllers\PerfomanceAnalysisController;
use App\Http\Controllers\PerformanceAnalysisController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\NotificationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\User;
use Illuminate\Support\Str;

use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

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

        Route::middleware(['web'])->group(function () {
            Route::get('auth/redirect', function () {
                return Socialite::driver('google')->redirect();
            });
            Route::get('auth/callback', function () {
                $googleUser = Socialite::driver('google')->stateless()->user();
            
                $user = User::updateOrCreate(
                    ['email' => $googleUser->email], 
                    ['google_id' => $googleUser->id,
                ],
    
                    [
                        'google_id' => $googleUser->id,
                        'full_name' => $googleUser->name,
                        'password' => bcrypt(Str::random(16)),                    
                        'google_token' => $googleUser->token,
                    ]
                );
            
                // Auth::login($user);
                $hasExamDetail = $user->latestExamDetail()->exists();
            
                return response()->json([
                    'message' => 'Authenticated successfully',
                    'user' => $user->load('subscription.subscriptionPlan'),
                    'has_exam_detail' => $hasExamDetail,
                    'token' => $user->createToken('API Token')->plainTextToken,
                ]);
            });
        });
        // Route::post('questions/import', [CsvImportController::class, 'importQuestions']);
        Route::post('questions/import', [CsvImportController::class, 'importCsv']);
        // Route::post('keys/import/Comm', [ImportKeyController::class, 'importStructureForComm']);
        // Route::post('keys/import/Eng', [ImportKeyController::class, 'importStructureForEng']);
        // Route::post('keys/import/Bio', [ImportKeyController::class, 'importStructureForBio']);
        Route::post('keys/import/Gov', [ImportKeyController::class, 'importStructureForGov']);
        Route::post('keys/import/Econ', [ImportKeyController::class, 'importStructureForEcon']);



        Route::group(['prefix' => 'subscription-plan'], function () {
            Route::post('store', [SubscriptionPlanController::class, 'store']);
            Route::get('index', [SubscriptionPlanController::class, 'index']);
            Route::get('{uuid}/show', [SubscriptionPlanController::class, 'show']);
            Route::patch('{uuid}/update', [SubscriptionPlanController::class, 'update']);
            Route::delete('{uuid}/delete', [SubscriptionPlanController::class, 'delete']);
        });


        Route::middleware('auth:sanctum')->group(function () {
            Route::get('exam-details', [ExamDetailController::class, 'show']);
            Route::put('exam-details', [ExamDetailController::class, 'update']);
            Route::resource('exam-details', ExamDetailController::class)->except(['index', 'show', 'update']);
            Route::get('/user/profile', [UserProfileController::class, 'getAuthenticatedUser']);
            Route::put('/user/profile', [UserProfileController::class, 'updateUser']);
            Route::post('/mock-exam', [MockExamController::class, 'generateMockExam']);
            Route::post('/mock-exam/answers', [MockExamController::class, 'storeUserAnswer']);
            Route::post('/mock-exam/{mockExamId}/calculate', [MockExamController::class, 'calculateScore']);
            Route::post('/mock-exam/{mockExamId}/finalize', [MockExamController::class, 'finalizeExam']);
            Route::get('/mock-exam/{mockExamId}/details', [MockExamController::class, 'getMockExamDetails']);
            Route::resource('subjects', SubjectController::class);
            Route::get('/user/analysis', [PerformanceAnalysisController::class, 'getUserExamAnalysis']);
            Route::get('/user/subjects/analysis', [PerformanceAnalysisController::class, 'getOverallSubjectAnalysis']);
            Route::get('/user/mock-exams', [PerformanceAnalysisController::class, 'getUserMockExams']);
            Route::get('/user/mock-exams/count', [PerformanceAnalysisController::class, 'getUserMockExamsCount']);
            Route::post('/subscription/initiate', [SubscriptionController::class, 'initiate']);
            Route::get('/subscription/verify', [SubscriptionController::class, 'verify'])->name('subscription.verify');

            Route::prefix('notifications')->group(function () {
                Route::get('/', [NotificationController::class, 'index']);
                Route::get('/{id}', [NotificationController::class, 'show']);
                Route::patch('/{id}/read', [NotificationController::class, 'markAsRead']);
                Route::patch('/mark-all-read', [NotificationController::class, 'markAllAsRead']);
                Route::delete('/{id}', [NotificationController::class, 'destroy']);
            });
        });
    });
