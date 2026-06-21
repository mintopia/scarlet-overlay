<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OverlayControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_overlay_page_is_publicly_accessible(): void
    {
        $response = $this->get('/overlay');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Public/Overlay')
            ->has('initialMetrics')
            ->has('boatName')
            ->missing('transparent')
        );
    }

    public function test_transparent_overlay_renders_the_same_page_with_the_transparent_flag(): void
    {
        $response = $this->get('/overlay/transparent');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Public/Overlay')
            ->has('initialMetrics')
            ->has('boatName')
            ->where('transparent', true)
        );
    }

    public function test_map_overlay_renders_the_same_page_with_the_map_only_flag(): void
    {
        $response = $this->get('/overlay/map');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Public/Overlay')
            ->has('initialMetrics')
            ->has('boatName')
            ->has('gpsTrack')
            ->where('mapOnly', true)
        );
    }
}
