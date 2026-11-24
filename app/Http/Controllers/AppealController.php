<?php

namespace App\Http\Controllers;

use App\Models\Appeal;
use App\Models\Application;
use App\Models\AuditEvent;
use App\Models\User;
use App\Services\AppealService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AppealController extends Controller
{
    public function store(Request $request, Application $application, AppealService $service): RedirectResponse
    {
        abort_unless($application->user_id === $request->user()->id && $application->submitted_at, 404);
        $data = $request->validate([
            'type' => ['required', Rule::in(Appeal::TYPES)],
            'stage' => ['required', Rule::in(Appeal::STAGES)],
            'grounds' => ['required', 'string', 'min:10', 'max:3000'],
            'evidence' => ['nullable', 'string', 'max:3000'],
        ]);
        $service->assertCanFile($application, $data['type'], $data['stage']);
        $appeal = $application->appeals()->create([
            'type' => $data['type'],
            'stage' => $data['stage'],
            'grounds' => $data['grounds'],
            'evidence' => $data['evidence'] ?? null,
            'status' => 'PENDIENTE',
        ]);
        AuditEvent::record('IMPUGNACION_PRESENTADA', $appeal, ['type' => $data['type'], 'stage' => $data['stage']]);

        return back()->with('success', 'Impugnación presentada. La comisión la resolverá dentro del plazo.');
    }

    public function resolve(Request $request, Appeal $appeal): RedirectResponse
    {
        abort_unless($this->canResolve($request->user(), $appeal), 404);
        $data = $request->validate(['decision' => ['required', 'string', 'min:5', 'max:3000']]);
        DB::transaction(function () use ($appeal, $data): void {
            $locked = Appeal::lockForUpdate()->findOrFail($appeal->id);
            abort_unless($locked->status === 'PENDIENTE', 409, 'Esta impugnación ya fue resuelta.');
            $locked->update([
                'status' => 'RESUELTA',
                'decision' => $data['decision'],
                'resolved_at' => now(),
                'decided_by' => auth()->id(),
            ]);
            AuditEvent::record('IMPUGNACION_RESUELTA', $locked, ['type' => $locked->type, 'stage' => $locked->stage]);
        });

        return back()->with('success', 'Impugnación resuelta.');
    }

    private function canResolve(User $user, Appeal $appeal): bool
    {
        if ($user->isManager()) {
            return true;
        }
        if ($appeal->type === 'apelacion') {
            return false;
        }

        return $user->role === 'jurado' && $appeal->application->vacancy->jurors()->where('users.id', $user->id)->exists();
    }
}
