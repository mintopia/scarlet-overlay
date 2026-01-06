<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="refresh" content="28800">
    <meta name="viewport" content="width=device-width, initial-scale=0.4, user-scalable=no">
    <meta charset="UTF-8">
    <title>Snow Overlay</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600&family=Share+Tech&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.30.1/moment.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <link href="https://fonts.cdnfonts.com/css/monofonto" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/weather-icons/2.0.12/css/weather-icons.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/weather-icons/2.0.12/css/weather-icons-wind.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tabler-icons/3.34.1/tabler-icons.min.css" />
    @vite('resources/css/snow.css')
    <script>
        window.scarletConfig = {
            utcOffset: 0,
        };
    </script>
</head>
<body>
<!-- HTML goes here, update it to be what you need! -->
<div id="container">
    <div id="video"></div>
    <div id="border1"></div>
    <div id="footer">
        <div id="time"></div>
        <div id="date"></div>
        <div id="weather">
            <span id="weather-summary">
                <i class="wi wi-day-sunny"></i>
            </span>
            <span id="air-temperature">

            </span>
            <span id="location">
                {{ config('scarlet.home.name') }}
            </span>
        </div>
    </div>
</div>
@vite('resources/js/snow.js')
</body>
</html>
