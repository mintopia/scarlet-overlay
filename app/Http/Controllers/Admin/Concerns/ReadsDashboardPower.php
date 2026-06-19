<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Services\CanonicalReader;

trait ReadsDashboardPower
{
    /**
     * Compute house battery power history (voltage × current) over the given duration.
     *
     * house_battery_power is not in the catalog — derive it from voltage × current (signed by current).
     *
     * @return array<int, array{t: int, v: float}>
     */
    public function housePowerHistory(CanonicalReader $reader, string $duration = '6h'): array
    {
        $voltagePoints = $reader->readRange('house_battery_voltage', $duration);
        $currentPoints = $reader->readRange('house_battery_current', $duration);

        return $this->zipByTime($voltagePoints, $currentPoints, fn (float $v, float $a) => $v * $a);
    }

    /**
     * Compute EcoFlow net power history (input − output, positive = charging) over the given duration.
     *
     * @return array<int, array{t: int, v: float}>
     */
    public function ecoflowPowerHistory(CanonicalReader $reader, string $duration = '6h'): array
    {
        $inputPoints = $reader->readRange('ecoflow_input_watts', $duration);
        $outputPoints = $reader->readRange('ecoflow_output_watts', $duration);

        return $this->zipByTime($inputPoints, $outputPoints, fn (float $inp, float $out) => $inp - $out);
    }

    /**
     * Zip two time-series by timestamp, applying a combiner to matched points.
     *
     * @param  array<int, array{t: int, v: float}>  $seriesA
     * @param  array<int, array{t: int, v: float}>  $seriesB
     * @return array<int, array{t: int, v: float}>
     */
    private function zipByTime(array $seriesA, array $seriesB, \Closure $combine): array
    {
        if (empty($seriesA) || empty($seriesB)) {
            return [];
        }

        // Index series B by timestamp for O(n) lookup
        $bByTs = [];
        foreach ($seriesB as $point) {
            $bByTs[$point['t']] = $point['v'];
        }

        $result = [];
        foreach ($seriesA as $point) {
            if (! isset($bByTs[$point['t']])) {
                continue;
            }
            $result[] = ['t' => $point['t'], 'v' => $combine((float) $point['v'], (float) $bByTs[$point['t']])];
        }

        return $result;
    }
}
