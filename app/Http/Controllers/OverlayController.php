<?php

namespace App\Http\Controllers;

use App\Models\BoatSetting;

class OverlayController extends Controller
{
    public function index()
    {
        return view('overlay', [
            'boatName' => BoatSetting::getValue('boat_name', config('scarlet.name')),
            'passageFrom' => BoatSetting::getValue('passage_from', ''),
            'passageTo' => BoatSetting::getValue('passage_to', ''),
            'portName' => BoatSetting::getValue('port_name', ''),
            'reverb' => config('scarlet.reverb'),
            'reverbKey' => config('broadcasting.connections.reverb.key'),
            'utcOffset' => config('scarlet.time.offset'),
            'timeLabel' => config('scarlet.time.label'),
        ]);
    }
}
