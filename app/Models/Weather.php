<?php
namespace App\Models;

class Weather
{
    public int $wmoCode = 0;
    public bool $daytime = true;
    public ?float $longitude = null;
    public ?float $latitude = null;
    public ?float $temp = null;
    public float $windSpeed = 0;
    public int $windDirection = 0;

    public float $waveHeight = 0;
    public int $waveDirection = 0;
    public float $wavePeriod = 0;
    public ?float $seaTemp = null;
    public float $current = 0;
    public int $currentDirection = 0;

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
