<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Journey;
use App\Models\ShipLog;
use App\Services\MetricsService;
use Carbon\CarbonInterval;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminLogController extends Controller
{
    use BuildsLogRows;

    public function index(Request $request, MetricsService $metrics)
    {
        $activeJourney = Journey::current();
        $period = $request->input('period', $activeJourney ? 'journey' : '24h');
        $timeRanges = ['6h', '12h', '24h', '48h', '168h'];

        $journey = null;

        if (str_starts_with($period, 'journey:')) {
            $journeyId = (int) str_replace('journey:', '', $period);
            $journey = Journey::find($journeyId);
        } elseif ($period === 'journey') {
            $journey = $activeJourney;
        }

        if ($journey?->started_at) {
            $logs = ShipLog::where('journey_id', $journey->id)
                ->orderBy('recorded_at')
                ->get();
        } else {
            if (! in_array($period, $timeRanges)) {
                $period = '24h';
            }
            $seconds = CarbonInterval::fromString($period)->totalSeconds;
            $start = now()->subSeconds((int) $seconds);
            $logs = ShipLog::where('recorded_at', '>=', $start)
                ->orderBy('recorded_at')
                ->get();
        }

        $rows = $this->buildLogRows($logs);

        $journeys = Journey::whereNotNull('started_at')
            ->orderByDesc('started_at')
            ->get(['id', 'title', 'status'])
            ->map(fn (Journey $j) => [
                'id' => $j->id,
                'title' => $j->title,
                'active' => $j->status === 'active',
            ]);

        return Inertia::render('Admin/Log', [
            'rows' => $rows,
            'period' => $period,
            'journeys' => $journeys,
            'positionTimezone' => Inertia::defer(fn () => ($metrics->getWeatherData())['timezone'] ?? null),
        ]);
    }

    public function update(Request $request, ShipLog $shipLog)
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $shipLog->update([
            'notes' => $validated['notes'] ?: null,
        ]);

        return back();
    }
}
