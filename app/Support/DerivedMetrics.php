<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Pure functions that compute a derived canonical metric from other canonical
 * metric values (ADR 0007). The CanonicalReader resolves a derived metric by
 * reading its declared inputs (in their display units) and passing them here.
 * Nothing is written back to VictoriaMetrics; this runs at read time.
 */
class DerivedMetrics
{
    /**
     * @param  array<string, float|null>  $inputs  keyed by the role names declared in derived_inputs
     */
    public static function compute(string $fn, array $inputs): ?float
    {
        return match ($fn) {
            'true_wind_speed' => self::trueWind($inputs)['speed'],
            'true_wind_direction' => self::trueWind($inputs)['direction'],
            default => null,
        };
    }

    /**
     * True wind speed (kn) + direction (° true, 0..360) from apparent wind, speed
     * through water, and heading. Inputs arrive in display units (kn, degrees);
     * NavigationMath works in radians, so angles are converted here.
     *
     * @param  array<string, float|null>  $i  aws (kn), awa (°), stw (kn), heading (° true)
     * @return array{speed: float|null, direction: float|null}
     */
    private static function trueWind(array $i): array
    {
        $tw = NavigationMath::calculateTrueWind(
            $i['aws'] ?? null,
            isset($i['awa']) ? deg2rad($i['awa']) : null,
            $i['stw'] ?? null,
            isset($i['heading']) ? deg2rad($i['heading']) : null,
        );

        return [
            'speed' => $tw['speed'],
            'direction' => $tw['direction'] !== null
                ? fmod(rad2deg($tw['direction']) + 360.0, 360.0)
                : null,
        ];
    }
}
