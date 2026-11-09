<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProfileRegistryLookupTest extends TestCase
{
    public function test_registration_dni_lookup_requires_consent_and_returns_only_the_name(): void
    {
        $this->enableLocalLookups();
        Http::fake(['dniruc.apisperu.com/*' => Http::response([
            'dni' => '00123456', 'nombres' => 'Nombre', 'apellidoPaterno' => 'Apellido',
            'apellidoMaterno' => 'Prueba', 'codVerifica' => '2', 'foto' => 'private-field',
        ])]);
        $this->get(route('register'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('dniLookupEnabled', true)
        );
        $this->postJson(route('register.dni.lookup'), ['dni' => '00123456'])
            ->assertUnprocessable()->assertJsonValidationErrors('consent');
        Http::assertNothingSent();

        $this->postJson(route('register.dni.lookup'), ['dni' => '00123456', 'consent' => true])
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertExactJson(['status' => 'found', 'name' => 'Nombre Apellido Prueba']);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://dniruc.apisperu.com/api/v1/dni/00123456?token=prueba-dni');
    }

    public function test_academic_queries_use_the_correct_endpoint_and_return_minimal_records(): void
    {
        $this->enableLocalLookups();
        Http::fake([
            'https://apisted.com/api/minedu/universitarios' => Http::response([
                'criterio' => '00123456', 'encontrado' => true,
                'registros' => [['grado_o_titulo' => 'Maestría de prueba', 'universidad' => 'Universidad de prueba', 'fecha_diploma' => '15/08/2021', 'resolucion' => 'Dato sin uso']],
            ]),
            'https://apisted.com/api/minedu/titulos' => Http::response([
                'criterio' => '00123456', 'encontrado' => true,
                'registros' => [['grado_o_titulo' => 'Título técnico de prueba', 'institucion_educativa' => 'Instituto de prueba', 'fecha_emision' => '10/01/2018']],
            ]),
        ]);
        $this->actingAs(User::factory()->create(['document_number' => '00123456']));
        $this->get(route('profile'))->assertInertia(fn (Assert $page) => $page
            ->where('registrySources', ['sunedu', 'minedu'])
        );
        $this->postJson(route('profile.registry.lookup'), ['source' => 'dni', 'consent' => true])
            ->assertUnprocessable()->assertJsonValidationErrors('source');

        $this->postJson(route('profile.registry.lookup'), ['source' => 'sunedu', 'consent' => true])
            ->assertExactJson(['status' => 'found', 'records' => [
                ['title' => 'Maestría de prueba', 'institution' => 'Universidad de prueba', 'date' => '15/08/2021', 'awarded_at' => '2021-08-15', 'kind' => 'maestro'],
            ]]);
        $this->postJson(route('profile.registry.lookup'), ['source' => 'minedu', 'consent' => true])
            ->assertExactJson(['status' => 'found', 'records' => [
                ['title' => 'Título técnico de prueba', 'institution' => 'Instituto de prueba', 'date' => '10/01/2018', 'awarded_at' => '2018-01-10', 'kind' => 'instituto'],
            ]]);
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://apisted.com/api/minedu/universitarios'
            && $request->hasHeader('Authorization', 'Bearer prueba-academica')
            && $request->data() === ['dni' => '00123456']);
    }

    public function test_rejects_injected_dni_missing_consent_and_calls_from_an_external_origin(): void
    {
        $this->enableLocalLookups();
        Http::fake();
        $this->actingAs(User::factory()->create(['document_number' => '00123456']));

        $this->postJson(route('profile.registry.lookup'), ['source' => 'sunedu', 'consent' => true, 'dni' => '76543210'])
            ->assertUnprocessable()->assertJsonValidationErrors('dni');
        $this->postJson(route('profile.registry.lookup'), ['source' => 'sunedu'])
            ->assertUnprocessable()->assertJsonValidationErrors('consent');
        $this->withHeaders(['X-Forwarded-For' => '203.0.113.7'])
            ->postJson(route('profile.registry.lookup'), ['source' => 'sunedu', 'consent' => true])->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_reports_an_empty_result_and_plan_errors_without_exposing_raw_provider_data(): void
    {
        $this->enableLocalLookups();
        Http::fake(['https://apisted.com/api/minedu/*' => Http::sequence()
            ->push(['criterio' => '00123456', 'encontrado' => false, 'registros' => []])
            ->push(['message' => 'Información privada del proveedor'], 402)
            ->push(['criterio' => '76543210', 'encontrado' => true, 'registros' => [['grado_o_titulo' => 'Registro de otra persona']]])]);
        $this->actingAs(User::factory()->create(['document_number' => '00123456']));
        $data = ['source' => 'minedu', 'consent' => true];

        $this->postJson(route('profile.registry.lookup'), $data)->assertExactJson(['status' => 'empty', 'records' => []]);
        $this->postJson(route('profile.registry.lookup'), $data)->assertJsonPath('status', 'unavailable')->assertDontSee('Información privada');
        $this->postJson(route('profile.registry.lookup'), $data)->assertJsonPath('status', 'unavailable')->assertDontSee('Registro de otra persona');
        $this->postJson(route('profile.registry.lookup'), $data)->assertTooManyRequests();
        Http::assertSentCount(3);
    }

    private function enableLocalLookups(): void
    {
        $this->app['env'] = 'local';
        $this->withoutMiddleware(PreventRequestForgery::class);
        config(['inertia.ssr.enabled' => false]);
        config(['services.profile_registry' => ['enabled' => true, 'apisperu_token' => 'prueba-dni', 'apisted_token' => 'prueba-academica']]);
        Http::preventStrayRequests();
    }
}
