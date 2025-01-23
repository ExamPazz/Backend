<?php

namespace App\Services;

use App\Models\User;

class GoogleAuthService
{
    public function setupAuthentication($request)
{
    $token = $request->input('token');

    if (empty($token)) {
        throw new \Exception('Authentication token is missing');
    }

    try {
        // Verify ID token using Firebase auth
        $auth = app('firebase.auth');
        $verifiedIdToken = $auth->verifyIdToken($token);

        // Extract UID from token claims
        $uid = $verifiedIdToken->claims()->get('sub');

        if (empty($uid) || strlen($uid) > 128) {
            throw new \Exception('Invalid UID in token');
        }

        // Retrieve user info from Firebase
        $firebaseUser = $auth->getUser($uid);

        $nameParts = explode(' ', $firebaseUser->displayName);
        $firstName = $nameParts[0];
        $lastName = isset($nameParts[1]) ? implode(' ', array_slice($nameParts, 1)) : '';

        // Check or create user in local database
        $user = User::firstOrCreate(
            ['email' => $firebaseUser->email],
            ['first_name' => $firstName, 'last_name' => $lastName]
        );

        return $user;
    } catch (\Throwable $e) {
        throw new \Exception('Firebase authentication failed: ' . $e->getMessage());
    }
}

}