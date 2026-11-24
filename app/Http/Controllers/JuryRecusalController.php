<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\JuryRecusal;
use App\Models\Vacancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class JuryRecusalController extends Controller
{
    public function store(Request $request, Vacancy $vacancy): RedirectResponse
    {
        abort_unless($vacancy->jurors()->where('users.id', $request->user()->id)->exists(), 404);
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:2000']]);
        $recusal = $vacancy->recusals()->firstOrCreate(
            ['juror_id' => $request->user()->id],
            ['reason' => $data['reason'], 'status' => 'SOLICITADA'],
        );
        if (! $recusal->wasRecentlyCreated) {
            throw ValidationException::withMessages(['recusal' => 'Ya presentaste una abstención para esta plaza.']);
        }
        AuditEvent::record('ABSTENCION_SOLICITADA', $recusal, ['vacancy_id' => $vacancy->id]);

        return back()->with('success', 'Abstención solicitada. La comisión la resolverá.');
    }

    public function resolve(Request $request, JuryRecusal $recusal): RedirectResponse
    {
        abort_unless($request->user()->isManager(), 404);
        $data = $request->validate([
            'decision' => ['required', 'string', 'min:5', 'max:2000'],
            'approve' => ['required', 'boolean'],
        ]);
        DB::transaction(function () use ($recusal, $data): void {
            $locked = JuryRecusal::lockForUpdate()->findOrFail($recusal->id);
            abort_unless($locked->status === 'SOLICITADA', 409, 'Esta abstención ya fue resuelta.');
            $locked->update([
                'status' => $data['approve'] ? 'APROBADA' : 'RECHAZADA',
                'decision' => $data['decision'],
                'decided_by' => auth()->id(),
                'decided_at' => now(),
            ]);
            if ($data['approve']) {
                $this->replaceJuror($locked);
            }
            AuditEvent::record('ABSTENCION_RESUELTA', $locked, ['approve' => (bool) $data['approve']]);
        });

        return back()->with('success', 'Abstención resuelta.');
    }

    private function replaceJuror(JuryRecusal $recusal): void
    {
        $vacancy = $recusal->vacancy;
        $vacancy->jurors()->detach($recusal->juror_id);
        DB::table('jury_assignments')
            ->where('vacancy_id', $vacancy->id)
            ->where('role', 'accesitario')
            ->update(['role' => 'titular']);
    }
}
