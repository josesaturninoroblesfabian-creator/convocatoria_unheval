<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['vacancy_id', 'stage', 'content', 'content_hash', 'status', 'signed_at'])]
class Acta extends Model
{
    use HasFactory;

    public const STAGES = ['aptitude', 'external', 'internal', 'final'];

    protected function casts(): array
    {
        return ['content' => 'array', 'signed_at' => 'datetime'];
    }

    public function vacancy(): BelongsTo
    {
        return $this->belongsTo(Vacancy::class);
    }

    public function signatures(): HasMany
    {
        return $this->hasMany(ActaSignature::class);
    }

    public function signers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'acta_signatures')->withPivot('signed_at');
    }
}
