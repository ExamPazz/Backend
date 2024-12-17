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

    public function show($id)
    {
        $examDetail = ExamDetail::where('id', $id)->where('user_id', Auth::id())->first();
        if (! $examDetail){
         return ApiResponse::failure('Exam data not found');
        }
        return ApiResponse::success('Data fetched Successfully', [
            'data' => $examDetail
         ]);
    }

    public function update(UpdateExamDetailRequest $request, $id)
    {
        $examDetail = ExamDetail::where('id', $id)->where('user_id', Auth::id())->first();
        if (! $examDetail){
            return ApiResponse::failure('Exam data not found');
        }
        $updatedDetail = $this->examDetailService->update($examDetail, $request->validated());
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
