<?php

namespace Tests\Feature;

use App\Models\BoatSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SrtMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_single_stream_payload_reports_publisher_connected(): void
    {
        BoatSetting::setValue('srt_stats_url', 'http://stats.test/stats/play_abc');

        Http::fake([
            'stats.test/*' => Http::response([
                'publisher' => [
                    'bitrate' => 2210,
                    'buffer' => 1936,
                    'dropped_pkts' => 0,
                    'latency' => 2000,
                    'rtt' => 60.746,
                    'uptime' => 280,
                ],
                'status' => 'ok',
            ]),
        ]);

        $response = $this->get(route('metrics.srt'));

        $response->assertOk();
        $body = $response->getContent();

        $this->assertStringContainsString('scarlet_srt_up 1', $body);
        $this->assertMatchesRegularExpression('/scarlet_srt_publisher_connected\{[^}]*\} 1/', $body);
        // bitrate is reported in kbps upstream and exported in bps
        $this->assertMatchesRegularExpression('/scarlet_srt_publisher_bitrate_bps\{[^}]*\} 2210000/', $body);
        $this->assertMatchesRegularExpression('/scarlet_srt_publisher_rtt_ms\{[^}]*\} 60\.746/', $body);
    }

    public function test_single_stream_payload_not_ok_reports_disconnected(): void
    {
        BoatSetting::setValue('srt_stats_url', 'http://stats.test/stats/play_abc');

        Http::fake([
            'stats.test/*' => Http::response([
                'publisher' => null,
                'status' => 'no_publisher',
            ]),
        ]);

        $response = $this->get(route('metrics.srt'));

        $response->assertOk();
        $body = $response->getContent();

        $this->assertStringContainsString('scarlet_srt_up 1', $body);
        $this->assertMatchesRegularExpression('/scarlet_srt_publisher_connected\{[^}]*\} 0/', $body);
    }

    public function test_aggregate_publishers_payload_still_supported(): void
    {
        BoatSetting::setValue('srt_stats_url', 'http://stats.test/stats');

        Http::fake([
            'stats.test/*' => Http::response([
                'publishers' => [
                    'belabox' => [
                        'connected' => true,
                        'bitrate' => 3000,
                        'rtt' => 42,
                        'latency' => 2000,
                        'dropped_pkts' => 5,
                        'network' => 4000,
                    ],
                ],
            ]),
        ]);

        $response = $this->get(route('metrics.srt'));

        $response->assertOk();
        $body = $response->getContent();

        $this->assertMatchesRegularExpression('/scarlet_srt_publisher_connected\{publisher="belabox"\} 1/', $body);
        $this->assertMatchesRegularExpression('/scarlet_srt_publisher_network_bps\{publisher="belabox"\} 4000000/', $body);
    }

    public function test_legacy_publishers_payload_derives_connected_from_status(): void
    {
        BoatSetting::setValue('srt_stats_url', 'http://stats.test/stats/play_abc?legacy=1');

        // Real ?legacy=1 shape: publishers map, no `connected` field, raw SRT field names.
        Http::fake([
            'stats.test/*' => Http::response([
                'publishers' => [
                    'live' => [
                        'bitrate' => 1884,
                        'pktRcvDrop' => 3,
                        'mbpsBandwidth' => 50.436,
                        'rtt' => 52.58,
                        'latency' => 2000,
                        'uptime' => 642,
                    ],
                ],
                'status' => 'ok',
            ]),
        ]);

        $response = $this->get(route('metrics.srt'));

        $response->assertOk();
        $body = $response->getContent();

        $this->assertMatchesRegularExpression('/scarlet_srt_publisher_connected\{publisher="live"\} 1/', $body);
        $this->assertMatchesRegularExpression('/scarlet_srt_publisher_bitrate_bps\{publisher="live"\} 1884000/', $body);
        $this->assertMatchesRegularExpression('/scarlet_srt_publisher_dropped_packets_total\{publisher="live"\} 3/', $body);
    }
}
