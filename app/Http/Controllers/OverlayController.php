<?php

namespace App\Http\Controllers;

use App\Models\BoatSetting;

class OverlayController extends Controller
{
    public function index()
    {
        $srtUrl = BoatSetting::getValue('srt_url', '');

        return view('overlay', [
            'boatName' => BoatSetting::getValue('boat_name', config('scarlet.name')),
            'passageFrom' => BoatSetting::getValue('passage_from', ''),
            'passageTo' => BoatSetting::getValue('passage_to', ''),
            'portName' => BoatSetting::getValue('port_name', ''),
            'srtUrl' => $srtUrl,
            'reverb' => config('scarlet.reverb'),
            'reverbKey' => config('broadcasting.connections.reverb.key'),
            'utcOffset' => config('scarlet.time.offset'),
            'timeLabel' => config('scarlet.time.label'),
        ]);
    }
}
