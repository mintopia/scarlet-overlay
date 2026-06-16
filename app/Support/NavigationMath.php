<?php

namespace App\Support;

class NavigationMath
{
    /**
     * Slack allowed between distance made good and distance sailed before a leg
     * is treated as a waypoint change. Distance sailed is measured through the
     * water, so favourable current can make the over-ground gain exceed it.
     */
    private const DMG_DISTANCE_TOLERANCE = 1.5;

    /**
     * Absolute floor (nm) added to the bound so small waypoint-distance jitter
     * over a near-stationary leg is not mistaken for a waypoint change.
     */
    private const DMG_DISTANCE_FLOOR = 0.5;

    /**
     * Distance made good toward the active waypoint over one log interval.
     *
     * Returns the reduction in waypoint distance (prev - curr), or null when it
     * cannot be trusted: either reading is missing, or the change exceeds what
     * the boat could plausibly have made good over the distance sailed. The
     * latter means the active waypoint switched between readings (arrival or
     * route edit), so the two distances measure different points and the
     * subtraction is meaningless.
     *
     * @param  float|null  $prevWpDistance  Distance to the waypoint at the start of the leg (nm)
     * @param  float|null  $currWpDistance  Distance to the waypoint at the end of the leg (nm)
     * @param  float|null  $distanceSailed  Distance travelled over the leg (nm); null skips the bound
     */
    public static function distanceMadeGood(?float $prevWpDistance, ?float $currWpDistance, ?float $distanceSailed): ?float
    {
        if ($prevWpDistance === null || $currWpDistance === null) {
            return null;
        }

        $dmg = $prevWpDistance - $currWpDistance;

        if ($distanceSailed !== null
            && abs($dmg) > $distanceSailed * self::DMG_DISTANCE_TOLERANCE + self::DMG_DISTANCE_FLOOR) {
            return null;
        }

        return $dmg;
    }

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
