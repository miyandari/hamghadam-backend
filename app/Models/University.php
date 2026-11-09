<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class University extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'city',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function userRoles(): HasMany
    {
        return $this->hasMany(UserUniversityRole::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'user_university_roles'
        )
            ->withPivot(['role_id', 'is_active'])
            ->withTimestamps();
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }
}
