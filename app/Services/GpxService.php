<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;

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

            if (! $routes) {
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

    public function storeAndParse(UploadedFile $file, int $journeyId): array
    {
        $path = $file->storeAs('journeys/gpx', $journeyId.'.gpx');
        $content = file_get_contents($file->getRealPath());
        $waypoints = $this->parseWaypoints($content);

        return [
            'path' => $path,
            'waypoints' => $waypoints,
        ];
    }

    public function parseTrackPoints(string $gpxContent): array
    {
        $previousErrorReporting = libxml_use_internal_errors(true);

        try {
            $xml = simplexml_load_string($gpxContent);
            if ($xml === false) {
                return [];
            }

            $points = [];

            $xml->registerXPathNamespace('gpx', 'http://www.topografix.com/GPX/1/1');
            $trackPoints = $xml->xpath('//gpx:trk/gpx:trkseg/gpx:trkpt') ?: $xml->xpath('//trk/trkseg/trkpt');

            if (! $trackPoints) {
                $routePoints = $xml->xpath('//gpx:rte/gpx:rtept') ?: $xml->xpath('//rte/rtept');
                if ($routePoints) {
                    foreach ($routePoints as $point) {
                        $points[] = [(float) $point['lat'], (float) $point['lon']];
                    }
                }

                return $points;
            }

            foreach ($trackPoints as $point) {
                $points[] = [(float) $point['lat'], (float) $point['lon']];
            }

            return $points;
        } finally {
            libxml_use_internal_errors($previousErrorReporting);
        }
    }

    public function storeAndParseRoute(UploadedFile $file, int $groupId): array
    {
        $filename = $groupId.'_'.time().'_'.preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName());
        $path = $file->storeAs('planner/gpx', $filename);
        $content = file_get_contents($file->getRealPath());

        $waypoints = $this->parseWaypoints($content);
        $trackPoints = $this->parseTrackPoints($content);

        $name = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $xml = @simplexml_load_string($content);
        if ($xml !== false) {
            $xml->registerXPathNamespace('gpx', 'http://www.topografix.com/GPX/1/1');
            $trackName = $xml->xpath('//gpx:trk/gpx:name') ?: $xml->xpath('//trk/name');
            if ($trackName && (string) $trackName[0] !== '') {
                $name = (string) $trackName[0];
            }
            $routeName = $xml->xpath('//gpx:rte/gpx:name') ?: $xml->xpath('//rte/name');
            if ($routeName && (string) $routeName[0] !== '' && $name === pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) {
                $name = (string) $routeName[0];
            }
        }

        $distance = $this->calculateDistanceNm($trackPoints ?: array_map(fn ($w) => [$w['lat'], $w['lng']], $waypoints));

        return [
            'path' => $path,
            'name' => $name,
            'waypoints' => $waypoints,
            'track_points' => $trackPoints,
            'distance_nm' => round($distance, 1),
        ];
    }

    protected function calculateDistanceNm(array $points): float
    {
        if (count($points) < 2) {
            return 0;
        }

        $total = 0;
        for ($i = 1; $i < count($points); $i++) {
            $total += $this->haversineNm($points[$i - 1][0], $points[$i - 1][1], $points[$i][0], $points[$i][1]);
        }

        return $total;
    }

    protected function haversineNm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $r = 3440.065;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
