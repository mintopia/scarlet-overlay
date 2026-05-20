<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=1920">
    <title>Scarlet Overlay</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9/dist/leaflet.js"></script>
    @vite(['resources/css/overlay.css', 'resources/js/overlay.js'])
    <script>
        window.scarletConfig = {
            utcOffset: @json($utcOffset),
            timeLabel: @json($timeLabel),
            boatName: @json($boatName),
            passageFrom: @json($passageFrom),
            passageTo: @json($passageTo),
            portName: @json($portName),
            reverb: {
                key: @json($reverbKey),
                host: @json($reverb['host']),
                port: @json($reverb['port']),
                scheme: @json($reverb['scheme']),
            },
            tileUrl: '/openseamap/{z}/{x}/{y}',
        };
    </script>
</head>
<body>
    <div id="overlay" data-state="loading">

        {{-- Video feed: full-screen, behind all chrome --}}
        <video id="video-feed" autoplay muted playsinline></video>

        {{-- PiP map: top-left, visible in video-live state only --}}
        <div id="map-pip"></div>

        {{-- Full-screen map: visible in no-video, offline, port states --}}
        <div id="map-full"></div>

        {{-- LIVE / OFFLINE badge: top-left (shifts right of PiP in video-live) --}}
        <div id="live-badge">
            <div class="live-scarlet">
                <span class="live-dot"></span>
                <span class="live-text">LIVE</span>
            </div>
            <span id="live-extension" class="live-ext"></span>
        </div>

        {{-- Weather pills: top-right --}}
        <div id="weather-strip">
            <div class="weather-pill weather-pill--hero" id="weather-air">
                <div class="wx-label">Air</div>
                <div class="wx-val" id="wx-air-val">--&deg;</div>
            </div>
            <div class="weather-pill" id="weather-sea">
                <div class="wx-label">Sea</div>
                <div class="wx-val" id="wx-sea-val">--&deg;</div>
            </div>
            <div class="weather-pill" id="weather-wind">
                <div class="wx-label">Wind</div>
                <div class="wx-val" id="wx-wind-val">-- kn</div>
                <div class="wx-sub" id="wx-wind-dir"></div>
            </div>
            <div class="weather-pill" id="weather-waves">
                <div class="wx-label">Waves</div>
                <div class="wx-val" id="wx-waves-val">-- m</div>
                <div class="wx-sub" id="wx-waves-period"></div>
            </div>
        </div>

        {{-- Coordinate badge: bottom-left of map area, above lower third --}}
        <div id="coord-badge">--&deg;N &ensp; --&deg;W</div>

        {{-- Speed legend (decorative) --}}
        <div id="speed-legend">
            <div class="speed-legend-track"></div>
            <div class="speed-legend-labels">
                <span>0 kn</span>
                <span>5 kn</span>
                <span>10 kn</span>
            </div>
        </div>

        {{-- Offline card: centered on map in offline state --}}
        <div id="offline-card">
            <div class="offline-card-inner">
                <p class="offline-title">Telemetry Unavailable</p>
                <p id="offline-last-update" class="offline-time">Last update received &mdash;</p>
            </div>
        </div>

        {{-- Lower third: single unified bar at bottom --}}
        <div id="lower-third">
            <div class="lt-brand">
                <span class="lt-name">{{ $boatName }}</span>
            </div>
            <div class="lt-body">
                {{-- Passage metrics strip (hidden in port state) --}}
                <div id="metrics-strip">
                    <div class="lt-metric" id="metric-speed">
                        <span class="lt-metric-label">Spd</span>
                        <span class="lt-metric-value">--</span>
                    </div>
                    <div class="lt-metric" id="metric-heading">
                        <span class="lt-metric-label">Hdg</span>
                        <span class="lt-metric-value">--</span>
                    </div>
                    <div class="lt-metric" id="metric-depth">
                        <span class="lt-metric-label">Dpt</span>
                        <span class="lt-metric-value">--</span>
                    </div>
                    <div class="lt-sep"></div>
                </div>

                <div id="status-pill" class="lt-status lt-status--sail">--</div>

                <div class="lt-sep"></div>

                {{-- Passage info (hidden in port state) --}}
                <div id="passage" class="lt-passage">
                    @if($passageFrom && $passageTo)
                        <strong>{{ $passageFrom }}</strong> &rarr; <strong>{{ $passageTo }}</strong>
                    @endif
                </div>

                {{-- Port info (visible only in port state) --}}
                <div id="port-info" class="lt-port">
                    @if($portName)
                        <span class="lt-port-label">Currently at</span> {{ $portName }}
                    @endif
                </div>

                <div class="lt-spacer"></div>

                <div id="clock" class="lt-clock">
                    <div class="lt-time" id="clock-time">--:--</div>
                    <div class="lt-date" id="clock-date"></div>
                </div>
            </div>
        </div>

    </div>
</body>
</html>
