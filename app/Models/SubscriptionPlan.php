<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'uuid',
        'name',
        'price',
        'allowed_subjects_ids',
        'allowed_number_of_questions',
        'allowed_number_of_attempts'
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }


    protected static function boot()
    {
        parent::boot();
        self::creating(function ($model) {
            $model->uuid = Str::orderedUuid();
        });
    }
}
