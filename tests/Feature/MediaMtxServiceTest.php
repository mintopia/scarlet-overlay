<?php

namespace Tests\Feature;

use App\Services\MediaMtxService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MediaMtxServiceTest extends TestCase
{
    public function test_live_source_is_configured_persistent_not_on_demand(): void
    {
        Http::fake(['*' => Http::response('', 200)]);

        $result = app(MediaMtxService::class)
            ->setPathSource('live', 'srt://eu.srt.belabox.net:4001?streamid=abc');

        $this->assertTrue($result);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return str_contains($request->url(), '/v3/config/paths/patch/live')
                && ($body['source'] ?? null) === 'srt://eu.srt.belabox.net:4001?streamid=abc'
                && ($body['sourceOnDemand'] ?? null) === false
                && ! array_key_exists('sourceOnDemandStartTimeout', $body);
        });
    }

    public function test_empty_source_clears_path_without_on_demand_keys(): void
    {
        Http::fake(['*' => Http::response('', 200)]);

        app(MediaMtxService::class)->setPathSource('live', '');

        Http::assertSent(function ($request) {
            $body = $request->data();

            return ($body['source'] ?? null) === ''
                && ! array_key_exists('sourceOnDemand', $body);
        });
    }
}
