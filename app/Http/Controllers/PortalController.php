<?php

namespace App\Http\Controllers;

use App\Models\RecruitmentCall;
use App\Models\Vacancy;
use App\Services\VacancyEvaluationWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PortalController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:120'], 'faculty' => ['nullable', 'string', 'max:180'], 'status' => ['nullable', 'in:abiertas,cerradas,todas']]);
        $query = Vacancy::query()->with('recruitmentCall')->whereHas('recruitmentCall', fn ($q) => $q->where('status', '!=', 'BORRADOR'));
        if (! empty($filters['q'])) {
            $term = $filters['q'];
            $query->where(fn ($q) => $q->where('school', 'like', "%{$term}%")->orWhere('airhsp', 'like', "%{$term}%")->orWhere('faculty', 'like', "%{$term}%")->orWhereHas('recruitmentCall', fn ($call) => $call->where('title', 'like', "%{$term}%")->orWhere('code', 'like', "%{$term}%")));
        }
        if (! empty($filters['faculty'])) {
            $query->where('faculty', $filters['faculty']);
        }
        if (($filters['status'] ?? 'abiertas') === 'abiertas') {
            $query->whereHas('recruitmentCall', fn ($q) => $q->where('status', 'PUBLICADA')->where('closes_at', '>=', now()));
        }
        if (($filters['status'] ?? 'abiertas') === 'cerradas') {
            $query->whereHas('recruitmentCall', fn ($q) => $q->where(fn ($q) => $q->where('closes_at', '<', now())->orWhereIn('status', ['EN_EVALUACION', 'FINALIZADA', 'ARCHIVADA'])));
        }

        return Inertia::render('Portal', ['vacancies' => $query->orderByDesc('id')->paginate(8)->withQueryString(), 'filters' => $filters, 'faculties' => Vacancy::whereHas('recruitmentCall', fn ($q) => $q->where('status', '!=', 'BORRADOR'))->distinct()->orderBy('faculty')->pluck('faculty'), 'stats' => ['open' => RecruitmentCall::where('status', 'PUBLICADA')->where('opens_at', '<=', now())->where('closes_at', '>=', now())->count(), 'vacancies' => Vacancy::whereHas('recruitmentCall', fn ($q) => $q->where('status', 'PUBLICADA')->where('opens_at', '<=', now())->where('closes_at', '>=', now()))->count(), 'faculties' => Vacancy::whereHas('recruitmentCall', fn ($q) => $q->where('status', '!=', 'BORRADOR'))->distinct()->count('faculty')]]);
    }

    public function show(Vacancy $vacancy): Response
    {
        $vacancy->load('recruitmentCall');
        abort_if($vacancy->recruitmentCall->status === 'BORRADOR', 404);

        return Inertia::render('Vacancy', ['vacancy' => $vacancy, 'stagePublications' => $vacancy->stage_publications ?? []]);
    }

    public function vacancyDocument(Request $request, Vacancy $vacancy, string $document): StreamedResponse
    {
        abort_unless($vacancy->recruitmentCall->status !== 'BORRADOR' || $request->user()?->isManager(), 404);
        $attachment = collect($vacancy->attached_documents)->firstWhere('id', $document);
        $disk = Storage::disk('local');
        abort_unless($attachment && $disk->exists($attachment['path']), 404);

        return $disk->response($attachment['path'], $attachment['name'].'.pdf', [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function call(RecruitmentCall $recruitmentCall): Response
    {
        abort_if($recruitmentCall->status === 'BORRADOR', 404);

        return Inertia::render('Call', ['call' => $recruitmentCall->load('vacancies')]);
    }

    public function results(): Response
    {
        $calls = RecruitmentCall::whereNotNull('results_published_at')->whereIn('status', ['FINALIZADA', 'ARCHIVADA'])->with(['vacancies.applications' => fn ($q) => $q->whereNotNull('submitted_at')->with('evaluations')])->get();
        $data = $calls->map(fn ($call) => [
            'id' => $call->id,
            'title' => $call->title,
            'is_demo' => $call->is_demo,
            'evaluation_mode' => $call->evaluation_mode,
            'published_at' => $call->results_published_at,
            'vacancies' => $call->vacancies->map(fn ($vacancy) => [
                'school' => $vacancy->school,
                'airhsp' => $vacancy->airhsp,
                'by_vacancy' => VacancyEvaluationWorkflow::configured($vacancy),
                'rows' => $vacancy->applications->map(fn ($application) => [
                    'reference' => $application->reference,
                    'status' => $application->status,
                    'outcome' => VacancyEvaluationWorkflow::configured($vacancy)
                        ? ['stage' => $application->evaluation_outcome['stage'] ?? null]
                        : $application->evaluation_outcome,
                    'score' => VacancyEvaluationWorkflow::configured($vacancy)
                        ? ($application->evaluation_outcome['final'] ?? null)
                        : ($call->evaluation_template
                        ? ($application->evaluation_outcome['total'] ?? null)
                        : ($application->status === 'EVALUADA' ? round((float) $application->evaluations->avg('total'), 3) : null)),
                ])->sortByDesc('score')->values(),
            ]),
        ]);

        return Inertia::render('Results', ['calls' => $data]);
    }

    public function help(): Response
    {
        return Inertia::render('Help');
    }
}
