<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'scores', 'total', 'notes', 'closed_at'])]
class Evaluation extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['scores' => 'array', 'total' => 'decimal:3', 'closed_at' => 'datetime'];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}
