<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => ['user' => $request->user()?->only('id', 'name', 'email', 'role', 'document_number', 'phone')],
            'flash' => ['success' => fn () => $request->session()->get('success')],
            'baseUrl' => $request->getBaseUrl(),
            'demo' => config('recruitment.demo'),
            'serverNow' => now()->toISOString(),
        ];
    }
}
