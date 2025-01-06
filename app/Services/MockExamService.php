<?php

namespace App\Services;

use App\Models\MockExam;
use App\Models\MockExamQuestion;
use App\Models\Subject;
use App\Models\UserExamAnswer;
use App\Models\Question;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery\Exception;

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
        $cacheKey = "mock_exam_{$user->id}";
        if ($cachedExam = Cache::get($cacheKey)) {
            return $cachedExam;
        }

        $examDetail = $user->latestExamDetail;

        if (!$examDetail || empty($examDetail->subject_combinations)) {
            throw new \InvalidArgumentException('User must select exactly 4 subjects.');
        }

        $subjects_ids = $examDetail->subject_combinations;

        if (count($subjects_ids) !== 4) {
            throw new \InvalidArgumentException('User must select exactly 4 subjects.');
        }

        $subjects_models = Subject::query()
            ->whereIn('id', $subjects_ids)
            ->get(['id', 'name'])
            ->keyBy('id');

        $questionIds = [];
        $groupedQuestions = [];

        DB::beginTransaction();
        try {
            $mockExam = MockExam::query()->create([
                'user_id' => $user->id,
                'start_time' => now(),
                'end_time' => now()->addMinutes(90), // 1 hour 30 minutes
            ]);

            foreach ($subjects_ids as $subject_id) {
                $selectedQuestionIds = Question::query()
                    ->where('subject_id', $subject_id)
                    ->inRandomOrder()
                    ->limit(50)
                    ->pluck('id')
                    ->toArray();

                if (empty($selectedQuestionIds)) {
                    throw new \RuntimeException("No questions found for subject ID: {$subject_id}");
                }

                $questionIds[$subject_id] = $selectedQuestionIds;

                $mockExamQuestions = array_map(function ($questionId) use ($mockExam, $subject_id) {
                    return [
                        'mock_exam_id' => $mockExam->id,
                        'question_id' => $questionId,
                        'subject_id' => $subject_id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }, $selectedQuestionIds);

                MockExamQuestion::query()->insert($mockExamQuestions);
            }

            foreach ($subjects_ids as $subjectId) {
                $questions = Question::query()
                    ->select(['id', 'year', 'question', 'image_url', 'subject_id', 'topic_id', 'objective_id'])
                    ->with([
                        'questionOptions' => function ($query) {
                            $query->select(['id', 'question_id', 'value']);
                        },
                        'topic:id,body',
                        'objective:id,body'
                    ])
                    ->whereIn('id', $questionIds[$subjectId])
                    ->get();

                //Structure response

                $groupedQuestions[ucwords($subjects_models[$subjectId]->name)] = [
                    'subject' => [
                        'id' => $subjects_models[$subjectId]->id,
                        'name' => ucwords($subjects_models[$subjectId]->name)
                    ],
                    'questions' => $questions->map(function ($question) {
                        return [
                            'id' => $question->id,
                            'year' => $question->year,
                            'question' => $question->question,
                            'image_url' => $question->image_url,
                            'topic' => [
                                'id' => $question->topic->id,
                                'name' => $question->topic->name,
                            ],
                            'objective' => [
                                'id' => $question->objective->id,
                                'name' => $question->objective->name
                            ],
                            'options' => $question->questionOptions->shuffle()->map(function ($option) {
                                return [
                                    'id' => $option->id,
                                    'value' => $option->value
                                ];
                            })->values()->toArray()
                        ];
                    })->toArray()

                ];
            }

            DB::commit();

            Cache::put($cacheKey, $groupedQuestions, now()->addHours(2));

            return $groupedQuestions;

        } catch (Exception $exception) {
            DB::rollBack();
            throw $exception;
        }

    }

    public function clearExamCache($userId): void
    {
        Cache::forget("mock_exam_{$userId}");
    }

    public function handleExamError($userId, \Exception $e)
    {
        $this->clearExamCache($userId);
        Log::error("Mock exam generation failed for user {$userId}: ".$e->getMessage());
        throw $e;
    }

    public function storeExamAnswers($request)
    {
        DB::beginTransaction();
        try {
            $user = $request->user();
            // Get all question IDs from the request
            $questionIds = collect($request->answers)->pluck('question_id')->unique()->toArray();
            $selectedOptionIds = collect($request->answers)->pluck('selected_option')->unique()->toArray();

            // Fetch all questions and their correct options in one query
            $questions = Question::with(['questionOptions' => function($query) use ($selectedOptionIds) {
                $query->whereIn('id', $selectedOptionIds)
                    ->select('id', 'question_id', 'is_correct');
            }])
                ->whereIn('id', $questionIds)
                ->get()
                ->keyBy('id');

            // Validate all options belong to their respective questions
            foreach ($request->answers as $answer) {
                $question = $questions->get($answer['question_id']);
                if (!$question || !$question->questionOptions->contains('id', $answer['selected_option'])) {
                    throw new \Exception("Invalid option selected for question {$answer['question_id']}");
                }
            }

            // Prepare bulk insert data
            $answersToInsert = [];
            $results = [];
            $now = now();

            foreach ($request->answers as $answer) {
                $question = $questions->get($answer['question_id']);
                $selectedOption = $question->questionOptions->firstWhere('id', $answer['selected_option']);

                $answersToInsert[] = [
                    'mock_exam_id' => $request->mock_exam_id,
                    'user_id' => $user->id,
                    'question_id' => $answer['question_id'],
                    'selected_option' => $answer['selected_option'],
                    'is_correct' => $selectedOption->is_correct,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $results[] = [
                    'question_id' => $answer['question_id'],
                    'is_correct' => $selectedOption->is_correct
                ];
            }

            // Delete any existing answers for this exam (in case of resubmission)
            UserExamAnswer::query()->where('mock_exam_id', $request->mock_exam_id)
                ->where('user_id', $user->id)
                ->delete();

            // Bulk insert all answers
            UserExamAnswer::query()->insert($answersToInsert);

            // Calculate score
            $totalQuestions = count($request->answers);
            $correctAnswers = count(array_filter($results, fn($result) => $result['is_correct']));
            $score = ($correctAnswers / $totalQuestions) * 100;

            // Update mock exam score
            MockExam::query()->where('id', $request->mock_exam_id)
                ->where('user_id', $user->id)
                ->whereNull('completed_at')
                ->update([
                    'score' => $score,
                    'completed_at' => $now,
                    'total_questions' => $totalQuestions,
                    'correct_answers' => $correctAnswers
                ]);

            DB::commit();

            return [
                'results' => $results,
                'score' => round($score, 2),
                'total_questions' => $totalQuestions,
                'correct_answers' => $correctAnswers
            ];

        } catch (\Exception $e) {
            DB::rollBack();

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    public function storeUserAnswer($user, $data)
    {
        $question = Question::find($data['question_id']);
        $selectedOption = json_encode($data['selected_option']);
        $isCorrect = $selectedOption === $question->questionOptions->pluck('value')->first();

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
        $score = $totalQuestions > 0 ? ($correctAnswers / $totalQuestions) * 400 : 0;

        return [
            'total_questions' => $totalQuestions,
            'correct_answers' => $correctAnswers,
            'score' => round($score, 2),
        ];
    }

    public function finalizeExam($request, $mockExamId)
    {
        DB::beginTransaction();
        try {
            $user = $request->user();

            // Retrieve the mock exam
            $mockExam = MockExam::where('id', $mockExamId)
                ->where('user_id', $user->id)
                ->firstOrFail();

            // Determine the exam status based on time
            $status = now()->greaterThan($mockExam->end_time) ? 'timer_expired' : 'submitted';

            $answersFromRequest = $request->answers ?? [];
            $answersToInsert = [];
            $now = now();

            if (!empty($answersFromRequest)) {
                // Get all question IDs and selected options from the request
                $questionIds = collect($answersFromRequest)->pluck('question_id')->unique()->toArray();
                $selectedOptionIds = collect($answersFromRequest)->pluck('selected_option')->unique()->toArray();

                // Fetch all questions and their correct options in one query
                $questions = Question::with(['questionOptions' => function ($query) use ($selectedOptionIds) {
                    $query->whereIn('id', $selectedOptionIds)
                        ->select('id', 'question_id', 'is_correct');
                }])
                    ->whereIn('id', $questionIds)
                    ->get()
                    ->keyBy('id');

                // Validate options and prepare answers for insertion
                foreach ($answersFromRequest as $answer) {
                    $question = $questions->get($answer['question_id']);

                    if (!$question || !$question->questionOptions->contains('id', $answer['selected_option'])) {
                        throw new \Exception("Invalid option selected for question {$answer['question_id']}");
                    }

                    $selectedOption = $question->questionOptions->firstWhere('id', $answer['selected_option']);
                    $answersToInsert[] = [
                        'mock_exam_id' => $mockExamId,
                        'user_id' => $user->id,
                        'question_id' => $answer['question_id'],
                        'selected_option' => $answer['selected_option'],
                        'is_correct' => $selectedOption->is_correct,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                // Delete any existing answers for this exam
                UserExamAnswer::where('mock_exam_id', $mockExamId)
                    ->where('user_id', $user->id)
                    ->delete();

                // Bulk insert new answers
                UserExamAnswer::insert($answersToInsert);
            }

            // Fetch user answers to calculate the score
            $userAnswers = UserExamAnswer::where('mock_exam_id', $mockExamId)
                ->where('user_id', $user->id)
                ->get();

            $totalQuestions = $mockExam->mockExamQuestions->count();
            $answeredQuestions = $userAnswers->count();
            $correctAnswers = $userAnswers->where('is_correct', true)->count();
            $score = $totalQuestions > 0 ? ($correctAnswers / $totalQuestions) * 100 : 0;

            // Update mock exam with final results
            $mockExam->update([
                'status' => $status,
                'score' => round($score, 2),
                'completed_at' => $now,
                'total_questions' => $totalQuestions,
                'answered_questions' => $answeredQuestions,
                'correct_answers' => $correctAnswers,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'status' => $status,
                'total_questions' => $totalQuestions,
                'answered_questions' => $answeredQuestions,
                'correct_answers' => $correctAnswers,
                'score' => round($score, 2),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
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
                'options' => $question->questionOptions,
                'image_url' => $question->image_url,
                'correct_option' => $question->questionOptions->pluck('value'),
                'solution' => $question->solution,
                'user_answer' => $userAnswer?->selected_option,
                'is_correct' => $userAnswer?->is_correct,
            ];
        });
    }
}
