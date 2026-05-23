<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Journey;
use App\Services\MetricsService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminLogController extends Controller
{
    public function index(Request $request, MetricsService $metrics)
    {
        $journey = Journey::current();
        $period = $request->input('period', $journey ? 'journey' : '24h');
        $allowed = ['journey', '6h', '12h', '24h', '48h', '168h'];
        if (!in_array($period, $allowed)) {
            $period = '24h';
        }

        if ($period === 'journey' && $journey?->started_at) {
            $rows = $metrics->getLogData(
                null,
                '3600',
                $journey->started_at->timestamp,
                now()->timestamp,
            );
            $tripOffset = $rows[0]['trip_log'] ?? 0;
            foreach ($rows as &$row) {
                if ($row['trip_log'] !== null) {
                    $row['trip_log'] -= $tripOffset;
                }
            }
            unset($row);
        } else {
            if ($period === 'journey') {
                $period = '24h';
            }
            $rows = $metrics->getLogData($period, '3600');
            $tripOffset = null;
        }

        return Inertia::render('Admin/Log', [
            'rows' => $rows,
            'period' => $period,
            'hasActiveJourney' => $journey !== null,
            'journeyTitle' => $journey?->title,
        ]);
    }
}
