<?php

namespace App\Services;

class GpxService
{
    public function parseWaypoints(string $gpxContent): array
    {
        $previousErrorReporting = libxml_use_internal_errors(true);

        try {
            $xml = simplexml_load_string($gpxContent);
            if ($xml === false) {
                return [];
            }

            $waypoints = [];

            $xml->registerXPathNamespace('gpx', 'http://www.topografix.com/GPX/1/1');
            $routes = $xml->xpath('//gpx:rte/gpx:rtept') ?: $xml->xpath('//rte/rtept');

            if (!$routes) {
                return [];
            }

            foreach ($routes as $point) {
                $lat = (float) $point['lat'];
                $lng = (float) $point['lon'];
                $name = isset($point->name) && (string) $point->name !== '' ? (string) $point->name : null;

                $waypoints[] = [
                    'lat' => $lat,
                    'lng' => $lng,
                    'name' => $name,
                ];
            }

            return $waypoints;
        } finally {
            libxml_use_internal_errors($previousErrorReporting);
        }
    }

    public function storeAndParse(\Illuminate\Http\UploadedFile $file, int $journeyId): array
    {
        $path = $file->storeAs('journeys/gpx', $journeyId . '.gpx');
        $content = file_get_contents($file->getRealPath());
        $waypoints = $this->parseWaypoints($content);

        return [
            'path' => $path,
            'waypoints' => $waypoints,
        ];
    }
}
