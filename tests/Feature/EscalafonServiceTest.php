<?php

namespace Tests\Feature;

use App\Services\EscalafonService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EscalafonServiceTest extends TestCase
{
    public function test_lookup_returns_disabled_when_not_configured(): void
    {
        config(['services.sso' => ['enabled' => false, 'escalafon_endpoint' => null, 'escalafon_token' => null]]);

        $result = app(EscalafonService::class)->lookup('12345678');

        $this->assertSame('disabled', $result['status']);
    }

    public function test_lookup_returns_data_when_configured(): void
    {
        Http::fake(['https://escalafon.unheval.edu.pe/*' => Http::response(['categoria' => 'Asociado', 'anios' => 12])]);
        config(['services.sso' => ['enabled' => true, 'escalafon_endpoint' => 'https://escalafon.unheval.edu.pe/api/docente', 'escalafon_token' => 'token-prueba']]);

        $result = app(EscalafonService::class)->lookup('12345678');

        $this->assertSame('found', $result['status']);
        $this->assertSame('Asociado', $result['data']['categoria']);
    }
}
