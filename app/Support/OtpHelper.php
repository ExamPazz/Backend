<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\Otp;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Random\RandomException;

class OtpHelper
{
    public function generateOtp(User|Model $user, int $expires_at = 20, bool $generate_token = false): array
    {
        $otp = random_int(0, 999999);
        $code = str_pad($otp, 6, '0', STR_PAD_LEFT);

        $model = Otp::query()->create([
            'code' => $code,
            'user_id' => $user->id,
            'is_used' => false,
            'verification_token' => $generate_token ? Str::random(24) : '',
            'expires_at' => Carbon::now()->addMinutes($expires_at)
        ]);

        return [
            'code' => $code,
            'verification_token' => $model->verification_token
        ];
    }


    public function verifyEmailOtp(string $otp, string $email, $reason = 'verify'): array
    {
        $user = User::query()->where('email', $email)->select('id', 'email')->first();

        if (!$user) {
            return ['status' => false, 'reason' => 'User not found', 'code' => 404];
        }
        if ($reason === 'verify' && $user->hasVerifiedEmail()) {
            return ['status' => false, 'reason' => 'Email already verified', 'code' => 400];
        }

        $otpRecord = $user->otp()->where('is_used', false)
            ->where('expires_at', '>', Carbon::now())->latest('id')->first();

        if (!$otpRecord || $otpRecord->code != $otp) {
            return ['status' => false, 'reason' => 'OTP is invalid', 'code' => 400];
        }
        if ($reason === 'verify') {

            $user->markEmailAsVerified();
        }

        if ($reason === 'password')
        {
            $otpRecord->update([
                'verification_token' => Str::random(32)
            ]);
        }

        $otpRecord->update(['is_used' => true]);

        return [
            'status' => true,
            'verification_token' => $otpRecord->verification_token
        ];
    }

}
