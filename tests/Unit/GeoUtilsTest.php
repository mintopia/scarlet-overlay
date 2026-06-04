<?php

namespace Tests\Unit;

use App\Support\GeoUtils;
use PHPUnit\Framework\TestCase;

class GeoUtilsTest extends TestCase
{
    public function test_null_island_detection(): void
    {
        $this->assertTrue(GeoUtils::isNullIsland(null, null));
        $this->assertTrue(GeoUtils::isNullIsland(0.05, 0.05));
        $this->assertFalse(GeoUtils::isNullIsland(50.0, -1.0));
    }

    public function test_haversine_known_distance(): void
    {
        $nm = GeoUtils::haversineNm(50.9097, -1.4044, 50.7622, -1.2996);
        $this->assertGreaterThan(8, $nm);
        $this->assertLessThan(12, $nm);
    }
}
