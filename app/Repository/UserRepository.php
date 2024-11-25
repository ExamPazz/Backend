<?php

namespace App\Repository;

use App\Http\Requests\RegistrationRequest;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Support\Facades\DB;

class UserRepository
{
    public function storeUser(RegistrationRequest $request)
    {
        return DB::transaction(function () use ($request) {
            $user = User::query()->create([
                'first_name' => $request->input('first_name'),
                'last_name' => $request->input('last_name'),
                'email' => $request->input('email'),
                'password' => bcrypt($request->input('password'))
            ]);

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
            return $user;
        });
    }

}
