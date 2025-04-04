<?php

namespace App\Repository;

use App\Http\Requests\RegistrationRequest;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Referral;
use Illuminate\Support\Facades\DB;

class UserRepository
{
    public function storeUser(RegistrationRequest $request)
    {
        return DB::transaction(function () use ($request) {
            $user = User::query()->create([
                'full_name' => $request->input('full_name'),
                'email' => $request->input('email'),
                'password' => bcrypt($request->input('password')),
                'referred_by' => $request->filled('referral_code')
                ? User::where('referral_code', $request->referral_code)->first()->id
                : null,
            ]);

             // Create a referral record
            if ($user->referred_by) {
                Referral::create([
                    'referrer_id' => $user->referred_by,
                    'referred_id' => $user->id,
                ]);
            }
            UserProfile::query()->updateOrCreate(
                [
                    'user_id' => $user->id
                ],
                [
                    'phone_number' => $request->input('phone_number'),
                    'nationality' => $request->input('nationality'),
                    'region' => $request->input('region'),
                    'city' => $request->input('city'),
                    'age' => $request->input('age')
                ]);

                 // Assigning freemium subscription
            $freemiumPlan = SubscriptionPlan::where('name', 'freemium')->first();

            if ($freemiumPlan) {
                Subscription::create([
                    'user_id' => $user->id,
                    'subscription_plan_id' => $freemiumPlan->id,
                    'expires_at' => now()->addDays(30), // Example: 30-day free period
                ]);
            }
            return $user;
        });
    }

}
