<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'subscription_plan_id',
        'user_id',
        'status',
        'payment_provider_data'
    ];
    protected $casts = [
        'payment_provider_data' => 'json'
    ];


    public function mockExams(): HasMany
    {
        return $this->hasMany(MockExam::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }
}
