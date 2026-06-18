<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MetricsBackupCommandTest extends TestCase
{
    public function test_backup_creates_snapshot_and_manifest(): void
    {
        Storage::fake('local');

        Http::fake([
            '*/snapshot/create*' => Http::response(['status' => 'ok', 'snapshot' => '20260618-AABBCC']),
            '*/api/v1/export*' => Http::response("{\"metric\":{\"__name__\":\"scarlet_gps_latitude_deg\"},\"values\":[1],\"timestamps\":[1]}\n"),
        ]);

        $this->artisan('metrics:backup')->assertSuccessful();

        $manifest = collect(Storage::disk('local')->allFiles('metrics'))
            ->first(fn ($f) => str_contains($f, 'manifest'));
        $this->assertNotNull($manifest);
        $this->assertStringContainsString('20260618-AABBCC', Storage::disk('local')->get($manifest));
    }

    public function test_backup_fails_when_snapshot_rejected(): void
    {
        Storage::fake('local');
        Http::fake(['*/snapshot/create*' => Http::response('', 500)]);

        $this->artisan('metrics:backup')->assertFailed();
    }
}
