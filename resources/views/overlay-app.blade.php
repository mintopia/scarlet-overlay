<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        html, body {
            width: 100vw; height: 100vh; overflow: hidden;
            background: oklch(0.05 0.01 40);
            font-family: 'Outfit', system-ui, sans-serif;
            font-variant-numeric: tabular-nums;
            color: oklch(0.96 0.005 70);
        }
    </style>
    <script>
        window.scarletConfig = {
            reverb: {
                key: @json(config('broadcasting.connections.reverb.key')),
                host: @json(config('scarlet.reverb.host')),
                port: @json(config('scarlet.reverb.port')),
                scheme: @json(config('scarlet.reverb.scheme')),
            },
        };
    </script>
    @vite(['resources/js/app.js'])
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
