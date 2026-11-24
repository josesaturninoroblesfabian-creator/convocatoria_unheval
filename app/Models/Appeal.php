<?php

namespace App\Models;

use Database\Factories\AppealFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['application_id', 'type', 'stage', 'grounds', 'evidence', 'status', 'decision', 'decided_by', 'resolved_at'])]
class Appeal extends Model
{
    /** @use HasFactory<AppealFactory> */
    use HasFactory;

    public const TYPES = ['reconsideracion', 'apelacion'];

    public const STAGES = ['aptitude', 'external'];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
