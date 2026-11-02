<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'title', 'description', 'modality', 'status', 'is_demo', 'rules_approved', 'resolution', 'opens_at', 'closes_at', 'schedule', 'rubric', 'public_documents', 'results_published_at'])]
class RecruitmentCall extends Model
{
    use HasFactory;

    protected $appends = ['accepting_applications'];

    protected function casts(): array
    {
        return ['opens_at' => 'datetime', 'closes_at' => 'datetime', 'schedule' => 'array', 'rubric' => 'array', 'public_documents' => 'array', 'is_demo' => 'boolean', 'rules_approved' => 'boolean', 'results_published_at' => 'datetime'];
    }

    public function getAcceptingApplicationsAttribute(): bool
    {
        return $this->status === 'PUBLICADA' && now()->startOfSecond()->betweenIncluded($this->opens_at, $this->closes_at);
    }

    public function vacancies(): HasMany
    {
        return $this->hasMany(Vacancy::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }
}
