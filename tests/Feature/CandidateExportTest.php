<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\RecruitmentCall;
use App\Services\CandidateExport;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CandidateExportTest extends TestCase
{
    public function test_candidate_export_builds_a_structured_profile(): void
    {
        $call = RecruitmentCall::factory()->draft()->create(['code' => 'EXPORT-2026']);
        $application = Application::factory()->received()->create([
            'recruitment_call_id' => $call->id,
            'form_data' => ['degree' => 'maestro', 'credentials' => []],
        ]);

        $profile = app(CandidateExport::class)->profile($application);

        $this->assertSame($application->reference, $profile['reference']);
        $this->assertSame('EXPORT-2026', $profile['process']['call']);
        $this->assertSame('maestro', $profile['declared']['degree']);
        $this->assertSame('12345678', $profile['applicant']['document_number']);
        $this->assertArrayHasKey('screening', $profile);
        $this->assertArrayHasKey('documents', $profile);
        $this->assertArrayHasKey('evaluations', $profile);
        $this->assertArrayHasKey('outcome', $profile);
    }

    public function test_export_command_writes_structured_json(): void
    {
        Storage::fake('local');
        $call = RecruitmentCall::factory()->draft()->create(['code' => 'EXPORT-2026']);
        Application::factory()->received()->create(['recruitment_call_id' => $call->id]);

        $this->artisan('unheval:export', ['code' => 'EXPORT-2026'])->assertSuccessful();

        Storage::disk('local')->assertExists('exports/EXPORT-2026.json');
        $payload = json_decode(Storage::disk('local')->get('exports/EXPORT-2026.json'), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('EXPORT-2026', $payload['call']['code']);
        $this->assertCount(1, $payload['applications']);
        $this->assertArrayHasKey('reference', $payload['applications'][0]);
    }
}
