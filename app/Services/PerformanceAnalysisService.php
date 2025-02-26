<?php

namespace App\Services;

use App\Models\MockExam;
use App\Models\UserExamAnswer;
use App\Models\WeakArea;


class PerformanceAnalysisService
{
    public function getUserExamStatistics($user)
    {
        $mockExams = MockExam::where('user_id', $user->id)->get();

        if ($mockExams->isEmpty()) {
            return collect();
        }

        $totalQuestions = 0;
        $totalAnsweredQuestions = 0;
        $totalCorrectAnswers = 0;
        $totalExamScores = 0;
        $totalTimeSpent = 0; // Total time spent in seconds
        $totalExams = $mockExams->count();

        foreach ($mockExams as $mockExam) {
            $userAnswers = UserExamAnswer::where('mock_exam_id', $mockExam->id)
                ->where('user_id', $user->id)
                ->get();

            $mockExamQuestionsCount = $mockExam->mockExamQuestions->count();
            $correctAnswers = $userAnswers->where('is_correct', true)->count();

            // Sum up time spent from the user_answers table
            $examTimeSpent = $userAnswers->sum('time_spent'); // Ensure `time_spent` exists in user_answers

            $totalQuestions += $mockExamQuestionsCount;
            $totalAnsweredQuestions += $userAnswers->count();
            $totalCorrectAnswers += $correctAnswers;
            $totalTimeSpent += $examTimeSpent;

            if ($mockExamQuestionsCount > 0) {
                $examScore = ($correctAnswers / $mockExamQuestionsCount) * 100;
                $totalExamScores += $examScore;
            }
        }

        $normalizedTotalCorrectAnswers = min($totalCorrectAnswers, 400);

        $averageScore = $normalizedTotalCorrectAnswers;
        $skippedQuestions = $totalQuestions - $totalAnsweredQuestions;

        // Calculate average times
        $averageTimePerExam = $totalExams > 0 ? round($totalTimeSpent / $totalExams) : 0;
        $averageTimePerQuestion = $totalAnsweredQuestions > 0 ? round($totalTimeSpent / $totalAnsweredQuestions) : 0;

        return [
            'average_score' => $averageScore,
            'total_questions' => $totalQuestions,
            'answered_questions' => $totalAnsweredQuestions,
            'correct_answers' => $totalCorrectAnswers,
            'skipped_questions' => $skippedQuestions,
           'average_time_per_exam' => gmdate('i', $averageTimePerExam) . 'm ' . gmdate('s', $averageTimePerExam) . 's',
            'average_time_per_question' => gmdate('i', $averageTimePerQuestion) . 'm ' . gmdate('s', $averageTimePerQuestion) . 's',
        ];
    }

    public function getMockExamTopicBreakdown($mockExam)
    {
        $topicBreakdown = $mockExam->mockExamQuestions
            ->groupBy(['question.subject_id', 'question.topic_id'])
            ->map(function ($questionsByTopic, $subjectId) use ($mockExam) {
                return $questionsByTopic->map(function ($questions, $topicId) use ($mockExam) {
                    $correctAnswers = $questions->filter(function ($question) use ($mockExam) {
                        return $mockExam->userAnswers
                            ->where('question_id', $question->question_id)
                            ->where('is_correct', true)
                            ->isNotEmpty();
                    })->count();

                    return [
                        'topic_id' => $topicId,
                        'topic_name' => $questions->first()->question->topic->body ?? 'Unknown Topic',
                        'subject_id' => $questions->first()->question->subject_id,
                        'subject_name' => $questions->first()->question->subject->name ?? 'Unknown Subject',
                        'question_count' => $questions->count(),
                        'score' => $correctAnswers,
                    ];
                })->values();
            });

        return $topicBreakdown;
    }

    public function getUserMockExamsWithScores($user)
    {
        $mockExams = MockExam::with(['mockExamQuestions.question.subject', 'mockExamQuestions.question.topic', 'userAnswers'])
            ->where('user_id', $user->id)
            ->latest()
            ->get();

        $result = $mockExams->map(function ($mockExam) {
            $totalTimeSpent = $mockExam->completed_at->diffInMinutes($mockExam->start_time);

            // Calculate scores by subject (100 points each)
            $subjectScores = $mockExam->mockExamQuestions
                ->groupBy('question.subject_id')
                ->map(function ($questions) use ($mockExam) {
                    $totalSubjectQuestions = $questions->count();
                    $correctSubjectAnswers = $questions->filter(function ($question) use ($mockExam) {
                        return $mockExam->userAnswers
                            ->where('question_id', $question->question_id)
                            ->where('is_correct', true)
                            ->isNotEmpty();
                    })->count();

                    // Update: Only count questions with non-null selected_option as attempted
                    $attemptedSubjectQuestions = $questions->filter(function ($question) use ($mockExam) {
                        return $mockExam->userAnswers
                            ->where('question_id', $question->question_id)
                            ->whereNotNull('selected_option') // Add this condition
                            ->isNotEmpty();
                    })->count();

                    $skippedSubjectQuestions = $totalSubjectQuestions - $attemptedSubjectQuestions;

                    // Calculate score out of 100 for this subject
                    $scorePerQuestion = 100 / $totalSubjectQuestions;
                    $score = $correctSubjectAnswers * $scorePerQuestion;

                    return [
                        'subject_id' => $questions->first()->question->subject_id,
                        'subject_name' => $questions->first()->question->subject->name,
                        'score' => round($score),
                        'correct_answers' => $correctSubjectAnswers,
                        'attempted_questions' => $attemptedSubjectQuestions,
                        'skipped_questions' => $skippedSubjectQuestions,
                        'total_questions' => $totalSubjectQuestions
                    ];
                })->values();

            // Total score is sum of all subject scores (out of 400)
            $totalScore = $subjectScores->sum('score');

            $topicBreakdown = $this->getMockExamTopicBreakdown($mockExam);

            return [
                'mock_exam_id' => $mockExam->id,
                'start_time' => $mockExam->start_time,
                'end_time' => $mockExam->end_time,
                'total_score' => round($totalScore),
                'total_time_spent' => $totalTimeSpent,
                'subject_scores' => $subjectScores,
                'topic_breakdown' => $topicBreakdown,
            ];
        });

        return $result;
    }

    public function getUserMockExamCount($user)
    {
        return MockExam::where('user_id', $user->id)->count();
    }

    public function getUserOverallSubjectAnalysis($user)
{
    $mockExams = MockExam::with(['mockExamQuestions.question.subject', 'userAnswers'])
        ->where('user_id', $user->id)
        ->get();

    if ($mockExams->isEmpty()) {
        return collect();
    }

    $subjectAnalysis = collect();
    $subjectTimeSpent = []; // Track time spent per subject
    $subjectExamsCount = []; // Track number of exams per subject

    foreach ($mockExams as $mockExam) {
        // Sum up time spent from user_answers
        $examTimeSpent = $mockExam->userAnswers->sum('time_spent'); // Get total time spent in this exam

        $subjectData = $mockExam->mockExamQuestions->groupBy('question.subject.id')->map(function ($questions, $subjectId) use ($mockExam, &$subjectTimeSpent, &$subjectExamsCount, $examTimeSpent) {
            $totalSubjectQuestions = $questions->count();
            $userAnswers = $mockExam->userAnswers;

            $correctAnswers = $questions->filter(function ($question) use ($userAnswers) {
                return $userAnswers->where('question_id', $question->question_id)->where('is_correct', true)->isNotEmpty();
            })->count();

            $attemptedQuestions = $questions->filter(function ($question) use ($userAnswers) {
                return $userAnswers->where('question_id', $question->question_id)->isNotEmpty();
            })->count();

            $skippedQuestions = $totalSubjectQuestions - $attemptedQuestions;
            $score = $totalSubjectQuestions > 0 ? ($correctAnswers / $totalSubjectQuestions) * 100 : 0;

            // Ensure array keys are properly initialized
            if (!array_key_exists($subjectId, $subjectTimeSpent)) {
                $subjectTimeSpent[$subjectId] = 0;
                $subjectExamsCount[$subjectId] = 0;
            }

            // Track time spent per subject
            $subjectTimeSpent[$subjectId] += $examTimeSpent;
            $subjectExamsCount[$subjectId]++;

            return [
                'subject_id' => $subjectId,
                'subject_name' => $questions->first()->question->subject->name,
                'score' => $score,
                'correct_answers' => $correctAnswers,
                'attempted_questions' => $attemptedQuestions,
                'skipped_questions' => $skippedQuestions,
            ];
        });

        $subjectAnalysis = $subjectAnalysis->merge($subjectData);
    }

    // Combine and calculate averages for each subject
    $result = $subjectAnalysis->groupBy('subject_id')->map(function ($subjectData, $subjectId) use ($subjectTimeSpent, $subjectExamsCount) {
        $totalCorrectAnswers = $subjectData->sum('correct_answers');
        $totalAttemptedQuestions = $subjectData->sum('attempted_questions');
        $totalSkippedQuestions = $subjectData->sum('skipped_questions');
        $totalQuestions = $totalCorrectAnswers + $totalSkippedQuestions;

        $averageScore = $totalQuestions > 0 ? ($totalCorrectAnswers / $totalQuestions) * 100 : 0;

        // Calculate average time per exam and per question **for each subject**
        $averageTimePerExam = isset($subjectExamsCount[$subjectId]) && $subjectExamsCount[$subjectId] > 0
            ? round($subjectTimeSpent[$subjectId] / $subjectExamsCount[$subjectId])
            : 0;

        $averageTimePerQuestion = isset($totalAttemptedQuestions) && $totalAttemptedQuestions > 0
            ? round($subjectTimeSpent[$subjectId] / $totalAttemptedQuestions)
            : 0;

        return [
            'subject_id' => $subjectId,
            'subject_name' => $subjectData->first()['subject_name'],
            'average_score' => $averageScore,
            'total_correct_answers' => $totalCorrectAnswers,
            'total_attempted_questions' => $totalAttemptedQuestions,
            'total_skipped_questions' => $totalSkippedQuestions,
            'average_time_per_exam' => gmdate('i', $averageTimePerExam) . 'm ' . gmdate('s', $averageTimePerExam) . 's',
            'average_time_per_question' => gmdate('i', $averageTimePerQuestion) . 'm ' . gmdate('s', $averageTimePerQuestion) . 's',
        ];
    })->values();

    return [
        'subjects' => $result
    ];
}



    public function getUserSubjectsPerformance($user)
    {
        $mockExams = MockExam::with(['mockExamQuestions.question.subject', 'userAnswers'])
            ->where('user_id', $user->id)
            ->get();

        $subjectPerformance = $mockExams->flatMap(function ($mockExam) {
            return $mockExam->mockExamQuestions->groupBy('question.subject_id')->map(function ($questions, $subjectId) use ($mockExam) {
                $totalSubjectQuestions = $questions->count();
                $correctAnswers = $questions->filter(function ($question) use ($mockExam) {
                    return $mockExam->userAnswers->where('question_id', $question->question_id)->where('is_correct', true)->isNotEmpty();
                })->count();

                $score = $totalSubjectQuestions > 0 ? ($correctAnswers / $totalSubjectQuestions) * 100 : 0;

                return [
                    'subject_id' => $subjectId,
                    'subject_name' => $questions->first()->question->subject->name ?? 'Unknown Subject',
                    'score' => $score
                ];
            });
        });

        // Calculate average scores for each subject
        $averageScores = $subjectPerformance->groupBy('subject_id')->map(function ($scores, $subjectId) {
            $averageScore = collect($scores)->avg('score');

            return [
                'subject_id' => $subjectId,
                'subject_name' => $scores[0]['subject_name'],
                'average_score' => $averageScore
            ];
        })->values();

        // Find the weakest and strongest subjects
        $weakSubject = $averageScores->sortBy('average_score')->first(); // Lowest score
        $strongSubject = $averageScores->sortByDesc('average_score')->first(); // Highest score

        return [
            'strong_subject' => $strongSubject,
            'weak_subject' => $weakSubject,
        ];
    }

    private function updateUserWeakAreas($user)
    {
        $mockExams = $user->mockExams()->with(['mockExamQuestions.question.topic', 'userAnswers'])->get();

        foreach ($mockExams as $mockExam) {
            $topicBreakdown = $this->getMockExamTopicBreakdown($mockExam);

            foreach ($topicBreakdown as $subjectId => $topics) {
                foreach ($topics as $topic) {
                    $totalQuestions = $topic['question_count'];
                    $correctAnswers = $topic['score'];

                    $accuracy = ($totalQuestions > 0) ? ($correctAnswers / $totalQuestions) * 100 : 0;

                    // Store only if accuracy is below 40%
                    if ($accuracy < 40) {
                        $weakArea = WeakArea::firstOrNew([
                            'user_id' => $user->id,
                            'subject_id' => $topic['subject_id'],
                            'topic_id' => $topic['topic_id'],
                        ]);

                        // Update cumulative data
                        $weakArea->total_questions = ($weakArea->exists) ? $weakArea->total_questions + $totalQuestions : $totalQuestions;
                        $weakArea->correct_answers = ($weakArea->exists) ? $weakArea->correct_answers + $correctAnswers : $correctAnswers;

                        $weakArea->save();
                    }
                }
            }
        }

    }

    public function getUserWeakAreas($user)
    {
        $this->updateUserWeakAreas($user);

        $weakAreas = WeakArea::where('user_id', $user->id)
            ->with(['subject', 'topic'])
            ->get()
            ->groupBy('subject_id') // Group by subject
            ->map(function ($weakTopics, $subjectId) {
                return [
                    'subject_name' => $weakTopics->first()->subject->name, // Get subject name
                    'topics' => $weakTopics->map(function ($weakArea) {
                        return [
                            'topic_name' => $weakArea->topic->body,
                            'total_questions' => $weakArea->total_questions,
                            'correct_answers' => $weakArea->correct_answers,
                            'accuracy' => $weakArea->total_questions > 0
                                ? round(($weakArea->correct_answers / $weakArea->total_questions) * 100, 2)
                                : 0,
                        ];
                    })->values(),
                ];
            })
            ->values(); // Ensure it's returned as an indexed array

        return $weakAreas;}
}
