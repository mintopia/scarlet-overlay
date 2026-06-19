<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CanonicalMetric;
use App\Services\CanonicalReader;
use Illuminate\Http\JsonResponse;

class DataController extends Controller
{
    public function current(CanonicalReader $reader): JsonResponse
    {
        $metrics = CanonicalMetric::where('enabled', true)->get(['key', 'display_unit']);
        $envelopes = $reader->readMany($metrics->pluck('key')->all());

        $out = [];
        foreach ($metrics as $metric) {
            $envelope = $envelopes[$metric->key] ?? null;
            $out[$metric->key] = $envelope === null ? null : [
                'value' => $envelope['value'],
                'unit' => $metric->display_unit,
                'age_s' => $envelope['age'],
                'stale' => $envelope['stale'],
            ];
        }

        return response()->json($out);
    }
}
