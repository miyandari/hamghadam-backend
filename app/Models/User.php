<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_super_admin',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function student(): HasOne
    {
        return $this->hasOne(Student::class);
    }

    public function universityRoles(): HasMany
    {
        return $this->hasMany(UserUniversityRole::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    public function ledGroups(): HasMany
    {
        return $this->hasMany(RegistrationGroup::class, 'leader_user_id');
    }

    public function createdEvents(): HasMany
    {
        return $this->hasMany(Event::class, 'created_by');
    }

    public function feedbacks(): HasMany
    {
        return $this->hasMany(Feedback::class);
    }

    public function studentCulturalRecords(): HasMany
    {
        return $this->hasMany(StudentCulturalRecord::class);
    }

    public function lotteryFactorAwards(): HasMany
    {
        return $this->hasMany(EventLotteryFactorAward::class);
    }

    public function universities(): BelongsToMany
    {
        return $this->belongsToMany(
            University::class,
            'user_university_roles'
        )
            ->withPivot(['role_id', 'is_active'])
            ->withTimestamps();
    }
}
