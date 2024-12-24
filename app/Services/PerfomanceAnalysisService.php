<?php

namespace App\Services;

use App\Models\MockExam;
use App\Models\UserExamAnswer;

class PerfomanceAnalysisService
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

        foreach ($mockExams as $mockExam) {
            $userAnswers = UserExamAnswer::where('mock_exam_id', $mockExam->id)
                ->where('user_id', $user->id)
                ->get();

            $mockExamQuestionsCount = $mockExam->mockExamQuestions->count();
            $correctAnswers = $userAnswers->where('is_correct', true)->count();

            $totalQuestions += $mockExamQuestionsCount;
            $totalAnsweredQuestions += $userAnswers->count();
            $totalCorrectAnswers += $correctAnswers;

            if ($mockExamQuestionsCount > 0) {
                $examScore = ($correctAnswers / $mockExamQuestionsCount) * 400;
                $totalExamScores += $examScore;
            }
        }

        $averageScore = $mockExams->count() > 0 
            ? $totalExamScores / $mockExams->count() 
            : 0;

        $skippedQuestions = $totalQuestions - $totalAnsweredQuestions;

        return [
            'average_score' => round($averageScore, 2),
            'total_questions' => $totalQuestions,
            'answered_questions' => $totalAnsweredQuestions,
            'correct_answers' => $totalCorrectAnswers,
            'skipped_questions' => $skippedQuestions,
        ];
    }

}