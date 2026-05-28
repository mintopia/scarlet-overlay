<?php

namespace Tests\Feature;

use App\Services\MetricRegistry;
use App\Services\PrometheusService;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class MetricRegistryTest extends TestCase
{
    private MetricRegistry $registry;

    /** @var PrometheusService&MockObject */
    private PrometheusService $prometheus;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('scarlet.metrics.registry', [
            'speed_sog' => [
                'query' => 'scarlet_signalk_navigation_speedOverGround',
                'multiply' => 1.94384,
            ],
            'heading' => [
                'query' => 'scarlet_signalk_navigation_headingTrue',
                'multiply' => 180,
                'divide' => 3.14159265359,
            ],
            'water_temp' => [
                'query' => 'scarlet_signalk_environment_water_temperature',
                'subtract' => 273.15,
            ],
            'gps_latitude' => [
                'query' => 'scarlet_gps_latitude_deg{gps_source="signalk"} != 0',
                'fallback' => 'scarlet_gps_latitude_deg{gps_source="onboard"} != 0',
            ],
            'cabin_temp_main' => [
                'query' => 'scarlet_mqtt_temperature{topic="zigbee2mqtt/Main Cabin"}',
                'sticky' => true,
            ],
            'plain_metric' => [
                'query' => 'scarlet_some_plain_metric',
            ],
        ]);

        Config::set('scarlet.metrics.groups', [
            'boat' => ['speed_sog', 'heading', 'water_temp'],
            'tracker' => ['gps_latitude', 'cabin_temp_main'],
        ]);

        $this->prometheus = $this->createMock(PrometheusService::class);
        $this->registry = new MetricRegistry($this->prometheus);
    }

    public function test_definition_returns_array_for_known_key(): void
    {
        $def = $this->registry->definition('speed_sog');

        $this->assertIsArray($def);
        $this->assertSame('scarlet_signalk_navigation_speedOverGround', $def['query']);
        $this->assertSame(1.94384, $def['multiply']);
    }

    public function test_definition_returns_null_for_unknown_key(): void
    {
        $this->assertNull($this->registry->definition('nonexistent_key'));
    }

    public function test_instant_query_applies_multiply(): void
    {
        $q = $this->registry->instantQuery('speed_sog');

        $this->assertSame('(scarlet_signalk_navigation_speedOverGround) * 1.94384', $q);
    }

    public function test_instant_query_applies_multiply_then_divide(): void
    {
        $q = $this->registry->instantQuery('heading');

        $this->assertSame('((scarlet_signalk_navigation_headingTrue) * 180) / 3.14159265359', $q);
    }

    public function test_instant_query_applies_subtract(): void
    {
        $q = $this->registry->instantQuery('water_temp');

        $this->assertSame('(scarlet_signalk_environment_water_temperature) - 273.15', $q);
    }

    public function test_instant_query_with_fallback_uses_default_operator(): void
    {
        $q = $this->registry->instantQuery('gps_latitude');

        $this->assertSame(
            'max(scarlet_gps_latitude_deg{gps_source="signalk"} != 0) default max(scarlet_gps_latitude_deg{gps_source="onboard"} != 0)',
            $q
        );
    }

    public function test_instant_query_no_transform_returns_raw_query(): void
    {
        $q = $this->registry->instantQuery('cabin_temp_main');

        $this->assertSame('scarlet_mqtt_temperature{topic="zigbee2mqtt/Main Cabin"}', $q);
    }

    public function test_instant_query_unknown_key_returns_key(): void
    {
        $q = $this->registry->instantQuery('nonexistent');

        $this->assertSame('nonexistent', $q);
    }

    public function test_range_query_wraps_metric_with_keep_last_value(): void
    {
        $q = $this->registry->rangeQuery('plain_metric');

        $this->assertSame('max(keep_last_value(scarlet_some_plain_metric))', $q);
    }

    public function test_range_query_wraps_and_applies_multiply(): void
    {
        $q = $this->registry->rangeQuery('speed_sog');

        $this->assertSame('(max(keep_last_value(scarlet_signalk_navigation_speedOverGround))) * 1.94384', $q);
    }

    public function test_range_query_wraps_and_applies_multiply_then_divide(): void
    {
        $q = $this->registry->rangeQuery('heading');

        $this->assertSame('((max(keep_last_value(scarlet_signalk_navigation_headingTrue))) * 180) / 3.14159265359', $q);
    }

    public function test_range_query_wraps_and_applies_subtract(): void
    {
        $q = $this->registry->rangeQuery('water_temp');

        $this->assertSame('(max(keep_last_value(scarlet_signalk_environment_water_temperature))) - 273.15', $q);
    }

    public function test_range_query_with_fallback_wraps_each_side_independently(): void
    {
        $q = $this->registry->rangeQuery('gps_latitude');

        $this->assertSame(
            '(max(keep_last_value(scarlet_gps_latitude_deg{gps_source="signalk"})) != 0) default (max(keep_last_value(scarlet_gps_latitude_deg{gps_source="onboard"})) != 0)',
            $q
        );
    }

    public function test_range_query_with_label_filter_wraps_correctly(): void
    {
        $q = $this->registry->rangeQuery('cabin_temp_main');

        $this->assertSame('max(keep_last_value(scarlet_mqtt_temperature{topic="zigbee2mqtt/Main Cabin"}))', $q);
    }

    public function test_fetch_instant_delegates_to_prometheus_with_timestamp(): void
    {
        $timestamp = 1700000000;

        $this->prometheus
            ->expects($this->once())
            ->method('queryMultipleAt')
            ->with(
                [
                    'speed_sog' => '(scarlet_signalk_navigation_speedOverGround) * 1.94384',
                    'water_temp' => '(scarlet_signalk_environment_water_temperature) - 273.15',
                ],
                $timestamp,
                fallback: true
            )
            ->willReturn(['speed_sog' => 3.5, 'water_temp' => 18.0]);

        $result = $this->registry->fetchInstant(['speed_sog', 'water_temp'], $timestamp);

        $this->assertSame(['speed_sog' => 3.5, 'water_temp' => 18.0], $result);
    }

    public function test_fetch_instant_defaults_timestamp_to_now(): void
    {
        $before = now()->timestamp;
        $capturedTimestamp = null;

        $this->prometheus
            ->expects($this->once())
            ->method('queryMultipleAt')
            ->willReturnCallback(function (array $queries, int $timestamp, bool $fallback) use (&$capturedTimestamp): array {
                $capturedTimestamp = $timestamp;

                return [];
            });

        $this->registry->fetchInstant(['plain_metric']);

        $this->assertGreaterThanOrEqual($before, $capturedTimestamp);
    }

    public function test_fetch_range_delegates_to_prometheus(): void
    {
        $this->prometheus
            ->expects($this->once())
            ->method('queryRange')
            ->with(
                '(max(keep_last_value(scarlet_signalk_navigation_speedOverGround))) * 1.94384',
                null,
                '30s',
                1000,
                2000,
                true
            )
            ->willReturn([['timestamp' => 1000, 'value' => 3.5]]);

        $result = $this->registry->fetchRange('speed_sog', '30s', 1000, 2000);

        $this->assertSame([['timestamp' => 1000, 'value' => 3.5]], $result);
    }

    public function test_fetch_range_with_fallback_calls_query_range_with_fallback(): void
    {
        $this->prometheus
            ->expects($this->once())
            ->method('queryRangeWithFallback')
            ->with(
                'max(keep_last_value(scarlet_gps_latitude_deg{gps_source="signalk"})) != 0',
                'max(keep_last_value(scarlet_gps_latitude_deg{gps_source="onboard"})) != 0',
                null,
                '15s',
                1000,
                2000
            )
            ->willReturn([['timestamp' => 1000, 'value' => 55.0]]);

        $result = $this->registry->fetchRangeWithFallback('gps_latitude', '15s', 1000, 2000);

        $this->assertSame([['timestamp' => 1000, 'value' => 55.0]], $result);
    }

    public function test_fetch_range_with_fallback_delegates_to_fetch_range_when_no_fallback(): void
    {
        $this->prometheus
            ->expects($this->once())
            ->method('queryRange')
            ->with(
                '(max(keep_last_value(scarlet_signalk_navigation_speedOverGround))) * 1.94384',
                null,
                '15s',
                null,
                null,
                true
            )
            ->willReturn([]);

        $this->registry->fetchRangeWithFallback('speed_sog', '15s');
    }

    public function test_group_keys_returns_configured_keys(): void
    {
        $keys = $this->registry->groupKeys('boat');

        $this->assertSame(['speed_sog', 'heading', 'water_temp'], $keys);
    }

    public function test_group_keys_returns_empty_array_for_unknown_group(): void
    {
        $keys = $this->registry->groupKeys('nonexistent_group');

        $this->assertSame([], $keys);
    }
}
