<?php

namespace Tests\Feature\Canonical;

use App\Services\CanonicalCatalog;
use App\Services\MetricsService;
use App\Support\CanonicalBaseline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ContractsBroadcastTest extends TestCase
{
    use RefreshDatabase;

    public function test_contracts_include_all_enabled_keys(): void
    {
        config()->set('scarlet.canonical.enabled', true);
        Http::fake(['*' => Http::response([
            'status' => 'success',
            'data' => ['resultType' => 'vector', 'result' => [['metric' => [], 'value' => [now()->timestamp, '1']]]],
        ], 200)]);

        app(CanonicalCatalog::class)->applyBaseline(CanonicalBaseline::definitions(), 'reset', 'test');

        $result = app(MetricsService::class)->getCanonicalContracts();

        $this->assertArrayHasKey('srt_up', $result['contracts']);
        $this->assertArrayHasKey('house_battery_soc', $result['contracts']);
        $this->assertGreaterThan(0, $result['version']);
    }
}
