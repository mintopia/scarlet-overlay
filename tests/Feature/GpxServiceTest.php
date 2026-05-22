<?php

namespace Tests\Feature;

use App\Services\GpxService;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class GpxServiceTest extends TestCase
{
    public function test_parses_route_waypoints_from_gpx(): void
    {
        $service = new GpxService();
        $path = base_path('tests/fixtures/test-route.gpx');
        $waypoints = $service->parseWaypoints(file_get_contents($path));

        $this->assertCount(4, $waypoints);
        $this->assertEquals('Lymington', $waypoints[0]['name']);
        $this->assertEqualsWithDelta(50.7572, $waypoints[0]['lat'], 0.0001);
        $this->assertEqualsWithDelta(-1.5435, $waypoints[0]['lng'], 0.0001);
        $this->assertNull($waypoints[2]['name']);
        $this->assertEquals('Yarmouth', $waypoints[3]['name']);
    }

    public function test_returns_empty_array_for_gpx_without_route(): void
    {
        $service = new GpxService();
        $xml = '<?xml version="1.0"?><gpx version="1.1"><trk><trkseg><trkpt lat="50.0" lon="-1.0"/></trkseg></trk></gpx>';
        $waypoints = $service->parseWaypoints($xml);

        $this->assertEmpty($waypoints);
    }

    public function test_returns_empty_array_for_invalid_xml(): void
    {
        $service = new GpxService();
        $waypoints = $service->parseWaypoints('not xml at all');

        $this->assertEmpty($waypoints);
    }
}
