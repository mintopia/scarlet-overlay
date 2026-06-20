<!DOCTYPE html>
<html lang="en">
<head>
    <script>
        (function() {
            var t = localStorage.getItem('scarlet-theme');
            if (t === 'dark' || t === 'night') {
                document.documentElement.setAttribute('data-theme', t);
            }
        })();
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>
        window.scarletConfig = {
            reverb: {
                key: @json(config('broadcasting.connections.reverb.key')),
                host: @json(config('scarlet.reverb.host')),
                port: @json(config('scarlet.reverb.port')),
                scheme: @json(config('scarlet.reverb.scheme')),
            },
            metrics: {
                pushInterval: @json(config('scarlet.metrics.push_interval')),
            },
        };
    </script>
    @routes
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
