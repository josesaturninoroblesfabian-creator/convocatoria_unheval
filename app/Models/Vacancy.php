<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['faculty', 'school', 'airhsp', 'cap', 'category', 'dedication', 'weekly_hours', 'courses', 'requirements', 'screening_rules', 'description', 'professional_profile', 'schedule', 'attached_documents'])]
class Vacancy extends Model
{
    use HasFactory;

    public const CONTRACT_HOURS = ['DC A1' => 32, 'DC A2' => 16, 'DC A3' => 8, 'DC B1' => 32, 'DC B2' => 16, 'DC B3' => 8];

    protected $hidden = ['attached_documents'];

    protected $appends = ['public_documents', 'contract_hours'];

    protected function casts(): array
    {
        return ['courses' => 'array', 'requirements' => 'array', 'screening_rules' => 'array', 'schedule' => 'array', 'attached_documents' => 'array'];
    }

    public function getPublicDocumentsAttribute(): array
    {
        return array_map(fn (array $document): array => [
            'id' => $document['id'],
            'name' => $document['name'],
            'path' => route('vacancies.documents.show', ['vacancy' => $this, 'document' => $document['id']], false),
        ], $this->attached_documents ?? []);
    }

    public function getContractHoursAttribute(): ?array
    {
        $hours = self::CONTRACT_HOURS[$this->category] ?? null;

        return $hours ? ['weekly' => $hours, 'teaching' => intdiv($hours, 2), 'other' => intdiv($hours, 2)] : null;
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
