<?php

namespace App\Services;

use App\Models\BoatSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MediaMtxService
{
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('scarlet.mediamtx.api_url', 'http://mediamtx:9997'), '/');
    }

    /**
     * Apply the configured SRT URL to the `live` path, honouring the manual
     * pull toggle. The pull only runs when `srt_pull_enabled` is set; otherwise
     * the source is cleared so MediaMTX holds no connection. Used by the sync
     * command, the settings save, and the Broadcast start/stop control so they
     * all resolve the source identically.
     */
    public function syncLiveSource(): bool
    {
        $url = BoatSetting::getValue('srt_url', '');
        $enabled = BoatSetting::getValue('srt_pull_enabled') === '1';

        return $this->setPathSource('live', $enabled ? $url : '');
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
