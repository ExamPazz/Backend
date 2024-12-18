<?php

namespace App\Services;

use App\Models\ExamDetail;

class ExamDetailService
{
    public function store(array $data)
    {
        $data['user_id'] = auth()->id(); 
        if (!is_array($data['subject_combinations'])) {
            throw new \InvalidArgumentException('Subject combinations must be an array of subject IDs.');
        }
        return ExamDetail::create($data);
    }

    public function update(ExamDetail $examDetail, array $data)
    {
        $examDetail->update($data);
        return $examDetail;
    }

    public function delete(ExamDetail $examDetail)
    {
        $examDetail->delete();
    }
}