<?php

namespace Tests\Feature;

use App\Services\CanonicalReader;
use App\Services\MetricsService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class CanonicalFuelWaterTest extends TestCase
{
    public function test_enabled_flag_overrides_fuel_from_canonical_reader(): void
    {
        Http::fake([
            '*' => Http::response(['status' => 'success', 'data' => ['result' => []]]),
        ]);

        Config::set('scarlet.canonical.enabled', true);
        Config::set('scarlet.canonical.overrides', [
            'fuel_level' => 'fuel_level',
            'water_fresh_level' => 'water_level',
        ]);

        /** @var CanonicalReader&MockObject $reader */
        $reader = $this->createMock(CanonicalReader::class);
        $reader->method('read')->willReturnMap([
            ['fuel_level', ['value' => 63.0, 'raw' => 63.0, 'unit' => '%', 'timestamp' => 1, 'age' => 20, 'stale' => false, 'resolved_source' => 'mqtt']],
            ['water_fresh_level', ['value' => 41.0, 'raw' => 41.0, 'unit' => '%', 'timestamp' => 1, 'age' => 20, 'stale' => false, 'resolved_source' => 'mqtt']],
        ]);
        $this->app->instance(CanonicalReader::class, $reader);

        $service = $this->app->make(MetricsService::class);
        $boat = $service->getBoatMetrics();

        $this->assertEqualsWithDelta(63.0, $boat['fuel_level'], 0.001);
        $this->assertEqualsWithDelta(41.0, $boat['water_level'], 0.001);
    }

    public function test_disabled_flag_does_not_call_reader(): void
    {
        Config::set('scarlet.canonical.enabled', false);

        /** @var CanonicalReader&MockObject $reader */
        $reader = $this->createMock(CanonicalReader::class);
        $reader->expects($this->never())->method('read');
        $this->app->instance(CanonicalReader::class, $reader);

        Http::fake([
            '*' => Http::response(['status' => 'success', 'data' => ['result' => []]]),
        ]);

        $service = $this->app->make(MetricsService::class);
        $boat = $service->getBoatMetrics();

        $this->assertArrayHasKey('wind_speed_true', $boat);
    }
}
