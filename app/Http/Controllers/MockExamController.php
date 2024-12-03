<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Question;
use App\Models\MockExam;
use App\Models\MockExamQuestion;

class MockExamController extends Controller
{
    public function generateMockExam(Request $request)
    {
        $user = $request->user();

        $distribution = [
            'i' => 15,
            'ii' => 15,
            'iii' => 10,
        ];

        $selectedQuestions = collect();

        foreach ($distribution as $sectionId => $count) {
            $questions = Question::where('section_id', $sectionId)
                ->inRandomOrder()
                ->limit($count)
                ->get();

            $selectedQuestions = $selectedQuestions->merge($questions);
        }

        $remaining = 40 - $selectedQuestions->count();
        if ($remaining > 0) {
            $extraQuestions = Question::whereNotIn('id', $selectedQuestions->pluck('id'))
                ->inRandomOrder()
                ->limit($remaining)
                ->get();

            $selectedQuestions = $selectedQuestions->merge($extraQuestions);
        }

        $mockExam = MockExam::create([
            'user_id' => $user->id,
        ]);

        foreach ($selectedQuestions as $question) {
            MockExamQuestion::create([
                'mock_exam_id' => $mockExam->id,
                'question_id' => $question->id,
            ]);
        }

        return response()->json([
            'mock_exam_id' => $mockExam->id,
            'questions' => $selectedQuestions->map(function ($question) {
                return [
                    'id' => $question->id,
                    'question' => $question->question,
                    'options' => [
                        'a' => $question->option_a,
                        'b' => $question->option_b,
                        'c' => $question->option_c,
                        'd' => $question->option_d,
                    ],
                    'image_url' => $question->image_url,
                ];
            }),
        ]);
    }
}
