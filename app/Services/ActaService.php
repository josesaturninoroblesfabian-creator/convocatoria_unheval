<?php

namespace App\Services;

use App\Models\Acta;
use App\Models\User;
use App\Models\Vacancy;

class ActaService
{
    public function generate(Vacancy $vacancy, string $stage, array $content): Acta
    {
        $hash = hash('sha256', json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return $vacancy->actas()->updateOrCreate(
            ['stage' => $stage],
            ['content' => $content, 'content_hash' => $hash, 'status' => 'ABIERTA', 'signed_at' => null],
        );
    }

    public function sign(Acta $acta, User $user): void
    {
        if (! $acta->vacancy->jurors()->where('users.id', $user->id)->exists()) {
            return;
        }
        $acta->signatures()->firstOrCreate(['user_id' => $user->id], ['signed_at' => now()]);
        $required = $acta->vacancy->jurors()->count();
        if ($required > 0 && $acta->signatures()->count() >= $required) {
            $acta->update(['status' => 'FIRMADA', 'signed_at' => now()]);
        }
    }

    /**
     * @return array{required: int, signed: int, status: string, signers: list<array{id: int, name: string, signed: bool}>}
     */
    public function status(Acta $acta): array
    {
        $jurors = $acta->vacancy->jurors()->get(['users.id', 'users.name']);
        $signed = $acta->signatures()->pluck('user_id')->all();

        return [
            'required' => $jurors->count(),
            'signed' => count($signed),
            'status' => $acta->status,
            'signers' => $jurors->map(fn (User $juror): array => [
                'id' => $juror->id,
                'name' => $juror->name,
                'signed' => in_array($juror->id, $signed, true),
            ])->values()->all(),
        ];
    }
}
