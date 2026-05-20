<?php

namespace App\Console\Commands;

use App\Models\BoatSetting;
use App\Services\MediaMtxService;
use Illuminate\Console\Command;

class MediaMtxSync extends Command
{
    protected $signature = 'mediamtx:sync';

    protected $description = 'Sync the SRT URL from settings to MediaMTX';

    public function handle(MediaMtxService $mediaMtx): int
    {
        $srtUrl = BoatSetting::getValue('srt_url', '');

        if ($mediaMtx->setPathSource('live', $srtUrl)) {
            $this->info('MediaMTX synced: ' . ($srtUrl ?: '(no source)'));
            return self::SUCCESS;
        }

        $this->warn('MediaMTX sync failed — will retry on next startup');
        return self::FAILURE;
    }
}
