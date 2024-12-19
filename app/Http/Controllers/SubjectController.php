<?php

namespace App\Http\Controllers;

use App\Models\Subject;
use App\Support\ApiResponse;

class SubjectController extends Controller
{
    public function index()
    {
        $subjects = Subject::all();

        if ($subjects->isEmpty()) {
            return ApiResponse::failure('No subjects found.');       
        }

        return ApiResponse::success('All subjects retrieved successfully.', [
            'subjects' => $subjects,
        ]);       
    }
}
