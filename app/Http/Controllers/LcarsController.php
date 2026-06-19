<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Admin\Concerns\MapsLegacyMetricKeys;
use App\Models\BoatSetting;
use App\Services\CanonicalReader;
use App\Services\MetricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The LCARS Panel: a hidden, public Star-Trek-styled console that re-presents
 * Scarlet's existing live telemetry (the same data the public voyage dashboard
 * already broadcasts). Read-only — adds no metrics, no broadcast events, no DB
 * writes. Reached via the Konami code on either dashboard. See PLAN.md / docs/adr/0001.
 */
class LcarsController extends Controller
{
    use MapsLegacyMetricKeys;

    /**
     * Per-Bridge-Station allow-list of `historical` registry keys (the only metrics
     * served by the lazy series endpoint). This is the backend half of the single
     * presentation contract; a parity test asserts it matches the front-end contract
     * and that every key is a valid canonical definition.
     *
     * @var array<string, list<string>>
     */
    public const STATION_HISTORY = [
        'msd' => ['speed_sog', 'house_battery_soc'],
        'conn' => ['speed_sog', 'speed_stw', 'vmg', 'heel'],
        'navigation' => ['depth', 'trip_log'],
        'engineering' => ['house_battery_soc', 'house_battery_voltage', 'house_battery_current', 'engine_battery_voltage', 'tracker_cpu'],
        'ops' => ['fuel_level', 'water_level', 'tracker_lte_rssi', 'tracker_wifi_rssi'],
        'science' => ['water_temp', 'cabin_pressure_forepeak'],
    ];

    /** Fixed history window for the graph-led console: 6 hours at a 60s step. */
    private const WINDOW_SECONDS = 21600;

    private const STEP = '60s';

    public function index(MetricsService $metrics): Response
    {
        return Inertia::render('Admin/Lcars', [
            'initialMetrics' => $metrics->getAllMetrics(),
            'boatName' => BoatSetting::getValue('boat_name', config('scarlet.name')),
            'registry' => BoatSetting::getValue('mmsi', config('scarlet.mmsi')),
            'reverb' => config('scarlet.reverb'),
            'reverbKey' => config('broadcasting.connections.reverb.key'),
            // Only the MSD landing view's history is preloaded; every other station's
            // history is fetched lazily on first selection (avoids an Octane fan-out).
            'msdHistory' => $this->stationSeries('msd'),
        ]);
    }

    public function series(Request $request, CanonicalReader $canonical): JsonResponse
    {
        $station = (string) $request->query('station', '');

        if (! array_key_exists($station, self::STATION_HISTORY)) {
            return response()->json(['error' => 'unknown_station'], 422);
        }

        return response()->json($this->stationSeries($station, $canonical));
    }

    /**
     * Fetch each allow-listed historical series for a station, isolating per-key
     * failures behind a stable public status code (raw errors are logged only).
     *
     * @return list<array{key: string, points: list<array{t: int|float, value: float|null}>|null, status: string}>
     */
    private function stationSeries(string $station, ?CanonicalReader $canonical = null): array
    {
        $canonical ??= app(CanonicalReader::class);

        $end = now()->timestamp;
        $start = $end - self::WINDOW_SECONDS;

        $out = [];
        foreach (self::STATION_HISTORY[$station] as $key) {
            try {
                $canonicalKey = $this->canonicalKey($key) ?? $key;
                $data = $canonical->readRange($canonicalKey, null, self::STEP, $start, $end);
                $points = array_map(
                    static fn (array $p): array => ['t' => $p['t'], 'value' => $p['v']],
                    $data,
                );

                $out[] = [
                    'key' => $key,
                    'points' => $points,
                    'status' => $points === [] ? 'empty' : 'ok',
                ];
            } catch (\Throwable $e) {
                Log::warning('LCARS series fetch failed', ['station' => $station, 'key' => $key, 'error' => $e->getMessage()]);
                $out[] = ['key' => $key, 'points' => null, 'status' => 'unavailable'];
            }
        }

        return $out;
    }
}
