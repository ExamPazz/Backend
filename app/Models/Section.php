<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Section extends Model
{
    use HasFactory;

    protected $guarded = [];

//    protected $primaryKey = 'id';
//    public $incrementing = false;
//    protected $keyType = 'string';

    public function questions()
    {
        return $this->hasMany(Question::class);
    }
}
