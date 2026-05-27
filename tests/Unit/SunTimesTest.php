<?php

namespace Tests\Unit;

use App\Services\MetricsService;
use App\Services\PrometheusService;
use App\Services\WeatherService;
use Mockery;
use Tests\TestCase;

class SunTimesTest extends TestCase
{
    private function makeService(): MetricsService
    {
        return new MetricsService(
            Mockery::mock(PrometheusService::class),
            Mockery::mock(WeatherService::class),
        );
    }

    public function test_returns_null_without_coordinates(): void
    {
        $service = $this->makeService();

        $this->assertNull($service->getSunTimes(null, null));
        $this->assertNull($service->getSunTimes(50.0, null));
        $this->assertNull($service->getSunTimes(null, -1.5));
    }

    public function test_returns_sunrise_and_sunset_times(): void
    {
        $service = $this->makeService();
        $result = $service->getSunTimes(50.6931, -1.6433, 'Europe/London');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('sunrise', $result);
        $this->assertArrayHasKey('sunset', $result);
        $this->assertArrayHasKey('civilDawn', $result);
        $this->assertArrayHasKey('civilDusk', $result);
        $this->assertArrayHasKey('isDay', $result);
        $this->assertIsBool($result['isDay']);
    }

    public function test_times_are_formatted_as_hhmm(): void
    {
        $service = $this->makeService();
        $result = $service->getSunTimes(50.6931, -1.6433, 'Europe/London');

        $this->assertMatchesRegularExpression('/^\d{2}:\d{2}$/', $result['sunrise']);
        $this->assertMatchesRegularExpression('/^\d{2}:\d{2}$/', $result['sunset']);
        $this->assertMatchesRegularExpression('/^\d{2}:\d{2}$/', $result['civilDawn']);
        $this->assertMatchesRegularExpression('/^\d{2}:\d{2}$/', $result['civilDusk']);
    }

    public function test_defaults_to_utc_timezone(): void
    {
        $service = $this->makeService();
        $result = $service->getSunTimes(50.6931, -1.6433);

        $this->assertIsArray($result);
        $this->assertMatchesRegularExpression('/^\d{2}:\d{2}$/', $result['sunrise']);
    }

    public function test_respects_timezone(): void
    {
        $service = $this->makeService();
        $utc = $service->getSunTimes(35.6762, 139.6503, 'UTC');
        $tokyo = $service->getSunTimes(35.6762, 139.6503, 'Asia/Tokyo');

        $this->assertNotEquals($utc['sunrise'], $tokyo['sunrise']);
    }
}
