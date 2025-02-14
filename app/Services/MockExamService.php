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
     * @throws \Exception
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

        $userActiveSubscription = getUserCurrentActiveSubscription($user);

        DB::beginTransaction();
        try {
            $mockExam = MockExam::query()->create([
                'subscription_id' => $userActiveSubscription->id,
                'user_id' => $user->id,
                'start_time' => now(),
                'end_time' => now()->addMinutes(90), // 1 hour 30 minutes
                'question_order' => [] // Initialize empty array
            ]);

            foreach ($subjects_ids as $subject_id) {
                $subject = Subject::findOrFail($subject_id);
                $selectedQuestionIds = Question::query()
                    ->where('subject_id', $subject_id)
                    ->inRandomOrder()
                    ->limit($subject->number_of_questions) // Use subject-specific question count
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

            // Store the order information
            $questionOrder = [];
            foreach ($groupedQuestions as $subjectName => $subjectData) {
                $questionOrder[$subjectName] = [
                    'questions' => collect($subjectData['questions'])->map(function ($q) {
                        return [
                            'id' => $q['id'],
                            'options' => collect($q['options'])->pluck('id')->toArray()
                        ];
                    })->toArray()
                ];
            }

            $mockExam->update(['question_order' => $questionOrder]);

            DB::commit();

            $groupedQuestions = ['questions' => $groupedQuestions, 'mock_exam_id' => $mockExam->id];
            Cache::put($cacheKey, $groupedQuestions, now()->addHours(2));

            return $groupedQuestions;

        } catch (Exception $exception) {
            DB::rollBack();
            $this->handleExamError($user->id, $exception);
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
            $mockExam = MockExam::with([
                'mockExamQuestions.question.subject',
                'mockExamQuestions.question.questionOptions'
            ])
                ->where('id', $request->mock_exam_id)
                ->where('user_id', $user->id)
                ->firstOrFail();

            // Delete existing answers
            UserExamAnswer::where('mock_exam_id', $request->mock_exam_id)
                ->where('user_id', $user->id)
                ->delete();

            $answersToInsert = [];
            $now = now();

            // Process and store new answers
            foreach ($request->answers as $answer) {
                $question = $mockExam->mockExamQuestions
                    ->where('question_id', $answer['question_id'])
                    ->first()
                    ->question;

                $selectedOption = $question->questionOptions
                    ->where('id', $answer['selected_option'])
                    ->first();

                $answersToInsert[] = [
                    'mock_exam_id' => $request->mock_exam_id,
                    'user_id' => $user->id,
                    'question_id' => $answer['question_id'],
                    'selected_option' => $answer['selected_option'] ?? null,
                    'is_correct' => $selectedOption ? $selectedOption->is_correct : false,
                    'time_spent' => $answer['time_spent'] ?? 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            // Insert all answers
            UserExamAnswer::insert($answersToInsert);

            // Refresh mock exam to get the new answers
            $mockExam->refresh();
            $mockExam->load('userAnswers');

            // Calculate scores by subject (100 points each)
            $totalScore = $mockExam->mockExamQuestions
                ->groupBy('question.subject_id')
                ->map(function ($questions) use ($mockExam) {
                    $totalQuestionsInSubject = $questions->count();
                    $correctAnswersInSubject = $mockExam->userAnswers
                        ->where('is_correct', true)
                        ->whereIn('question_id', $questions->pluck('question_id'))
                        ->count();

                    // Each subject is worth 100 points
                    $scorePerQuestion = 100 / $totalQuestionsInSubject;
                    return $correctAnswersInSubject * $scorePerQuestion;
                })
                ->sum(); // Sum up all subject scores for total out of 400

            // Calculate other metrics
            $totalQuestions = $mockExam->mockExamQuestions->count();
            $totalAnswered = count($request->answers);
            $totalCorrect = $mockExam->userAnswers->where('is_correct', true)->count();
            $totalWrong = $totalAnswered - $totalCorrect;

            // Update mock exam with scores
            $mockExam->update([
                'score' => round($totalScore),
                'completed_at' => $now,
                'total_questions' => $totalQuestions,
                'total_answered' => $totalAnswered,
                'total_correct' => $totalCorrect,
                'total_wrong' => $totalWrong
            ]);

            DB::commit();

            // Handle subscription status
            $examsLeft = totalMockExamsLeft($user);
            if ($examsLeft == 0) {
                $userSubscription = getUserCurrentActiveSubscription($user);
                $userSubscription->update(['status' => 'inactive']);
            }

            // Clear cache
            $cacheKey = "mock_exam_{$user->id}";
            if (Cache::has($cacheKey)) {
                Cache::forget($cacheKey);
            }

            return [
                'success' => true,
                'total_score' => round($totalScore),
                'total_questions' => $totalQuestions,
                'total_answered' => $totalAnswered,
                'total_correct' => $totalCorrect,
                'total_wrong' => $totalWrong
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error storing exam answers: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function calculateScore($user, $mockExamId)
    {
        $mockExam = MockExam::with([
            'mockExamQuestions.question.subject',
            'userAnswers'
        ])
            ->where('id', $mockExamId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        // Group questions and answers by subject
        $subjectScores = $mockExam->mockExamQuestions
            ->groupBy('question.subject_id')
            ->map(function ($questions) use ($mockExam) {
                $subjectId = $questions->first()->question->subject_id;
                $totalQuestions = $questions->count();

                // Get correct answers for this subject
                $correctAnswers = $mockExam->userAnswers
                    ->where('is_correct', true)
                    ->whereIn('question_id', $questions->pluck('question_id'))
                    ->count();

                // Calculate score for this subject (out of 100)
                $scorePerQuestion = 100 / $totalQuestions;
                $subjectScore = $correctAnswers * $scorePerQuestion;

                return [
                    'subject_name' => $questions->first()->question->subject->name,
                    'total_questions' => $totalQuestions,
                    'correct_answers' => $correctAnswers,
                    'score' => round($subjectScore) // Rounded to whole number
                ];
            });

        // Calculate total score (out of 400)
        $totalScore = $subjectScores->sum('score');
        $totalQuestions = $mockExam->mockExamQuestions->count();
        $totalCorrect = $mockExam->userAnswers->where('is_correct', true)->count();

        return [
            'total_score' => round($totalScore), // Rounded to whole number
            'total_questions' => $totalQuestions,
            'total_correct' => $totalCorrect,
            'subject_scores' => $subjectScores->values()->toArray()
        ];
    }

    public function finalizeExam($user, $mockExamId)
    {
        DB::beginTransaction();
        try {
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
                $questions = Question::with([
                    'questionOptions' => function ($query) use ($selectedOptionIds) {
                        $query->whereIn('id', $selectedOptionIds)
                            ->select('id', 'question_id', 'is_correct');
                    }
                ])
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
        $mockExam = MockExam::with([
            'mockExamQuestions.question.questionOptions',
            'mockExamQuestions.question.topic',
            'mockExamQuestions.question.objective',
            'mockExamQuestions.question.subject',
            'userAnswers',
        ])
            ->where('id', $mockExamId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $questionOrder = $mockExam->question_order;

        // Group questions by subject and calculate time spent
        $subjectTimeSpent = $mockExam->userAnswers
            ->groupBy('question.subject.id')
            ->map(function ($answers) {
                return $answers->sum('time_spent');
            });

        $groupedQuestions = $mockExam->mockExamQuestions
            ->sortBy('id')
            ->groupBy('question.subject.id');

        return $groupedQuestions->map(function ($questions, $subjectId) use ($mockExam, $questionOrder, $subjectTimeSpent) {
            $subjectName = $questions->first()->question->subject->name;
            $subjectOrder = $questionOrder[ucwords($subjectName)] ?? [];

            // Get original question order for this subject
            $originalQuestionOrder = collect($subjectOrder['questions'] ?? [])->pluck('id');

            return [
                'subject' => [
                    'id' => $subjectId,
                    'name' => $subjectName,
                    'total_time_spent' => $subjectTimeSpent[$subjectId] ?? 0, // Add total time spent for subject
                ],
                'questions' => $questions
                    ->sortBy(function ($mockExamQuestion) use ($originalQuestionOrder) {
                        return $originalQuestionOrder->search($mockExamQuestion->question_id);
                    })
                    ->map(function ($mockExamQuestion) use ($mockExam, $questionOrder, $subjectName) {
                        $question = $mockExamQuestion->question;
                        $userAnswer = $mockExam->userAnswers
                            ->where('question_id', $question->id)
                            ->first();

                        // Find original option order
                        $questionOrderData = collect($questionOrder[ucwords($subjectName)]['questions'] ?? [])
                            ->firstWhere('id', $question->id);
                        $originalOptionOrder = $questionOrderData['options'] ?? [];

                        return [
                            'id' => $question->id,
                            'question' => $question->question,
                            'options' => $question->questionOptions
                                ->whereNotNull('value')
                                ->sortBy(function ($option) use ($originalOptionOrder) {
                                    return array_search($option->id, $originalOptionOrder);
                                })
                                ->map(function ($option) {
                                    return [
                                        'id' => $option->id,
                                        'value' => $option->value,
                                    ];
                                })
                                ->values(),
                            'image_url' => $question->image_url,
                            'correct_option' => $question->questionOptions
                                ->where('is_correct', true)
                                ->pluck('value')
                                ->first(),
                            'solution' => $question->solution,
                            'user_answer' => $userAnswer?->selected_option,
                            'is_correct' => $userAnswer?->is_correct,
                            'time_spent' => $userAnswer?->time_spent ?? 0,
                        ];
                    })->values(),
            ];
        })->values();
    }

}
