<?php

namespace App\Services;

use App\Models\User;

class GoogleAuthService
{
    public function setupAuthentication($request)
    {
        $firebaseUser = app('firebase.auth')->getUser($request['token']);
        $nameParts = explode(' ', $firebaseUser->displayName);
        $firstName = $nameParts[0];
        $lastName = implode(' ', array_slice($nameParts, 1));
        $user = User::where('email', $firebaseUser->email)->first();

        if ($user == null) {
            $user = User::create([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $firebaseUser->email,
            ]);
        }

        return $user;
    }
}