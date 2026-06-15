<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MediaMtxService
{
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('scarlet.mediamtx.api_url', 'http://mediamtx:9997'), '/');
    }

    public function setPathSource(string $path, string $source): bool
    {
        $config = ['source' => $source];

        if ($source !== '') {
            // Keep the SRT pull connected continuously rather than on demand. The
            // BELABOX relay can take 15s+ to establish the SRT session and fill its
            // latency buffer — longer than any reasonable on-demand start timeout —
            // so on-demand mode timed out the first viewer and re-paid that startup
            // cost on every reconnect. A persistent source stays primed so viewers
            // attach instantly.
            $config['sourceOnDemand'] = false;
        }

        try {
            $response = Http::timeout(5)
                ->patch("{$this->baseUrl}/v3/config/paths/patch/{$path}", $config);

            if ($response->status() === 404) {
                $response = Http::timeout(5)
                    ->post("{$this->baseUrl}/v3/config/paths/add/{$path}", $config);
            }

            if ($response->successful()) {
                Log::info("MediaMTX path '{$path}' source updated", ['source' => $source ?: '(empty)']);

                return true;
            }

            Log::warning('MediaMTX API error', ['status' => $response->status(), 'body' => $response->body()]);

            return false;
        } catch (\Exception $e) {
            Log::warning("MediaMTX API unreachable: {$e->getMessage()}");

            return false;
        }
    }
}
