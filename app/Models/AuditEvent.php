<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'action', 'subject_type', 'subject_id', 'metadata'])]
class AuditEvent extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public static function record(string $action, Model $subject, array $metadata = []): void
    {
        static::create(['user_id' => auth()->id(), 'action' => $action, 'subject_type' => $subject->getMorphClass(), 'subject_id' => $subject->getKey(), 'metadata' => $metadata]);
    }
}
