<?php

namespace Tests\Feature;

use App\Services\OpenSeaMapService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MapTileControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
    }

    public function test_osm_route_returns_tile(): void
    {
        Http::fake([
            'tile.openstreetmap.org/*' => Http::response(file_get_contents(base_path('tests/fixtures/blank-tile.png')), 200),
        ]);

        $response = $this->get('/osm/10/512/340');

        $response->assertOk();
        $response->assertHeader('Cache-Control', 'immutable, max-age=604800, public');
        Storage::assertExists('openstreetmap/10.512.340.png');
    }

    public function test_cartodb_dark_route_returns_tile(): void
    {
        Http::fake([
            'basemaps.cartocdn.com/*' => Http::response(file_get_contents(base_path('tests/fixtures/blank-tile.png')), 200),
        ]);

        $response = $this->get('/cartodb-dark/10/512/340');

        $response->assertOk();
        $response->assertHeader('Cache-Control', 'immutable, max-age=604800, public');
        Storage::assertExists('cartodb-dark/10.512.340.png');
    }

    public function test_depth_contours_route_returns_tile(): void
    {
        Http::fake([
            'ows.emodnet-bathymetry.eu/*' => Http::response(file_get_contents(base_path('tests/fixtures/blank-tile.png')), 200),
        ]);

        $response = $this->get('/depth/8/128/85');

        $response->assertOk();
        $response->assertHeader('Cache-Control', 'immutable, max-age=604800, public');
        Storage::assertExists('depth-contours/8.128.85.png');
    }

    public function test_depth_contours_caches_tile(): void
    {
        Http::fake([
            'ows.emodnet-bathymetry.eu/*' => Http::response(file_get_contents(base_path('tests/fixtures/blank-tile.png')), 200),
        ]);

        $this->get('/depth/8/128/85')->assertOk();
        $this->get('/depth/8/128/85')->assertOk();

        Http::assertSentCount(1);
    }

    public function test_osm_returns_404_on_upstream_failure(): void
    {
        Http::fake([
            'tile.openstreetmap.org/*' => Http::response('', 500),
        ]);

        $response = $this->get('/osm/10/512/340');

        $response->assertNotFound();
    }

    public function test_depth_returns_404_on_upstream_failure(): void
    {
        Http::fake([
            'ows.emodnet-bathymetry.eu/*' => Http::response('', 500),
        ]);

        $response = $this->get('/depth/8/128/85');

        $response->assertNotFound();
    }

    public function test_depth_contour_sends_correct_wms_parameters(): void
    {
        Http::fake([
            'ows.emodnet-bathymetry.eu/*' => Http::response(file_get_contents(base_path('tests/fixtures/blank-tile.png')), 200),
        ]);

        $this->get('/depth/5/16/10');

        Http::assertSent(function ($request) {
            $url = $request->url();
            parse_str(parse_url($url, PHP_URL_QUERY), $query);

            return $query['LAYERS'] === 'contours'
                && $query['CRS'] === 'EPSG:3857'
                && $query['FORMAT'] === 'image/png'
                && $query['TRANSPARENT'] === 'true'
                && $query['WIDTH'] === '256'
                && $query['HEIGHT'] === '256';
        });
    }
}
