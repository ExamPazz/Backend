<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    use HasFactory;

    protected $guarded = [];


    public function section()
    {
        return $this->belongsTo(Section::class, 'section_id');
    }

    public function chapter()
    {
        return $this->belongsTo(Chapter::class, 'chapter_id');
    }

    public function topic()
    {
        return $this->belongsTo(Topic::class);
    }

    public function objective()
    {
        return $this->belongsTo(Objective::class);
    }
}
