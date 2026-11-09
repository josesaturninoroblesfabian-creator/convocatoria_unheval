<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['reference', 'statement', 'status', 'applicant_snapshot', 'consented_at', 'submitted_at', 'receipt_hash', 'review_notes', 'form_data', 'screening_snapshot', 'declared_score', 'external_evaluation', 'evaluation_outcome'])]
class Application extends Model
{
    use HasFactory;

    protected $hidden = ['external_evaluation', 'evaluation_outcome'];

    protected function casts(): array
    {
        return ['applicant_snapshot' => 'array', 'consented_at' => 'datetime', 'submitted_at' => 'datetime', 'form_data' => 'array', 'screening_snapshot' => 'array', 'declared_score' => 'decimal:3', 'external_evaluation' => 'array', 'evaluation_outcome' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function recruitmentCall(): BelongsTo
    {
        return $this->belongsTo(RecruitmentCall::class);
    }

    public function vacancy(): BelongsTo
    {
        return $this->belongsTo(Vacancy::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ApplicationDocument::class);
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class);
    }
}
