<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\PerfomanceAnalysisService;
use App\Support\ApiResponse;

class PerfomanceAnalysisController extends Controller

{
    protected $perfomanceAnalysisService;

    public function __construct(PerfomanceAnalysisService $perfomanceAnalysisService)
    {
        $this->perfomanceAnalysisService = $perfomanceAnalysisService;
    }

    public function getUserExamAnalysis(Request $request)
    {
        $user = $request->user();

        $result = $this->perfomanceAnalysisService->getUserExamStatistics($user);

        return ApiResponse::success('User exam analysis retrieved successfully', $result);
    }
}
