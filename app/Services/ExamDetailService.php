<?php

namespace App\Services;

use App\Models\ExamDetail;

class ExamDetailService
{
    public function store(array $data)
    {
        $data['user_id'] = auth()->id(); 
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