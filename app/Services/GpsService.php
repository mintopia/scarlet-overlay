<?php
namespace App\Services;

use App\Models\Gps;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GpsService
{
    public function getLocation(bool $force = false): Gps
    {
        if ($force) {
            return $this->fetchLocation();
        }
        return Cache::get('gps.location', function() {
            return $this->fetchLocation();
        });
    }

    protected function fetchLocation(): Gps
    {
        Log::debug("Fetching GPS location");
        $baseuri = config('scarlet.gps.endpoint');
        $device = config('scarlet.gps.device');
        $uri = "{$baseuri}/plugins/telemetry/DEVICE/{$device}/values/timeseries";
        $authToken = $this->getAuthToken();
        $response = Http::withHeader('X-Authorization', "Bearer {$authToken}")->get($uri);
        if (!$response->ok()) {
            throw new \RuntimeException("Received {$response->status()} {$response->body()} from API");
        }
        $json = $response->json();
        $gps = new Gps;
        $gps->longitude = (float)$json['longitude'][0]['value'] ?? null;
        $gps->latitude = (float)$json['latitude'][0]['value'] ?? null;
        $gps->valid = (bool)$json['valid'][0]['value'] ?? false;
        $gps->course = (float)$json['course'][0]['value'] ?? 0;
        $gps->speed = (float)$json['speed'][0]['value'] ?? 0;
        $gps->hdop = (int)$json['hdop'][0]['value'] ?? 9999;
        $gps->satellites = (int)$json['satellites'][0]['value'] ?? 0;

        $age = 0;
        if (isset($json['age'][0])) {
            $age = $json['age'][0]['ts'] + ($json['age'][0]['value'] * 1000);
        }
        $gps->timestamp = CarbonImmutable::createFromTimestampMsUTC($age);

        Cache::put('gps.location', $gps, 10);
        Log::debug("GPS location obtained");
        return $gps;
    }

    protected function getAuthToken(): string
    {
        return Cache::get('gps.accesstoken', function () {
            return $this->fetchAccessToken();
        });
    }

    protected function fetchAccessToken(): string
    {
        Log::debug("Fetching new access token");
        $baseuri = config('scarlet.gps.endpoint');
        $response = Http::post("{$baseuri}/auth/login", [
            'username' => config('scarlet.gps.username'),
            'password' => config('scarlet.gps.password'),
        ])->json();
        [, $body] = explode('.', $response['token']);
        $claims = json_decode(base64_decode($body));
        $expiry = CarbonImmutable::createFromTimestamp($claims->exp);
        Cache::put('gps.accesstoken', $response['token'], $expiry->subMinutes(5));
        Log::debug("Access token fetched for {$claims->sub} expires at " . $expiry->toIso8601String());
        return $response['token'];
    }
}
