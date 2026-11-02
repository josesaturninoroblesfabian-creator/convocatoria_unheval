<?php

namespace App\Http\Controllers;

use App\Models\RecruitmentCall;
use App\Models\Vacancy;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PortalController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:120'], 'faculty' => ['nullable', 'string', 'max:180'], 'status' => ['nullable', 'in:abiertas,cerradas,todas']]);
        $query = Vacancy::query()->with('recruitmentCall')->whereHas('recruitmentCall', fn ($q) => $q->where('status', '!=', 'BORRADOR'));
        if (! empty($filters['q'])) {
            $term = $filters['q'];
            $query->where(fn ($q) => $q->where('school', 'like', "%{$term}%")->orWhere('airhsp', 'like', "%{$term}%")->orWhere('faculty', 'like', "%{$term}%"));
        }
        if (! empty($filters['faculty'])) {
            $query->where('faculty', $filters['faculty']);
        }
        if (($filters['status'] ?? 'abiertas') === 'abiertas') {
            $query->whereHas('recruitmentCall', fn ($q) => $q->where('status', 'PUBLICADA')->where('opens_at', '<=', now())->where('closes_at', '>=', now()));
        }
        if (($filters['status'] ?? 'abiertas') === 'cerradas') {
            $query->whereHas('recruitmentCall', fn ($q) => $q->where(fn ($q) => $q->where('closes_at', '<', now())->orWhereIn('status', ['EN_EVALUACION', 'FINALIZADA', 'ARCHIVADA'])));
        }

        $calls = RecruitmentCall::where('status', '!=', 'BORRADOR')->with('vacancies')->withCount('vacancies');
        if (! empty($filters['faculty'])) {
            $calls->whereHas('vacancies', fn ($query) => $query->where('faculty', $filters['faculty']));
        }
        if (! empty($filters['q'])) {
            $term = $filters['q'];
            $calls->where(fn ($query) => $query->where('title', 'like', "%{$term}%")->orWhere('code', 'like', "%{$term}%")->orWhereHas('vacancies', fn ($query) => $query->where('school', 'like', "%{$term}%")->orWhere('airhsp', 'like', "%{$term}%")));
        }
        if (($filters['status'] ?? 'abiertas') === 'abiertas') {
            $calls->where('status', 'PUBLICADA')->where('closes_at', '>=', now());
        }
        if (($filters['status'] ?? 'abiertas') === 'cerradas') {
            $calls->where(fn ($query) => $query->where('closes_at', '<', now())->orWhereIn('status', ['EN_EVALUACION', 'FINALIZADA', 'ARCHIVADA']));
        }

        return Inertia::render('Portal', ['vacancies' => $query->orderByDesc('id')->paginate(8)->withQueryString(), 'filters' => $filters, 'faculties' => Vacancy::whereHas('recruitmentCall', fn ($q) => $q->where('status', '!=', 'BORRADOR'))->distinct()->orderBy('faculty')->pluck('faculty'), 'calls' => $calls->orderByDesc('id')->get(), 'stats' => ['open' => RecruitmentCall::where('status', 'PUBLICADA')->where('opens_at', '<=', now())->where('closes_at', '>=', now())->count(), 'vacancies' => Vacancy::whereHas('recruitmentCall', fn ($q) => $q->where('status', 'PUBLICADA')->where('opens_at', '<=', now())->where('closes_at', '>=', now()))->count(), 'faculties' => Vacancy::whereHas('recruitmentCall', fn ($q) => $q->where('status', '!=', 'BORRADOR'))->distinct()->count('faculty')]]);
    }

    public function show(Vacancy $vacancy): Response
    {
        $vacancy->load('recruitmentCall');
        abort_if($vacancy->recruitmentCall->status === 'BORRADOR', 404);

        return Inertia::render('Vacancy', ['vacancy' => $vacancy]);
    }

    public function call(RecruitmentCall $recruitmentCall): Response
    {
        abort_if($recruitmentCall->status === 'BORRADOR', 404);

        return Inertia::render('Call', ['call' => $recruitmentCall->load('vacancies')]);
    }

    public function results(): Response
    {
        $calls = RecruitmentCall::whereNotNull('results_published_at')->whereIn('status', ['FINALIZADA', 'ARCHIVADA'])->with(['vacancies.applications' => fn ($q) => $q->whereNotNull('submitted_at')->with('evaluations')])->get();
        $data = $calls->map(fn ($call) => ['id' => $call->id, 'title' => $call->title, 'is_demo' => $call->is_demo, 'published_at' => $call->results_published_at, 'vacancies' => $call->vacancies->map(fn ($v) => ['school' => $v->school, 'airhsp' => $v->airhsp, 'rows' => $v->applications->map(fn ($a) => ['reference' => $a->reference, 'status' => $a->status, 'score' => $a->status === 'EVALUADA' ? round((float) $a->evaluations->avg('total'), 3) : null])->sortByDesc('score')->values()])]);

        return Inertia::render('Results', ['calls' => $data]);
    }

    public function help(): Response
    {
        return Inertia::render('Help');
    }
}
