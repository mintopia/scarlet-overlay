<?php

namespace Tests\Unit;

use App\Support\NavigationMath;
use PHPUnit\Framework\TestCase;

class NavigationMathTest extends TestCase
{
    public function test_calculates_true_wind_from_apparent_wind_stw_and_heading(): void
    {
        $result = NavigationMath::calculateTrueWind(8.5, 0.78, 3.1, 4.78);

        // Speed is returned in the same unit as the inputs (m/s), direction in radians.
        $this->assertEqualsWithDelta(6.662947, $result['speed'], 0.0001);
        $this->assertEqualsWithDelta(5.893347, $result['direction'], 0.0001);
    }

    public function test_true_wind_angle_opens_aft_of_apparent_on_a_reach(): void
    {
        // With the boat moving, true wind always sits further aft (wider) than apparent.
        $awa = 0.6; // apparent wind angle off the bow
        $result = NavigationMath::calculateTrueWind(4.15, $awa, 2.82, 0.0);

        // Heading is 0, so the returned direction equals the true wind angle off the bow.
        $this->assertGreaterThan($awa, $result['direction']);
        // True wind speed is lower than apparent when reaching.
        $this->assertLessThan(4.15, $result['speed']);
    }

    public function test_returns_apparent_wind_when_stationary(): void
    {
        // With no speed through water, apparent wind equals true wind.
        $result = NavigationMath::calculateTrueWind(6.0, 0.9, 0.0, 1.2);

        $this->assertEqualsWithDelta(6.0, $result['speed'], 0.0001);
        $this->assertEqualsWithDelta(fmod(1.2 + 0.9, 2 * M_PI), $result['direction'], 0.0001);
    }

    public function test_wraps_true_wind_direction_into_zero_to_two_pi(): void
    {
        $result = NavigationMath::calculateTrueWind(5.0, 0.5, 2.0, 6.0);

        $this->assertGreaterThanOrEqual(0.0, $result['direction']);
        $this->assertLessThan(2 * M_PI, $result['direction']);
    }

    public function test_returns_nulls_when_any_input_is_null(): void
    {
        $this->assertSame(['speed' => null, 'direction' => null], NavigationMath::calculateTrueWind(null, 0.78, 3.1, 4.78));
        $this->assertSame(['speed' => null, 'direction' => null], NavigationMath::calculateTrueWind(8.5, null, 3.1, 4.78));
        $this->assertSame(['speed' => null, 'direction' => null], NavigationMath::calculateTrueWind(8.5, 0.78, null, 4.78));
        $this->assertSame(['speed' => null, 'direction' => null], NavigationMath::calculateTrueWind(8.5, 0.78, 3.1, null));
    }

    public function test_distance_made_good_is_the_reduction_in_waypoint_distance(): void
    {
        // Closed 5nm toward the waypoint over a 5nm leg.
        $this->assertEqualsWithDelta(5.0, NavigationMath::distanceMadeGood(17.5, 12.5, 5.0), 0.0001);
    }

    public function test_distance_made_good_is_negative_when_sailing_away(): void
    {
        // Tacking away: waypoint distance grew, but only within the distance sailed.
        $this->assertEqualsWithDelta(-1.0, NavigationMath::distanceMadeGood(10.0, 11.0, 3.0), 0.0001);
    }

    public function test_distance_made_good_allows_gain_beyond_through_water_distance_from_current(): void
    {
        // Favourable current: 6nm made good on 4.2nm through the water is plausible, not a waypoint change.
        $this->assertEqualsWithDelta(6.0, NavigationMath::distanceMadeGood(10.0, 4.0, 4.2), 0.0001);
    }

    public function test_distance_made_good_is_null_when_waypoint_advances_to_a_farther_point(): void
    {
        // Arrival: 0.2nm -> 17.5nm over a 4.6nm leg is physically impossible for a fixed point.
        $this->assertNull(NavigationMath::distanceMadeGood(0.2, 17.5, 4.6));
    }

    public function test_distance_made_good_is_null_when_waypoint_switches_to_a_nearer_point(): void
    {
        // Route edit to a closer waypoint: 17nm -> 2nm over a 4nm leg is impossible for a fixed point.
        $this->assertNull(NavigationMath::distanceMadeGood(17.0, 2.0, 4.0));
    }

    public function test_distance_made_good_tolerates_jitter_when_stationary(): void
    {
        // At anchor with a small waypoint-distance wobble and no distance sailed.
        $this->assertEqualsWithDelta(0.2, NavigationMath::distanceMadeGood(5.0, 4.8, 0.0), 0.0001);
    }

    public function test_distance_made_good_is_null_when_either_reading_is_missing(): void
    {
        $this->assertNull(NavigationMath::distanceMadeGood(null, 12.5, 5.0));
        $this->assertNull(NavigationMath::distanceMadeGood(17.5, null, 5.0));
    }

    public function test_distance_made_good_trusts_the_reading_when_distance_sailed_is_unknown(): void
    {
        // Without a distance sailed we cannot apply the physical bound, so return the raw reduction.
        $this->assertEqualsWithDelta(2.0, NavigationMath::distanceMadeGood(10.0, 8.0, null), 0.0001);
    }
}
