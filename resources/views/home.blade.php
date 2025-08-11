<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="refresh" content="28800">
    <meta name="viewport" content="width=device-width, initial-scale=0.4, user-scalable=no">
    <meta charset="UTF-8">
    <title>Scarlet Overlay</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600&family=Share+Tech&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.30.1/moment.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <link href="https://fonts.cdnfonts.com/css/monofonto" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/weather-icons/2.0.12/css/weather-icons.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/weather-icons/2.0.12/css/weather-icons-wind.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tabler-icons/3.34.1/tabler-icons.min.css" />
    @vite('resources/css/app.css')
    <script>
        window.scarletConfig = {
            utcOffset: {{ config('scarlet.time.offset') }},
        };
    </script>
</head>
<body>
<!-- HTML goes here, update it to be what you need! -->
<div id="container">
    <div id="video"></div>
    <div id="border1"></div>
    <div id="sidebar">
        <div class="label">Latitude</div>
        <div id="lat"></div>

        <div class="label">Longitude</div>
        <span id="long"></span>

        <div class="label">Speed</div>
        <div id="speed"></div>

        <div class="label">Heading</div>
        <div id="heading"></div>
    </div>
    <div id="compass-bg"></div>
    <div id="pointer"></div>
    <div id="footer">
        <div id="time"></div>
        <div id="timezone">{{ config('scarlet.time.label') }}</div>
        <div id="date"></div>
        <div id="text">
            <div id="title">{{ config('scarlet.name') }}</div>
            <div id="journey">{{ config('scarlet.passage') }}</div>
            <div id="mmsi">MMSI: {{ config('scarlet.mmsi') }}</div>
        </div>
        <div id="weather">
            <span class="label">Weather</span>
            <br />
            <span id="weather-summary">
                <i class="wi wi-day-sunny"></i>
            </span>
            <span id="air-temperature">

            </span>
            <br />
            <span id="wind-direction">
                <i class="wi wi-wind from-23-deg"></i>
            </span>
            <span id="wind-speed"></span>
        </div>

        <div id="ocean">
            <div class="label">Sea</div>
            <span class="footer-icon">
                <i class="wi wi-thermometer"></i>
            </span>
            <span id="sea-temperature"></span>
            <br />
            <div class="waves">
                <i class="ti ti-ripple"></i>
                <span id="wave-height"></span>,
                <span id="wave-period"></span>
            </div>
        </div>
    </div>
</div>
@vite('resources/js/app.js')
</body>
</html>
