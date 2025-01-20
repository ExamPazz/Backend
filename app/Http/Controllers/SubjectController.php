<?php

namespace App\Http\Controllers;

use App\Models\Subject;
use App\Support\ApiResponse;

class SubjectController extends Controller
{
    public function index()
    {
        $englishSubject = Subject::where('name', 'English')->first();

        if ($englishSubject && !$englishSubject->is_default) {
            $englishSubject->update(['is_default' => true]);
        }

        $subjects = Subject::orderByDesc('is_default')->get();

        if ($subjects->isEmpty()) {
            return ApiResponse::failure('No subjects found.');       
        }

        return ApiResponse::success('All subjects retrieved successfully.', [
            'subjects' => $subjects,
        ]);       
    }

}
