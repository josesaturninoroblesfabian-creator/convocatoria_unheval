<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['faculty', 'school', 'airhsp', 'cap', 'category', 'dedication', 'weekly_hours', 'courses', 'requirements', 'screening_rules'])]
class Vacancy extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['courses' => 'array', 'requirements' => 'array', 'screening_rules' => 'array'];
    }

    public function recruitmentCall(): BelongsTo
    {
        return $this->belongsTo(RecruitmentCall::class);
    }

    public function jurors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'jury_assignments');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }
}
