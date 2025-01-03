<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserAnswerRequest;
use Illuminate\Http\Request;
use App\Services\MockExamService;
use App\Support\ApiResponse;

class MockExamController extends Controller
{
    protected $mockExamService;

    public function __construct(MockExamService $mockExamService)
    {
        $this->mockExamService = $mockExamService;
    }

    public function generateMockExam(Request $request)
    {
        $user = $request->user();

        $mockExamData = $this->mockExamService->generateMockExam($user);

        return ApiResponse::success('Exam generated successfully', [
            'exam_questions_data' => $mockExamData,
        ]);
    }

    public function storeUserAnswer(StoreUserAnswerRequest $request)
    {
        $validated = $request->all();
        $user = $request->user();

        $result = $this->mockExamService->storeUserAnswer($user, $validated);

        return ApiResponse::success('Answer saved successfully', $result);
    }

    public function calculateScore(Request $request, $mockExamId)
    {
        $user = $request->user();

        $result = $this->mockExamService->calculateScore($user, $mockExamId);

        return ApiResponse::success('Answers calculated successfully', $result);
    }

    public function finalizeExam(Request $request, $mockExamId)
    {
        $user = $request->user();

        $result = $this->mockExamService->finalizeExam($user, $mockExamId);

        return ApiResponse::success('Answers saved and finalized successfully', $result);
    }

    public function getMockExamDetails(Request $request, $mockExamId)
    {
        $user = $request->user();
        $examDetails = $this->mockExamService->getMockExamDetails($user, $mockExamId);

        return ApiResponse::success('Mock exam details retrieved successfully', [
            'mock_exam_id' => $mockExamId,
            'questions' => $examDetails,
        ]);
    }

}
