<?php
namespace App\Models;

use Carbon\CarbonImmutable;

class Gps
{
    public ?CarbonImmutable $timestamp = null;
    public ?float $longitude = null;
    public ?float $latitude = null;
    public bool $valid = false;
    public int $satellites = 0;
    public int $hdop = 9999;
    public float $speed = 0;
    public float $course;
    public function __construct()
    {
    }
}
