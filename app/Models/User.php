<?php

namespace App\Models;

 use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
 use Illuminate\Database\Eloquent\Relations\HasMany;
 use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable
{
    use HasApiTokens, SoftDeletes, HasFactory, Notifiable, \Illuminate\Auth\MustVerifyEmail;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'full_name',
        'email',
        'password',
        'uuid',
        'agreed_to_terms_of_use',
        'is_active',
        'google_id',
        'referral_code'
    ];

    protected $with = [
      'userProfile'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function userProfile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    public function otp(): HasOne
    {
        return $this->hasOne(Otp::class);
    }

    public function subscription()
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    protected static function boot()
    {
        parent::boot();
        self::creating(function ($model) {
            $model->uuid = Str::orderedUuid();
        });
    }

    public function examDetails(): HasMany
    {
        return $this->hasMany(ExamDetail::class, 'user_id');
    }

    // Retrieve the latest subject combination
    public function latestSubjectCombination()
    {
        return $this->hasOne(ExamDetail::class, 'user_id')->latestOfMany();
    }

    public function latestExamDetail()
    {
        return $this->hasOne(ExamDetail::class)->latestOfMany();
    }

    public function hasMockExam(): bool
    {
        return $this->mockExams()->exists();
    }

    public function mockExams()
    {
        return $this->hasMany(MockExam::class);
    }

    protected static function booted()
    {
        static::created(function ($user) {
            $user->referral_code = strtoupper(Str::random(6));
            $user->save();
        });
    }

    public function ensureReferralCode()
    {
        if (!$this->referral_code) {
            $this->referral_code = User::generateReferralCode();
            $this->save();
        }
    }

    public static function generateReferralCode($length = 6)
    {
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        return substr(str_shuffle(str_repeat($characters, ceil($length / strlen($characters)))), 0, $length);
    }
}
