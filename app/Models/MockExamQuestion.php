<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MockExamQuestion extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function mockExam()
    {
        return $this->belongsTo(MockExam::class);
    }

    public function question()
    {
        return $this->belongsTo(Question::class);
    }

}
