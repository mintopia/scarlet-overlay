<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BoatSetting;
use App\Services\MediaMtxService;
use Illuminate\Http\Request;

class StreamMonitorController extends Controller
{
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
