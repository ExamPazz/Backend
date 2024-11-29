<?php

namespace App\Http\Controllers;

use App\Http\Requests\GoogleAuthFormRequest;
use App\Services\GoogleAuthService;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use App\Support\ApiResponse;


class GoogleAuthController extends Controller
{
    public function store(GoogleAuthFormRequest $request)
    {
        $token = null;

        try {
            $user = (new GoogleAuthService())->setUpAuthentication($request);
            $token = JWTAuth::fromUser($user);

            return ApiResponse::success('Authentication successful', ['token' => $token]);

        } catch (\Exception $e) {
            return ApiResponse::failure($e->getMessage(), [], 409);
        }
    }
}
