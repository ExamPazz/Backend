<?php

namespace App\Http\Controllers;

use App\Http\Requests\GoogleAuthFormRequest;
use App\Services\GoogleAuthService;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;


class GoogleAuthController extends Controller
{
    public function store(GoogleAuthFormRequest $request)
    {
        $token = null;

        try {
            $user = (new GoogleAuthService())->setUpAuthentication($request);
            $token = JWTAuth::fromUser($user);
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => $e->getMessage(),
            ], 409);
        }

        return ($token) ? $this->respondWithToken($token) : $this->errorResponse('Unauthenticated token', 409);
    }
}
