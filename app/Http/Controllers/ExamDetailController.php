<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExamDetailRequest;
use App\Http\Requests\UpdateExamDetailRequest;
use App\Models\ExamDetail;
use App\Services\ExamDetailService;
use Illuminate\Support\Facades\Auth;
use App\Support\ApiResponse;


class ExamDetailController extends Controller
{
    protected $examDetailService;

    public function __construct(ExamDetailService $examDetailService)
    {
        $this->examDetailService = $examDetailService;
    }

    public function store(StoreExamDetailRequest $request)
    {
        $examDetail = $this->examDetailService->store($request->validated());
        $examDetail->user_id = auth()->id();
        $examDetail->save();
        return ApiResponse::success('Exam details saved successfully', [
            'data' => $examDetail
         ]);
    }

    public function show()
    {
        $examDetail = ExamDetail::where('user_id', Auth::id())->first();

        if (! $examDetail) {
            return ApiResponse::success('No exam data found.', ['data' => []]);
        }

        return ApiResponse::success('Data fetched successfully', [
            'data' => $examDetail
        ]);
    }


    public function update(UpdateExamDetailRequest $request)
    {
        $examDetail = ExamDetail::where('user_id', Auth::id())->first();
        if (! $examDetail){
            return ApiResponse::failure('Exam data not found');
        }
        $validatedData = $request->validated();

        $updatedDetail = $this->examDetailService->update($examDetail, $validatedData);
            return ApiResponse::success('Exam details updated successfully', [
            'data' => $updatedDetail
        ]);
    }

    public function destroy($id)
    {
        $examDetail = ExamDetail::where('id', $id)->where('user_id', Auth::id())->first();
        if (! $examDetail) {
            return ApiResponse::failure('Exam data not found');
        }

        $examDetail->delete();
        return ApiResponse::success('Exam details deleted successfully');
    }
}