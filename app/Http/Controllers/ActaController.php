<?php

namespace App\Http\Controllers;

use App\Models\Acta;
use App\Models\AuditEvent;
use App\Services\ActaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ActaController extends Controller
{
    public function sign(Request $request, Acta $acta, ActaService $service): RedirectResponse
    {
        abort_unless($acta->vacancy->jurors()->where('users.id', $request->user()->id)->exists(), 404);
        $service->sign($acta, $request->user());
        AuditEvent::record('ACTA_FIRMADA', $acta, ['stage' => $acta->stage, 'juror_id' => $request->user()->id]);

        return back()->with('success', 'Acta firmada correctamente.');
    }
}
