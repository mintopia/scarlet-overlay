<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\Concerns\MapsLegacyMetricKeys;
use App\Http\Controllers\LcarsController;
use App\Models\BoatSetting;
use App\Services\CanonicalCatalog;
use App\Services\CanonicalReader;
use App\Support\CanonicalBaseline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LcarsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_lcars_page_is_publicly_accessible(): void
    {
        // No auth: the LCARS console exposes only data the public dashboard already broadcasts.
        $response = $this->get('/lcars');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Lcars')
            ->has('initialMetrics')
            ->has('initialMetrics.boat')
            ->has('initialMetrics.gps')
            ->has('boatName')
            ->has('registry')
            ->has('reverb')
            ->has('msdHistory')
        );
    }

    public function test_lcars_series_is_publicly_accessible(): void
    {
        $this->getJson('/lcars/series?station=conn')->assertStatus(200);
    }

    public function test_lcars_identity_falls_back_when_settings_empty(): void
    {
        BoatSetting::query()->delete();

        $this->get('/lcars')->assertInertia(fn ($page) => $page
            ->where('boatName', config('scarlet.name'))
            ->where('registry', config('scarlet.mmsi'))
        );
    }

    public function test_series_returns_independently_nullable_series(): void
    {
        $response = $this->getJson('/lcars/series?station=ops');

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertIsArray($data);
        $this->assertCount(count(LcarsController::STATION_HISTORY['ops']), $data);

        foreach ($data as $series) {
            $this->assertArrayHasKey('key', $series);
            $this->assertArrayHasKey('points', $series);
            $this->assertArrayHasKey('status', $series);
            $this->assertContains($series['status'], ['ok', 'empty', 'unavailable']);
            $this->assertArrayNotHasKey('error', $series);
        }
    }

    public function test_series_rejects_unknown_station(): void
    {
        $this->getJson('/lcars/series?station=tactical')->assertStatus(422);
    }

    public function test_series_isolates_a_failing_key(): void
    {
        // The science station reads water_temp (canonical key water_temp) and
        // cabin_pressure_forepeak; throw on the former, return empty for the latter.
        $this->mock(CanonicalReader::class, function ($mock) {
            $mock->shouldReceive('readRange')
                ->andReturnUsing(function (string $key) {
                    if ($key === 'water_temp') {
                        throw new \RuntimeException('boom');
                    }

                    return [];
                });
        });

        $response = $this->getJson('/lcars/series?station=science');
        $response->assertStatus(200);

        $byKey = collect($response->json())->keyBy('key');
        $this->assertSame('unavailable', $byKey['water_temp']['status']);
        $this->assertNull($byKey['water_temp']['points']);
        $this->assertSame('empty', $byKey['cabin_pressure_forepeak']['status']);
    }

    public function test_station_history_keys_all_resolve_to_canonical_definitions(): void
    {
        app(CanonicalCatalog::class)->applyBaseline(CanonicalBaseline::definitions(), 'reset', 'test');
        $catalog = app(CanonicalCatalog::class)->all();

        $probe = new class
        {
            use MapsLegacyMetricKeys;

            public function resolve(string $key): ?string
            {
                return $this->canonicalKey($key);
            }
        };

        foreach (LcarsController::STATION_HISTORY as $station => $keys) {
            foreach ($keys as $key) {
                $canonicalKey = $probe->resolve($key) ?? $key;
                $this->assertArrayHasKey(
                    $canonicalKey,
                    $catalog,
                    "Station '{$station}' references key '{$key}' with no canonical definition '{$canonicalKey}'",
                );
            }
        }
    }
}
