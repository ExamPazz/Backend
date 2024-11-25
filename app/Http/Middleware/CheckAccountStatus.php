<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAccountStatus
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && !$user->is_active)
        {
            return ApiResponse::failure('Account is inactive, please contact Administrator', statusCode: 403);
        }
        return $next($request);
    }
}
