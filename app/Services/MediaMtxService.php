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
            $config['sourceOnDemand'] = true;
            $config['sourceOnDemandStartTimeout'] = '10s';
            $config['sourceOnDemandCloseAfter'] = '10s';
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

            Log::warning("MediaMTX API error", ['status' => $response->status(), 'body' => $response->body()]);
            return false;
        } catch (\Exception $e) {
            Log::warning("MediaMTX API unreachable: {$e->getMessage()}");
            return false;
        }
    }
}
