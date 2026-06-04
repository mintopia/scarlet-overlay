<?php

namespace App\Http\Controllers;

use App\Services\OpenSeaMapService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MapTileController extends Controller
{
    public function seamap(OpenSeaMapService $mapService, int $z, int $x, int $y): StreamedResponse
    {
        $filename = $mapService->getTile($z, $x, $y);
        if ($filename === null) {
            abort(404);
        }

        return Storage::response($filename, null, [
            'Cache-Control' => 'public, max-age=604800, immutable',
        ]);
    }

    public function seamapDark(OpenSeaMapService $mapService, int $z, int $x, int $y): StreamedResponse
    {
        $filename = $mapService->getDarkTile($z, $x, $y);
        if ($filename === null) {
            abort(404);
        }

        return Storage::response($filename, null, [
            'Cache-Control' => 'public, max-age=604800, immutable',
        ]);
    }

    public function satellite(int $z, int $y, int $x): StreamedResponse
    {
        $filename = "satellite/{$z}.{$y}.{$x}.jpg";

        if (! Storage::exists($filename)) {
            if (Storage::directoryMissing('satellite')) {
                Storage::createDirectory('satellite');
            }

            $url = "https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{$z}/{$y}/{$x}";
            $response = Http::withHeader('User-Agent', 'Scarlet Sailing Map Overlay; jess@mintopia.net')
                ->get($url);

            if ($response->failed()) {
                abort(404);
            }

            Storage::put($filename, $response->body());
        }

        return Storage::response($filename, null, [
            'Cache-Control' => 'public, max-age=604800, immutable',
            'Content-Type' => 'image/jpeg',
        ]);
    }
}
