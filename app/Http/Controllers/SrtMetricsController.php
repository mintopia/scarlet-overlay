<?php

namespace App\Http\Controllers;

use App\Models\BoatSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SrtMetricsController extends Controller
{
    public function __invoke()
    {
        $url = BoatSetting::getValue('srt_stats_url', '');

        if (!$url) {
            return response("# No SRT stats URL configured\n", 200)
                ->header('Content-Type', 'text/plain; version=0.0.4; charset=utf-8');
        }

        try {
            $response = Http::timeout(5)->get($url);
            $data = $response->json();
        } catch (\Throwable $e) {
            Log::warning("SRT metrics fetch failed: {$e->getMessage()}");
            return response("# SRT stats fetch failed\nscarlet_srt_up 0\n", 200)
                ->header('Content-Type', 'text/plain; version=0.0.4; charset=utf-8');
        }

        $lines = [];
        $lines[] = '# HELP scarlet_srt_up Whether the SRT stats endpoint is reachable';
        $lines[] = '# TYPE scarlet_srt_up gauge';
        $lines[] = 'scarlet_srt_up 1';

        $publishers = $data['publishers'] ?? [];
        foreach ($publishers as $name => $pub) {
            $labels = '{publisher="' . $this->escape($name) . '"}';

            $lines[] = '# HELP scarlet_srt_publisher_connected Whether the publisher is connected';
            $lines[] = '# TYPE scarlet_srt_publisher_connected gauge';
            $lines[] = "scarlet_srt_publisher_connected{$labels} " . ($pub['connected'] ? '1' : '0');

            $lines[] = '# HELP scarlet_srt_publisher_bitrate_bps Publisher bitrate in bits per second';
            $lines[] = '# TYPE scarlet_srt_publisher_bitrate_bps gauge';
            $lines[] = "scarlet_srt_publisher_bitrate_bps{$labels} " . (($pub['bitrate'] ?? 0) * 1000);

            $lines[] = '# HELP scarlet_srt_publisher_rtt_ms Publisher round-trip time in milliseconds';
            $lines[] = '# TYPE scarlet_srt_publisher_rtt_ms gauge';
            $lines[] = "scarlet_srt_publisher_rtt_ms{$labels} " . ($pub['rtt'] ?? 0);

            $lines[] = '# HELP scarlet_srt_publisher_latency_ms Publisher latency in milliseconds';
            $lines[] = '# TYPE scarlet_srt_publisher_latency_ms gauge';
            $lines[] = "scarlet_srt_publisher_latency_ms{$labels} " . ($pub['latency'] ?? 0);

            $lines[] = '# HELP scarlet_srt_publisher_dropped_packets_total Publisher dropped packets';
            $lines[] = '# TYPE scarlet_srt_publisher_dropped_packets_total gauge';
            $lines[] = "scarlet_srt_publisher_dropped_packets_total{$labels} " . ($pub['dropped_pkts'] ?? 0);

            $lines[] = '# HELP scarlet_srt_publisher_network_bytes Publisher network bytes';
            $lines[] = '# TYPE scarlet_srt_publisher_network_bytes gauge';
            $lines[] = "scarlet_srt_publisher_network_bytes{$labels} " . ($pub['network'] ?? 0);
        }

        $consumers = $data['consumers'] ?? [];
        foreach ($consumers as $i => $con) {
            $server = $con['server'] ?? "consumer_{$i}";
            $labels = '{server="' . $this->escape($server) . '"}';

            $lines[] = '# HELP scarlet_srt_consumer_bitrate_bps Consumer bitrate in bits per second';
            $lines[] = '# TYPE scarlet_srt_consumer_bitrate_bps gauge';
            $lines[] = "scarlet_srt_consumer_bitrate_bps{$labels} " . (($con['bitrate'] ?? 0) * 1000);

            $lines[] = '# HELP scarlet_srt_consumer_rtt_ms Consumer round-trip time in milliseconds';
            $lines[] = '# TYPE scarlet_srt_consumer_rtt_ms gauge';
            $lines[] = "scarlet_srt_consumer_rtt_ms{$labels} " . ($con['rtt'] ?? 0);

            $lines[] = '# HELP scarlet_srt_consumer_latency_ms Consumer latency in milliseconds';
            $lines[] = '# TYPE scarlet_srt_consumer_latency_ms gauge';
            $lines[] = "scarlet_srt_consumer_latency_ms{$labels} " . ($con['latency'] ?? 0);

            $lines[] = '# HELP scarlet_srt_consumer_dropped_packets_total Consumer dropped packets';
            $lines[] = '# TYPE scarlet_srt_consumer_dropped_packets_total gauge';
            $lines[] = "scarlet_srt_consumer_dropped_packets_total{$labels} " . ($con['dropped_pkts'] ?? 0);
        }

        $lines[] = '';

        return response(implode("\n", $lines), 200)
            ->header('Content-Type', 'text/plain; version=0.0.4; charset=utf-8');
    }

    private function escape(string $value): string
    {
        return str_replace(['\\', '"', "\n"], ['\\\\', '\\"', '\\n'], $value);
    }
}
