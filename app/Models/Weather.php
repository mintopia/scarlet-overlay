<?php

namespace App\Models;

class Weather
{
    public int $wmoCode = 0;

    public bool $daytime = true;

    public ?float $longitude = null;

    public ?float $latitude = null;

    public ?float $temp = null;

    public ?float $windSpeed = null;

    public ?float $windGusts = null;

    public ?int $windDirection = null;

    public ?float $pressure = null;

    public ?float $waveHeight = null;

    public ?int $waveDirection = null;

    public ?float $wavePeriod = null;

    public ?float $seaTemp = null;

    public ?float $current = null;

    public ?int $currentDirection = null;

    public string $timezone = 'UTC';

    public array $forecast = [];

    public function getConditionText(): string
    {
        return match ($this->wmoCode) {
            0 => 'Clear sky',
            1 => 'Mainly clear',
            2 => 'Partly cloudy',
            3 => 'Overcast',
            45, 48 => 'Fog',
            51, 53, 55 => 'Drizzle',
            56, 57 => 'Freezing drizzle',
            61, 63, 65 => 'Rain',
            66, 67 => 'Freezing rain',
            71, 73, 75 => 'Snow',
            77 => 'Snow grains',
            80, 81, 82 => 'Showers',
            85, 86 => 'Snow showers',
            95, 96, 99 => 'Thunderstorm',
            default => 'Unknown',
        };
    }

    public function getWeatherSummary(): string
    {
        switch ($this->wmoCode) {
            case 0:
            case 1:
                if ($this->daytime) {
                    return 'day-sunny';
                }

                return 'night-clear';

            case 2:
                return 'cloud';
            case 3:
                return 'cloudy';
            case 45:
            case 48:
                return 'fog';
            case 51:
            case 53:
            case 55:
            case 56:
            case 57:
                return 'sprinkle';
            case 61:
            case 63:
            case 65:
            case 66:
            case 67:
                return 'rain';
            case 71:
            case 73:
            case 75:
            case 77:
            case 85:
            case 86:
                return 'snow';
            case 80:
            case 81:
            case 82:
                return 'showers';
            case 95:
            case 96:
            case 99:
                return 'thunderstorm';
            default:
                return 'na';
        }
    }
}
