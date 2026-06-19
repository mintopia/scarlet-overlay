<?php

namespace Tests\Feature\Admin;

use App\Models\BoatSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BroadcastPullTest extends TestCase
{
    use RefreshDatabase;

    public function test_enabling_pull_stores_flag_and_starts_source(): void
    {
        Http::fake(['*' => Http::response('', 200)]);
        BoatSetting::setValue('srt_url', 'srt://relay:4001?streamid=abc');

        $this->actingAs(User::factory()->create())
            ->post(route('admin.broadcast.pull'), ['enabled' => true])
            ->assertRedirect();

        $this->assertSame('1', BoatSetting::getValue('srt_pull_enabled'));
        Http::assertSent(fn ($request) => ($request->data()['source'] ?? null) === 'srt://relay:4001?streamid=abc');
    }

    public function test_disabling_pull_clears_source(): void
    {
        Http::fake(['*' => Http::response('', 200)]);
        BoatSetting::setValue('srt_url', 'srt://relay:4001?streamid=abc');
        BoatSetting::setValue('srt_pull_enabled', '1');

        $this->actingAs(User::factory()->create())
            ->post(route('admin.broadcast.pull'), ['enabled' => false])
            ->assertRedirect();

        $this->assertSame('0', BoatSetting::getValue('srt_pull_enabled'));
        Http::assertSent(fn ($request) => ($request->data()['source'] ?? null) === '');
    }

    public function test_enabling_pull_without_url_is_rejected_and_does_not_call_api(): void
    {
        Http::fake(['*' => Http::response('', 200)]);

        $this->actingAs(User::factory()->create())
            ->post(route('admin.broadcast.pull'), ['enabled' => true])
            ->assertSessionHasErrors();

        $this->assertNotSame('1', BoatSetting::getValue('srt_pull_enabled'));
        Http::assertNothingSent();
    }

    public function test_pull_endpoint_requires_authentication(): void
    {
        $this->post(route('admin.broadcast.pull'), ['enabled' => true])
            ->assertRedirect(route('login'));
    }

    public function test_force_reload_endpoint_requires_authentication(): void
    {
        $this->post(route('admin.settings.force-reload'))
            ->assertRedirect(route('login'));
    }
}
