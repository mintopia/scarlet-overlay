<?php

namespace Tests\Feature;

use App\Services\CanonicalCatalog;
use App\Services\CanonicalReader;
use App\Services\MetricsService;
use App\Support\CanonicalBaseline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class CanonicalFuelWaterTest extends TestCase
{
    use RefreshDatabase;

    public function test_boat_metrics_resolve_fuel_and_water_from_canonical_reader(): void
    {
        Http::fake([
            '*' => Http::response(['status' => 'success', 'data' => ['result' => []]]),
        ]);

        /** @var CanonicalReader&MockObject $reader */
        $reader = $this->createMock(CanonicalReader::class);
        $reader->method('readMany')->willReturnCallback(function (array $keys): array {
            $envelopes = [
                'fuel_level' => ['value' => 63.0, 'raw' => 63.0, 'unit' => '%', 'timestamp' => 1, 'age' => 20, 'stale' => false, 'resolved_source' => 'mqtt'],
                'water_fresh_level' => ['value' => 41.0, 'raw' => 41.0, 'unit' => '%', 'timestamp' => 1, 'age' => 20, 'stale' => false, 'resolved_source' => 'mqtt'],
            ];

            $out = [];
            foreach ($keys as $key) {
                $out[$key] = $envelopes[$key] ?? null;
            }

            return $out;
        });
        $this->app->instance(CanonicalReader::class, $reader);

        $service = $this->app->make(MetricsService::class);
        $boat = $service->getBoatMetrics();

        $this->assertEqualsWithDelta(63.0, $boat['fuel_level'], 0.001);
        $this->assertEqualsWithDelta(41.0, $boat['water_level'], 0.001);
    }

    public function test_boat_metrics_includes_computed_true_wind(): void
    {
        /** @var CanonicalReader&MockObject $reader */
        $reader = $this->createMock(CanonicalReader::class);
        $reader->method('readMany')->willReturnCallback(fn (array $keys) => array_fill_keys($keys, null));
        $this->app->instance(CanonicalReader::class, $reader);

        Http::fake([
            '*' => Http::response(['status' => 'success', 'data' => ['result' => []]]),
        ]);

        $service = $this->app->make(MetricsService::class);
        $boat = $service->getBoatMetrics();

        $this->assertArrayHasKey('wind_speed_true', $boat);
        $this->assertArrayHasKey('wind_direction_true', $boat);
    }

    public function test_get_all_metrics_includes_canonical_block_when_enabled(): void
    {
        Http::fake([
            '*' => Http::response(['status' => 'success', 'data' => ['result' => []]]),
        ]);

        Config::set('scarlet.canonical.enabled', true);
        app(CanonicalCatalog::class)->applyBaseline(CanonicalBaseline::definitions(), 'reset', 'test');

        /** @var CanonicalReader&MockObject $reader */
        $reader = $this->createMock(CanonicalReader::class);
        $reader->method('catalogVersion')->willReturn(4);
        $reader->method('readMany')->willReturnCallback(function (array $keys): array {
            $out = [];
            foreach ($keys as $key) {
                $out[$key] = $key === 'fuel_level'
                    ? ['value' => 63.0, 'raw' => 63.0, 'unit' => '%', 'timestamp' => 1, 'age' => 20, 'stale' => false, 'resolved_source' => 'mqtt']
                    : null;
            }

            return $out;
        });
        $this->app->instance(CanonicalReader::class, $reader);

        $all = $this->app->make(MetricsService::class)->getAllMetrics();

        $this->assertSame(4, $all['catalog_version']);
        $this->assertArrayHasKey('fuel_level', $all['canonical']);
        $this->assertEqualsWithDelta(63.0, $all['canonical']['fuel_level']['value'], 0.001);
        $this->assertSame(20, $all['canonical']['fuel_level']['age']);
        $this->assertArrayNotHasKey('water_fresh_level', $all['canonical']);
    }

    public function test_get_all_metrics_canonical_empty_when_disabled(): void
    {
        Http::fake([
            '*' => Http::response(['status' => 'success', 'data' => ['result' => []]]),
        ]);
        Config::set('scarlet.canonical.enabled', false);

        /** @var CanonicalReader&MockObject $reader */
        $reader = $this->createMock(CanonicalReader::class);
        $reader->method('readMany')->willReturnCallback(fn (array $keys) => array_fill_keys($keys, null));
        $this->app->instance(CanonicalReader::class, $reader);

        $all = $this->app->make(MetricsService::class)->getAllMetrics();

        $this->assertSame([], $all['canonical']);
    }
}
