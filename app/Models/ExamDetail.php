<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExamDetail extends Model
{
    use HasFactory;
    
    protected $guarded = [];

    protected $casts = [
        'subject_combinations' => 'array',
        'weak_areas' => 'array',
    ];
}
