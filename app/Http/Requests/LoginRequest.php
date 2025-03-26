<?php

namespace App\Http\Requests;

use App\Enums\Roles;
use App\Events\NewUserRegistrationEvent;
use App\Events\UserRegistrationEvent;
use App\Exceptions\AppException;
use App\Models\Role;
use App\Models\User;
use App\Models\FcmToken;
use App\Support\ApiResponse;
use App\Support\OtpHelper;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * @property string $email
 * @property string $password
 */
class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'exists:users'],
            'password' => ['required', 'string'],
            'fcm_token' => ['nullable', 'string'], // Validate FCM token
        ];
    }


    public function authenticate(): mixed
    {
        $this->ensureIsNotRateLimited();

        $user = User::query()
            ->where('email', $this->email)->first();


        if ($user->google_id) {
            return ApiResponse::failure('You cannot login with email and password, please use Google', statusCode: 400);
        }

        if (!$user || !Hash::check($this->password, $user->password)) {
            RateLimiter::hit($this->throttleKey());
            return ApiResponse::failure("Credentials do not match our records.");
        }

        if (!$user->is_active)
        {
            return ApiResponse::failure('Account is inactive, please contact Administrator', statusCode: 403);
        }

        if (is_null($user->email_verified_at)) {
            $otpAction = new OtpHelper();
            $response = $otpAction->generateOtp($user);
            event(new NewUserRegistrationEvent($user, $response['code']));
            return ApiResponse::failure("Your email is not verified!. Verification code has been sent to your email", statusCode: 401);
        }

        if ($this->has('fcm_token')) {
            FcmToken::updateOrCreate(
                ['user_id' => $user->id], 
                ['token' => $this->fcm_token]
            );
        }
        RateLimiter::clear($this->throttleKey());

        $hasExamDetail = $user->latestExamDetail()->exists();

        $access_token = $user->createToken('auth_token')->plainTextToken;
        return ApiResponse::success('Login Successful', [
            'user' => $user->load('subscription.subscriptionPlan'),
            'has_exam_detail' => $hasExamDetail,
            'access_token' => $access_token
        ]);
    }

    public function throttleKey(): string
    {
        return Str::lower($this->input('email')) . '|' . $this->ip();
    }

    public function ensureIsNotRateLimited()
    {
        if (!RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }
}
