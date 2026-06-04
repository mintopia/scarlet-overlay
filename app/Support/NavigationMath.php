<?php

namespace App\Support;

class NavigationMath
{
    /**
     * Calculate true wind speed and direction using the cosine rule.
     *
     * All inputs must use consistent units: angles in radians, speeds in the same unit.
     * Returns speed in the same unit as input, direction in radians.
     *
     * @return array{speed: ?float, direction: ?float}
     */
    public static function calculateTrueWind(?float $aws, ?float $awa, ?float $stw, ?float $heading): array
    {
        if ($aws === null || $awa === null || $stw === null || $heading === null) {
            return ['speed' => null, 'direction' => null];
        }

        $tws = sqrt($aws ** 2 + $stw ** 2 - 2 * $aws * $stw * cos($awa));
        $twa = atan2($aws * sin($awa), $aws * cos($awa) - $stw);
        $twd = fmod($heading + $twa + 2 * M_PI, 2 * M_PI);

        return ['speed' => $tws, 'direction' => $twd];
    }
}
