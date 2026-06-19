<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OpsDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_auth(): void
    {
        $this->get(route('admin.dash.ops'))->assertRedirect(route('login'));
    }

    public function test_renders_with_contracts(): void
    {
        config()->set('scarlet.canonical.enabled', true);
        Http::fake(['*' => Http::response(['status' => 'success', 'data' => ['resultType' => 'vector', 'result' => [['metric' => [], 'value' => [now()->timestamp, '1']]]]], 200)]);

        $this->actingAs(User::factory()->create())
            ->get(route('admin.dash.ops'))
            ->assertInertia(fn (Assert $p) => $p->component('Admin/Dash/Ops')->has('contracts'));
    }

    public function test_renders_with_multi_day_forecast(): void
    {
        config()->set('scarlet.canonical.enabled', true);

        Http::fake([
            // Open-Meteo daily forecast feed.
            '*open-meteo.com*' => Http::response([
                'latitude' => 51.5,
                'longitude' => -0.13,
                'timezone' => 'Europe/London',
                'daily' => [
                    'time' => ['2026-06-19', '2026-06-20', '2026-06-21'],
                    'weather_code' => [0, 3, 61],
                    'temperature_2m_max' => [21.4, 18.2, 16.0],
                    'temperature_2m_min' => [12.1, 11.0, 10.5],
                    'wind_speed_10m_max' => [9.0, 14.0, 22.0],
                ],
            ], 200),
            // Everything else (Prometheus reads, marine, etc.) → empty.
            '*' => Http::response(['status' => 'success', 'data' => ['result' => []]], 200),
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('admin.dash.ops'))
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('Admin/Dash/Ops')
                ->has('forecast', 3)
                ->where('forecast.0.code', 0)
                ->where('forecast.0.tempMax', 21.4)
                ->where('forecast.2.tempMin', 10.5)
            );
    }

    public function test_renders_when_forecast_feed_unavailable(): void
    {
        config()->set('scarlet.canonical.enabled', true);

        Http::fake(['*' => Http::response('', 500)]);

        $this->actingAs(User::factory()->create())
            ->get(route('admin.dash.ops'))
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('Admin/Dash/Ops')
                ->has('forecast')
                ->where('forecast', [])
            );
    }
}
