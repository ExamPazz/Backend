<?php

namespace App\Services;

use App\Models\MockExam;
use App\Models\UserExamAnswer;

class PerformanceAnalysisService
{
    public function getUserExamStatistics($user)
    {
        $mockExams = MockExam::where('user_id', $user->id)->get();

        if ($mockExams->isEmpty()) {
            throw new \InvalidArgumentException('No mock exams found for the user.');
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
            $examTimeSpent = $mockExam->average_time_per_exam ?? 0; // Time in seconds sent by frontend

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

        $averageTimePerExam = $totalExams > 0 ? round($totalTimeSpent / $totalExams) : 0;
        $averageTimePerQuestion = $totalAnsweredQuestions > 0 ? round($totalTimeSpent / $totalAnsweredQuestions) : 0;

        return [
            'average_score' => $averageScore, // Average score over 400
            'total_questions' => $totalQuestions,
            'answered_questions' => $totalAnsweredQuestions,
            'correct_answers' => $totalCorrectAnswers,
            'skipped_questions' => $skippedQuestions,
            'average_time_per_exam' => $averageTimePerExam,
            'average_time_per_question' => $averageTimePerQuestion, 
        ];
    }


    public function getUserMockExamsWithScores($user)
    {
        $mockExams = MockExam::with(['mockExamQuestions.question.subject', 'userAnswers'])
            ->where('user_id', $user->id)
            ->get();

        $result = $mockExams->map(function ($mockExam) {
            $totalQuestions = $mockExam->mockExamQuestions->count();
            $userAnswers = $mockExam->userAnswers;

            $totalScore = $userAnswers->where('is_correct', true)->count() / ($totalQuestions > 0 ? $totalQuestions : 1) * 100;

            $totalTimeSpent = $mockExam->end_time->diffInMinutes($mockExam->start_time);

            $subjectScores = $mockExam->mockExamQuestions->groupBy('subject_id')->map(function ($questions, $subjectId) use ($userAnswers) {
                $totalSubjectQuestions = $questions->count();
                $correctSubjectAnswers = $questions->filter(function ($question) use ($userAnswers) {
                    return $userAnswers->where('question_id', $question->question_id)->where('is_correct', true)->isNotEmpty();
                })->count();

                $attemptedSubjectQuestions = $questions->filter(function ($question) use ($userAnswers) {
                    return $userAnswers->where('question_id', $question->question_id)->isNotEmpty();
                })->count();

                $skippedSubjectQuestions = $totalSubjectQuestions - $attemptedSubjectQuestions;

                $score = $totalSubjectQuestions > 0 ? ($correctSubjectAnswers / $totalSubjectQuestions) * 100 : 0;

                return [
                    'subject_id' => $subjectId,
                    'subject_name' => $questions->first()->question->subject->name,
                    'score' => $score,
                    'correct_answers' => $correctSubjectAnswers,
                    'attempted_questions' => $attemptedSubjectQuestions,
                    'skipped_questions' => $skippedSubjectQuestions,
                ];
            })->values();

            return [
                'mock_exam_id' => $mockExam->id,
                'start_time' => $mockExam->start_time,
                'end_time' => $mockExam->end_time,
                'total_score' => $totalScore,
                'total_time_spent' => $totalTimeSpent, 
                'subject_scores' => $subjectScores,
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

        foreach ($mockExams as $mockExam) {
            $subjectData = $mockExam->mockExamQuestions->groupBy('subject_id')->map(function ($questions, $subjectId) use ($mockExam) {
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
        $result = $subjectAnalysis->groupBy('subject_id')->map(function ($subjectData) {
                $totalCorrectAnswers = $subjectData->sum('correct_answers');
                $totalAttemptedQuestions = $subjectData->sum('attempted_questions');
                $totalSkippedQuestions = $subjectData->sum('skipped_questions');
                $totalQuestions = $totalCorrectAnswers + $totalSkippedQuestions;

                $averageScore = $totalQuestions > 0 ? ($totalCorrectAnswers / $totalQuestions) * 100 : 0;

            return [
                'subject_id' => $subjectData->first()['subject_id'],
                'subject_name' => $subjectData->first()['subject_name'],
                'average_score' => $averageScore,
                'total_correct_answers' => $totalCorrectAnswers,
                'total_attempted_questions' => $totalAttemptedQuestions,
                'total_skipped_questions' => $totalSkippedQuestions,
            ];
        })->values();

        return $result;
    }
}
