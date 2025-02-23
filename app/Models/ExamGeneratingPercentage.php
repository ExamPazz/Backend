<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamGeneratingPercentage extends Model
{
    use HasFactory;

    protected $fillable = [
        'subject_id',
        'section_id',
        'section_code',
        'objective_id',
        'objective_code',
        'year',
        'percentage_value'
    ];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function objective(): BelongsTo
    {
        return $this->belongsTo(Objective::class);
    }
}
