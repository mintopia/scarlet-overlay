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

    /**
     * @return array<int, array{path: string, name: string, waypoints: array, track_points: array, distance_nm: float}>
     */
    public function storeAndParseRoute(UploadedFile $file, int $groupId): array
    {
        $filename = $groupId.'_'.time().'_'.preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName());
        $path = $file->storeAs('planner/gpx', $filename);
        $content = file_get_contents($file->getRealPath());
        $fallbackName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

        $xml = @simplexml_load_string($content);
        if ($xml === false) {
            throw new \RuntimeException('Invalid GPX file');
        }

        $xml->registerXPathNamespace('gpx', 'http://www.topografix.com/GPX/1/1');
        $routes = $this->extractRoutes($xml, $fallbackName);

        if (empty($routes)) {
            throw new \RuntimeException('No tracks or routes found in GPX file');
        }

        return array_map(fn (array $route) => array_merge($route, ['path' => $path]), $routes);
    }

    protected function extractRoutes(\SimpleXMLElement $xml, string $fallbackName): array
    {
        $routes = [];

        $tracks = $xml->xpath('//gpx:trk') ?: $xml->xpath('//trk') ?: [];
        foreach ($tracks as $trk) {
            $trk->registerXPathNamespace('gpx', 'http://www.topografix.com/GPX/1/1');
            $nameNode = $trk->xpath('gpx:name') ?: $trk->xpath('name');
            $name = ($nameNode && (string) $nameNode[0] !== '') ? (string) $nameNode[0] : $fallbackName;

            $points = [];
            $segments = $trk->xpath('gpx:trkseg/gpx:trkpt') ?: $trk->xpath('trkseg/trkpt') ?: [];
            foreach ($segments as $pt) {
                $points[] = [(float) $pt['lat'], (float) $pt['lon']];
            }

            if (empty($points)) {
                continue;
            }

            $routes[] = [
                'name' => $name,
                'track_points' => $points,
                'waypoints' => [],
                'distance_nm' => round($this->calculateDistanceNm($points), 1),
            ];
        }

        $rtes = $xml->xpath('//gpx:rte') ?: $xml->xpath('//rte') ?: [];
        foreach ($rtes as $rte) {
            $rte->registerXPathNamespace('gpx', 'http://www.topografix.com/GPX/1/1');
            $nameNode = $rte->xpath('gpx:name') ?: $rte->xpath('name');
            $name = ($nameNode && (string) $nameNode[0] !== '') ? (string) $nameNode[0] : $fallbackName;

            $waypoints = [];
            $rtepts = $rte->xpath('gpx:rtept') ?: $rte->xpath('rtept') ?: [];
            foreach ($rtepts as $pt) {
                $ptName = isset($pt->name) && (string) $pt->name !== '' ? (string) $pt->name : null;
                $waypoints[] = ['lat' => (float) $pt['lat'], 'lng' => (float) $pt['lon'], 'name' => $ptName];
            }

            if (empty($waypoints)) {
                continue;
            }

            $points = array_map(fn ($w) => [$w['lat'], $w['lng']], $waypoints);

            $routes[] = [
                'name' => $name,
                'track_points' => $points,
                'waypoints' => $waypoints,
                'distance_nm' => round($this->calculateDistanceNm($points), 1),
            ];
        }

        if (empty($routes)) {
            $name = $fallbackName;
            $waypoints = $this->parseWaypoints($xml->asXML());
            $trackPoints = $this->parseTrackPoints($xml->asXML());

            if (! empty($trackPoints) || ! empty($waypoints)) {
                $points = $trackPoints ?: array_map(fn ($w) => [$w['lat'], $w['lng']], $waypoints);
                $routes[] = [
                    'name' => $name,
                    'track_points' => $trackPoints,
                    'waypoints' => $waypoints,
                    'distance_nm' => round($this->calculateDistanceNm($points), 1),
                ];
            }
        }

        return $routes;
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
