<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Regression guard: the public dashboard must render correctly when
 * canonical.enabled=true, exercising the expanded override map.
 */
class CanonicalIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('scarlet.canonical.enabled', true);

        // All VM queries return empty result — canonical reader returns null for each key,
        // which causes getBoatMetrics() to keep the registry value unchanged. This is the
        // graceful-degradation path and exactly what we need to verify doesn't 500.
        Http::fake([
            '*' => Http::response(['status' => 'success', 'data' => ['result' => []]], 200),
        ]);
    }

    public function test_public_dashboard_renders_with_canonical_enabled(): void
    {
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Public/Dashboard'));
    }

    public function test_public_dashboard_boat_metrics_keys_present_with_canonical_enabled(): void
    {
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/Dashboard')
                ->has('initialMetrics')
                ->has('initialMetrics.boat')
                ->has('initialMetrics.canonical')
            );
    }
}
