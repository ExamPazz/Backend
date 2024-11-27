<?php

namespace App\Firebase;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable as UserContract;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Hashing\Hasher as HasherContract;

class FirebaseUserProvider implements UserProvider
{
    protected $hasher;

    protected $model;

    protected $auth;

    public function __construct(HasherContract $hasher, $model)
    {
        $this->model = $model;
        $this->hasher = $hasher;
        $this->auth = app('firebase.auth');
    }

    public function retrieveById($identifier)
    {
        $user = $this->auth->getUser($identifier);

        $existingUser = User::where('firebase_uid', $user->uid)->first();

        if ($existingUser == null) {
            $user = User::create([
                'firebase_uid' => $user->uid,
                'name' => $user->displayName,
                'email' => $user->email,
                'image' => $user->photoUrl,
                'phone' => $user->phoneNumber,
            ]);
        }

        return $user;
    }

    public function retrieveByToken($identifier, $token)
    {

    }

    public function updateRememberToken(UserContract $user, $token)
    {

    }

    public function retrieveByCredentials(array $credentials)
    {

    }

    public function validateCredentials(UserContract $user, array $credentials)
    {

    }
}
