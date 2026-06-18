<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class MetricsBackupCommand extends Command
{
    protected $signature = 'metrics:backup {--prefix=scarlet} {--match=}';

    protected $description = 'Create a VictoriaMetrics snapshot and export scarlet_* series before any normalisation';

    public function handle(): int
    {
        $baseUrl = config('scarlet.metrics.prometheus_url');
        $match = (string) ($this->option('match') ?: '{__name__=~"'.$this->option('prefix').'_.*"}');
        $stamp = now()->format('Ymd-His');

        $snapshot = Http::timeout(60)->post("{$baseUrl}/snapshot/create");
        if (! $snapshot->ok() || $snapshot->json('status') !== 'ok') {
            $this->error('Snapshot creation failed.');

            return self::FAILURE;
        }
        $snapshotName = (string) $snapshot->json('snapshot');

        $export = Http::timeout(300)->get("{$baseUrl}/api/v1/export", ['match[]' => $match]);
        if (! $export->ok()) {
            $this->error('Export failed.');

            return self::FAILURE;
        }
        $body = $export->body();
        $lineCount = substr_count($body, "\n");

        Storage::disk('local')->put("metrics/backup-{$stamp}/export.jsonl", $body);

        $manifest = [
            'created_at' => now()->toIso8601String(),
            'snapshot' => $snapshotName,
            'match' => $match,
            'export_series_lines' => $lineCount,
            'export_bytes' => strlen($body),
        ];
        Storage::disk('local')->put("metrics/backup-{$stamp}/manifest.json", json_encode($manifest, JSON_PRETTY_PRINT));

        $this->info("Backup complete: snapshot {$snapshotName}, {$lineCount} series exported.");
        $this->warn('Restore-verify into a disposable VM per docs/data-layer/backup-runbook.md before any prune.');

        return self::SUCCESS;
    }
}
