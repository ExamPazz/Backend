<?php

namespace App\Http\Controllers;

use App\Support\ApiResponse;
use App\Models\UtmeDate;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class UtmeDateController extends Controller 
{
    public function store(Request $request)
    {
        $request->validate([
            'exam_date' => 'required|date'
        ]);

        $user = $request->user();

        // Check if user already has a UTME date
        $utmeDate = UtmeDate::updateOrCreate(
            ['user_id' => $user->id],
            ['exam_date' => $request->exam_date]
        );

        return ApiResponse::success('UTME exam date saved successfully', $utmeDate);

    }

    public function show(Request $request)
    {
        $user = $request->user();

        $utmeDate = UtmeDate::where('user_id', $user->id)->first();

        if (! $utmeDate) {
            return ApiResponse::success('UTME exam date not found', ['data' => []]);
        }

        return ApiResponse::success('UTME exam date saved successfully', $utmeDate);
    }
}
