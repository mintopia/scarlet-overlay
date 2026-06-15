<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BoatSetting;
use App\Services\MediaMtxService;
use App\Services\PrometheusService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StreamMonitorController extends Controller
{
    public function index(PrometheusService $prometheus)
    {
        $statsUrl = BoatSetting::getValue('srt_stats_url', '');

        $currentResult = $prometheus->queryMultipleWithStatus([
            'connected' => 'scarlet_srt_publisher_connected',
            'bitrate' => 'scarlet_srt_publisher_bitrate_bps',
            'rtt' => 'scarlet_srt_publisher_rtt_ms',
            'latency' => 'scarlet_srt_publisher_latency_ms',
            'dropped_pkts' => 'scarlet_srt_publisher_dropped_packets_total',
            'network' => 'scarlet_srt_publisher_network_bps',
        ]);

        return Inertia::render('Admin/Broadcast', [
            'statsUrl' => $statsUrl,
            'fetchError' => $currentResult['fetchError'],
            'publisher' => $currentResult['values'],
            'pullEnabled' => BoatSetting::getValue('srt_pull_enabled') === '1',
            'srtConfigured' => BoatSetting::getValue('srt_url', '') !== '',
            'bitrateHistory' => $prometheus->queryRange('scarlet_srt_publisher_bitrate_bps / 1000000', '1h', '15s'),
            'rttHistory' => $prometheus->queryRange('scarlet_srt_publisher_rtt_ms', '1h', '15s'),
            'droppedHistory' => $prometheus->queryRange('increase(scarlet_srt_publisher_dropped_packets_total[30s])', '1h', '15s'),
            'hlsUrl' => config('scarlet.mediamtx.api_url') ? str_replace('/v3', '', config('scarlet.mediamtx.api_url')).'/scarlet/index.m3u8' : null,
        ]);
    }

    public function updatePull(Request $request, MediaMtxService $mediaMtx)
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        if ($validated['enabled'] && BoatSetting::getValue('srt_url', '') === '') {
            return back()->withErrors(['enabled' => 'Set an SRT URL in stream settings before starting the pull.']);
        }

        BoatSetting::setValue('srt_pull_enabled', $validated['enabled'] ? '1' : '0');
        $mediaMtx->syncLiveSource();

        return back()->with('success', $validated['enabled'] ? 'Stream pull started.' : 'Stream pull stopped.');
    }
}
