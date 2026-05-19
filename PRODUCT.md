# Scarlet Overlay

## Product Purpose
Real-time sailing stream overlay and dashboard for the yacht Scarlet. Displays live telemetry from onboard instruments (SignalK, GPS, ESP32) as a stream overlay for OBS/streaming, plus admin and public dashboards for monitoring and sharing the voyage.

## Register
product

## Users
- **Stream viewers**: watching a live sailing stream, see the overlay composited on video. Want position, speed, weather at a glance. Passive consumption.
- **Boat crew (admin)**: Jessica and crew aboard Scarlet. Configuring the overlay, monitoring system health, checking telemetry status. Need quick access, often on mobile with intermittent connectivity.
- **Interested public**: friends, family, sailing enthusiasts following the voyage. Checking in on position, progress, conditions. Casual browsing.

## Brand & Tone
Maritime, technical, purposeful. Not flashy or corporate. Think instrument panel meets nautical chart: data-dense but readable, dark backgrounds for stream overlay contrast, subtle nautical character without kitsch. The overlay is a tool that happens to look good; it shouldn't distract from the sailing footage.

## Anti-references
- Overly polished SaaS dashboards with hero metrics and gradient cards
- Nautical themes that lean into cliché (rope textures, anchor icons, weathered wood)
- Gaming-style stream overlays with neon colors and aggressive animations
- Generic Bootstrap admin panels

## Technical Stack
- Laravel 12, FrankenPHP, Vite
- Vue 3 + Inertia.js (admin/dashboards)
- Vanilla JS + Blade (stream overlay)
- Leaflet (maps)
- Tailwind CSS 4
- Laravel Reverb (WebSocket)
- Dark theme, 1920x1080 fixed layout for stream overlay
