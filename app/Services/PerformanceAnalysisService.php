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
                    'score' => round($score, 2),
                    'correct_answers' => $correctSubjectAnswers,
                    'attempted_questions' => $attemptedSubjectQuestions,
                    'skipped_questions' => $skippedSubjectQuestions,
                ];
            })->values();

            return [
                'mock_exam_id' => $mockExam->id,
                'start_time' => $mockExam->start_time,
                'end_time' => $mockExam->end_time,
                'total_score' => round($totalScore, 2),
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
}
