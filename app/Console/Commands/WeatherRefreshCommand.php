<?php

namespace App\Console\Commands;

use App\Services\WeatherService;
use Illuminate\Console\Command;

class WeatherRefreshCommand extends Command
{
    protected $signature = 'weather:refresh';

    protected $description = 'Fetch the latest OpenMeteo current + marine weather and warm the cache for VM scrape.';

    public function handle(WeatherService $weather): int
    {
        $weather->getWeather(force: true);
        $weather->getWeatherForHome(force: true);
        $this->info('Weather refreshed.');

        return self::SUCCESS;
    }
}
