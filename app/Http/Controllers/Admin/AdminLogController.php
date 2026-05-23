<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\MetricsService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminLogController extends Controller
{
    public function index(Request $request, MetricsService $metrics)
    {
        $period = $request->input('period', '24h');
        $allowed = ['6h', '12h', '24h', '48h', '168h'];
        if (!in_array($period, $allowed)) {
            $period = '24h';
        }

        return Inertia::render('Admin/Log', [
            'rows' => $metrics->getLogData($period, '3600'),
            'period' => $period,
        ]);
    }
}
