<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\PerformanceAnalysisService;
use App\Support\ApiResponse;

class PerformanceAnalysisController extends Controller

{
    protected $performanceAnalysisService;

    public function __construct(PerformanceAnalysisService $performanceAnalysisService)
    {
        $this->performanceAnalysisService = $performanceAnalysisService;
    }

    public function getUserExamAnalysis(Request $request): JsonResponse
    {
        $user = $request->user();

        $result = $this->performanceAnalysisService->getUserExamStatistics($user);

        return ApiResponse::success('User exam analysis retrieved successfully', $result);
    }

    public function getUserMockExams(Request $request)
    {
        $user = $request->user(); 

        $result = $this->performanceAnalysisService->getUserMockExamsWithScores($user);

        return ApiResponse::success('User exam analysis retrieved successfully', $result);
    }

    public function getUserMockExamsCount(Request $request)
    {
        $user = $request->user(); 

        $result = $this->performanceAnalysisService->getUserMockExamCount($user);

        return ApiResponse::success('User exam count successfully', $result);
    }

    public function getOverallSubjectAnalysis(Request $request)
    {
        $user = $request->user(); 

        $result = $this->performanceAnalysisService->getUserOverallSubjectAnalysis($user);

        return ApiResponse::success('User subjects analysis generated successfully', $result);
    }

    public function getUserSubjectsPerformance(Request $request)
    {
        $user = $request->user(); 

        $result = $this->performanceAnalysisService->getUserSubjectsPerformance($user);

        return ApiResponse::success('User weak and strong subjects generated successfully', $result);
    }

}
