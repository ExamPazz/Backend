<?php

use App\Http\Controllers\Admin\SubscriptionPlanController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\ExamDetailController;
use App\Http\Controllers\Auth\GoogleAuthController;
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
use App\Http\Controllers\PaystackWebhookController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\ContactFormController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\User;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Str;
use App\Http\Controllers\UtmeDateController;
use App\Http\Controllers\ExamGenerationPercentageController;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
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
        Route::post('/user/restore', [UserProfileController::class, 'restore']);
        Route::post('/convert-images', [CsvImportController::class, 'migrateImagesToCloudinaryBySubject']);

        Route::post('/test', [CsvImportController::class, 'test']);


        Route::middleware(['web'])->group(function () {
            Route::get('auth/redirect', function () {
                session(['redirect_url' => url()->previous()]); // Store previous URL
                return Socialite::driver('google')->redirect();
            });

            Route::post('auth/callback', function () {
                Log::info('Google callback route accessed', request()->all());
                $googleUser = Socialite::driver('google')->stateless()->user();
                $user = User::where('email', $googleUser->email)->first();

                if ($user) {
                    // If the user exists, just update Google ID and token (DO NOT overwrite password)
                    $user->update([
                        'google_id' => $googleUser->id,
                    ]);
                } else {
                    // If the user does not exist, create a new one
                    $user = User::create([
                        'email' => $googleUser->email,
                        'full_name' => $googleUser->name,
                        'google_id' => $googleUser->id,
                        'password' => bcrypt(Str::random(16)), // Only set password for new users
                    ]);
                }

                $hasExamDetail = $user->latestExamDetail()->exists();
                $freemiumPlan = SubscriptionPlan::where('name', 'freemium')->first();

                if ($freemiumPlan) {
                    Subscription::firstOrCreate([
                        'user_id' => $user->id,
                        'subscription_plan_id' => $freemiumPlan->id,
                    ], [
                        'expires_at' => now()->addDays(30),
                    ]);
                }

                $redirectUrl = session('redirect_url', 'https://exampazz.com/dashboard');

                return redirect()->away($redirectUrl)->with([
                    'message' => 'Authenticated successfully',
                    'user' => $user->load('subscription.subscriptionPlan'),
                    'has_exam_detail' => $hasExamDetail,
                    'token' => $user->createToken('API Token')->plainTextToken,
                ]);
            });
        });
        // Route::post('questions/import', [CsvImportController::class, 'importQuestions']);
        Route::post('questions/import', [CsvImportController::class, 'importCsv']);
        Route::post('keys/import/chem', [ImportKeyController::class, 'importStructureForChem']);
        Route::post('keys/import/geo', [ImportKeyController::class, 'importStructureforGeo']);
        Route::post('keys/import/comm', [ImportKeyController::class, 'importStructureForComm']);
        Route::post('keys/import/Gov', [ImportKeyController::class, 'importStructureForGov']);
        Route::post('keys/import/Econ', [ImportKeyController::class, 'importStructureForEcon']);
        Route::post('keys/import/bio', [ImportKeyController::class, 'importStructureForBio']);
        Route::post('keys/import/eng', [ImportKeyController::class, 'importStructureForEng']);
        Route::post('keys/import/lit-eng', [ImportKeyController::class, 'importStructureForLiteng']);
        Route::post('keys/import/maths', [ImportKeyController::class, 'importStructureForMaths']);
        Route::post('keys/import/acc', [ImportKeyController::class, 'importStructureForAcc']);
        Route::post('keys/import/phy', [ImportKeyController::class, 'importStructureForPhy']);
        Route::post('images', [CsvImportController::class, 'imageS3']);



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
            Route::delete('/user/delete', [UserProfileController::class, 'deleteUserAccount']);
            Route::post('/mock-exam', [MockExamController::class, 'generateMockExam']);
            Route::post('/mock-exam/answers', [MockExamController::class, 'storeUserAnswer']);
            Route::post('/mock-exam/{mockExamId}/calculate', [MockExamController::class, 'calculateScore']);
            Route::post('/mock-exam/{mockExamId}/finalize', [MockExamController::class, 'finalizeExam']);
            Route::get('/mock-exam/{mockExamId}/details', [MockExamController::class, 'getMockExamDetails']);
            Route::resource('subjects', SubjectController::class);
            Route::get('/user/analysis', [PerformanceAnalysisController::class, 'getUserExamAnalysis']);
            Route::get('/user/subjects/analysis', [PerformanceAnalysisController::class, 'getOverallSubjectAnalysis']);
            Route::get('/user/subjects-performance', [PerformanceAnalysisController::class, 'getUserSubjectsPerformance']);
            Route::get('/user/mock-exams', [PerformanceAnalysisController::class, 'getUserMockExams']);
            Route::get('/user/mock-exams/count', [PerformanceAnalysisController::class, 'getUserMockExamsCount']);
            Route::get('/user/weak-area', [PerformanceAnalysisController::class, 'getUserWeakAreas']);
            Route::post('/subscription/initiate', [SubscriptionController::class, 'initiate']);
            Route::get('/subscription/verify', [SubscriptionController::class, 'verify'])->name('subscription.verify');

            Route::prefix('notifications')->group(function () {
                Route::get('/', [NotificationController::class, 'index']);
                Route::get('/{id}', [NotificationController::class, 'show']);
                Route::patch('/{id}/read', [NotificationController::class, 'markAsRead']);
                Route::patch('/mark-all-read', [NotificationController::class, 'markAllAsRead']);
                Route::delete('/{id}', [NotificationController::class, 'destroy']);
            });
            Route::post('/utme-date', [UtmeDateController::class, 'store']);
            Route::get('/utme-date', [UtmeDateController::class, 'show']);
        });

        Route::post('/exam-generation-percentage/import', [ExamGenerationPercentageController::class, 'importFromCsv']);

        Route::post('webhook/paystack', [PaystackWebhookController::class, 'handle']);

        Route::prefix('newsletter')->group(function () {
            Route::post('subscribe', [NewsletterController::class, 'subscribe']);
            Route::get('unsubscribe/{email}', [NewsletterController::class, 'unsubscribe']);
        });

        Route::post('contact', [ContactFormController::class, 'submit']);

    
        Route::group([
            'prefix' => 'auth/google',
            'middleware' => ['api'],
        ], function () {
            Route::get('url', [GoogleAuthController::class, 'getAuthUrl']);
            Route::post('callback', [GoogleAuthController::class, 'handleCallback']);
        });
    });
