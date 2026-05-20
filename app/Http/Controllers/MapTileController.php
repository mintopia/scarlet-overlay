<?php

namespace App\Http\Controllers;

use App\Services\OpenSeaMapService;
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
}
