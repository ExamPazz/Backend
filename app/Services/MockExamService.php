<?php

namespace App\Services;

use App\Models\MockExam;
use App\Models\MockExamQuestion;
use App\Models\Subject;
use App\Models\UserExamAnswer;
use App\Models\Question;
use Carbon\Carbon;

class MockExamService
{
    /**
     * Generate a mock exam for the user.
     *
     * @param  \App\Models\User  $user
     * @return array
     */
    public function generateMockExam($user)
    {
        $examDetail = $user->latestExamDetail;

        if (!$examDetail || empty($examDetail->subject_combinations)) {
            throw new \InvalidArgumentException('User must select exactly 4 subjects.');
        }

        $subjects_ids = $examDetail->subject_combinations;

        $subjects_models = Subject::query()
            ->whereIn('id', $subjects_ids)->get(['id', 'name']);


//        $subjects = collect($subjects_ids);


        if (count($subjects_ids) !== 4) {
            throw new \InvalidArgumentException('User must select exactly 4 subjects.');
        }

        $selectedQuestions = [];
        // Fetch 40 questions for each subject
        foreach ($subjects_ids as $subjectId) {
            $questions = Question::query()
                ->select(['id', 'year', 'question', 'image_url', 'subject_id', 'topic_id', 'objective_id'])
                ->with(['questionOptions' => function ($query) {
                    $query->select(['id', 'question_id', 'value'])->inRandomOrder();
                }, 'subject', 'topic', 'objective'])
                ->where('subject_id', $subjectId)
                ->inRandomOrder()
                ->limit(40)
                ->get();

//            $questions->each(function ($question) use ($subjectId) {
//                $question->subject_id = $subjectId;
//            });

            $selectedQuestions[] = $questions;
        }

//        return $selectedQuestions;

        $mockExam = MockExam::create([
            'user_id' => $user->id,
            'start_time' => now(),
            'end_time' => Carbon::now()->addMinutes(90), // 1 hour 30 minutes
        ]);

        // Attach questions to the mock exam
        foreach ($selectedQuestions as $question) {
            MockExamQuestion::create([
                'mock_exam_id' => $mockExam->id,
                'question_id' => $question->toArray()[0]['id'],
                'subject_id' => $question->toArray()[0]['subject_id'],
            ]);
        }

        // Return the generated exam details
        return [
          'subjects' => $subjects_models,
          'questions' => $selectedQuestions
        ];

//        return [
//            'subjects' => $subjects_models,
//            'mock_exam_id' => $mockExam->id,
//            'questions' => $selectedQuestions->map(function ($question) {
//                return [
//                    'id' => $question->id,
//                    'question' => $question->question,
//                    'options' => $question->questionOptions()->inRandomOrder()->get(['id', 'value', 'question_id']),
//
////                        ->mapWithKeys(function ($option) {
////                        return [$option->value => $option->is_correct];
////                    }),
//                    'image_url' => $question->image_url,
//                    'subject_id' => $question->subject_id
//                ];
//            }),
//        ];
    }


    public function storeUserAnswer($user, $data)
    {
        $question = Question::find($data['question_id']);
        $isCorrect = $data['selected_option'] === $question->correct_option;

        UserExamAnswer::updateOrCreate(
            [
                'mock_exam_id' => $data['mock_exam_id'],
                'user_id' => $user->id,
                'question_id' => $data['question_id'],
            ],
            [
                'selected_option' => $data['selected_option'],
                'is_correct' => $isCorrect,
            ]
        );

        return ['is_correct' => $isCorrect];
    }

    public function calculateScore($user, $mockExamId)
    {
        $userAnswers = UserExamAnswer::where('mock_exam_id', $mockExamId)
            ->where('user_id', $user->id)
            ->get();

        $totalQuestions = $userAnswers->count();
        $correctAnswers = $userAnswers->where('is_correct', true)->count();
        $score = $totalQuestions > 0 ? ($correctAnswers / $totalQuestions) * 100 : 0;

        return [
            'total_questions' => $totalQuestions,
            'correct_answers' => $correctAnswers,
            'score' => round($score, 2),
        ];
    }

    public function finalizeExam($user, $mockExamId)
    {
        $mockExam = MockExam::where('id', $mockExamId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $status = now()->greaterThan($mockExam->end_time) ? 'timer_expired' : 'submitted';

        $userAnswers = UserExamAnswer::where('mock_exam_id', $mockExamId)
            ->where('user_id', $user->id)
            ->get();

        $totalQuestions = $mockExam->mockExamQuestions->count();
        $answeredQuestions = $userAnswers->count();
        $correctAnswers = $userAnswers->where('is_correct', true)->count();

        $score = $totalQuestions > 0 ? ($correctAnswers / $totalQuestions) * 100 : 0;

        return [
            'status' => $status,
            'total_questions' => $totalQuestions,
            'answered_questions' => $answeredQuestions,
            'correct_answers' => $correctAnswers,
            'score' => round($score, 2),
        ];
    }

    public function getMockExamDetails($user, $mockExamId)
    {
        $mockExam = MockExam::with(['mockExamQuestions.question', 'userAnswers'])
            ->where('id', $mockExamId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        return $mockExam->mockExamQuestions->map(function ($mockExamQuestion) use ($mockExam) {
            $question = $mockExamQuestion->question;
            $userAnswer = $mockExam->userAnswers
                ->where('question_id', $question->id)
                ->first();

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
                'correct_option' => $question->correct_option,
                'solution' => $question->solution,
                'user_answer' => $userAnswer?->selected_option,
                'is_correct' => $userAnswer?->is_correct,
            ];
        });
    }
}
