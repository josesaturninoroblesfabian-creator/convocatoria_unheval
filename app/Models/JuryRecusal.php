<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['vacancy_id', 'juror_id', 'reason', 'status', 'decided_by', 'decision', 'decided_at'])]
class JuryRecusal extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }

    public function vacancy(): BelongsTo
    {
        return $this->belongsTo(Vacancy::class);
    }

    public function juror(): BelongsTo
    {
        return $this->belongsTo(User::class, 'juror_id');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
