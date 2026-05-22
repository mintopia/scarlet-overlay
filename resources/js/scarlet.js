import L from 'leaflet';

export const weatherIcons = {
    'day-sunny': '☀️',
    'night-clear': '🌙',
    'cloud': '⛅',
    'cloudy': '☁️',
    'fog': '🌫️',
    'sprinkle': '🌦️',
    'rain': '🌧️',
    'snow': '❄️',
    'showers': '🌦️',
    'thunderstorm': '⛈️',
    'na': '🌤️',
};

export const weatherLabels = {
    'day-sunny': 'Clear',
    'night-clear': 'Clear',
    'cloud': 'Partly Cloudy',
    'cloudy': 'Overcast',
    'fog': 'Fog',
    'sprinkle': 'Drizzle',
    'rain': 'Rain',
    'snow': 'Snow',
    'showers': 'Showers',
    'thunderstorm': 'Thunderstorm',
    'na': 'Unknown',
};

export function speedToColor(speed) {
    const ratio = Math.min(Math.max(speed ?? 0, 0) / 7, 1);
    if (ratio <= 0.5) {
        const t = ratio * 2;
        const l = 0.50 + t * 0.14;
        const c = 0.14 + t * 0.06;
        const h = 265 - t * 110;
        return `oklch(${l} ${c} ${h})`;
    }
    const t = (ratio - 0.5) * 2;
    const l = 0.64 - t * 0.10;
    const c = 0.20 + t * 0.04;
    const h = 155 - t * 128;
    return `oklch(${l} ${c} ${h})`;
}

export function makeBoatIcon(heading) {
    return L.divIcon({
        className: 'boat-marker',
        html: `<svg width="24" height="24" viewBox="0 0 24 24" style="transform:rotate(${heading ?? 0}deg);overflow:visible">
            <polygon points="12,2 20,20 12,16 4,20"
                fill="oklch(0.54 0.22 27)"
                stroke="oklch(0.96 0.005 70)"
                stroke-width="1.5"
                stroke-linejoin="round"/>
        </svg>`,
        iconSize: [24, 24],
        iconAnchor: [12, 12],
    });
}

export function formatCoord(lat, lon) {
    if (lat == null || lon == null) return '--';
    const latDir = lat >= 0 ? 'N' : 'S';
    const lonDir = lon >= 0 ? 'E' : 'W';
    return `${Math.abs(lat).toFixed(4)}°${latDir}  ${Math.abs(lon).toFixed(4)}°${lonDir}`;
}

export function formatVal(val, decimals = 1) {
    return val != null ? Number(val).toFixed(decimals) : '--';
}

export function getWeatherIcon(summary) {
    return weatherIcons[summary] ?? weatherIcons['na'];
}

export function getWeatherLabel(summary) {
    return weatherLabels[summary] ?? 'Unknown';
}

export function addRouteLayer(map, waypoints) {
    if (!waypoints?.length) return { polyline: null, markers: [] };

    const latlngs = waypoints.map(w => [w.lat, w.lng]);
    const polyline = L.polyline(latlngs, {
        color: 'oklch(0.65 0.10 240 / 0.5)',
        weight: 2.5,
        dashArray: '8, 6',
        opacity: 0.8,
    }).addTo(map);

    const markers = waypoints
        .filter(w => w.name)
        .map(w => {
            return L.circleMarker([w.lat, w.lng], {
                radius: 4,
                color: 'oklch(0.65 0.10 240 / 0.6)',
                fillColor: 'oklch(0.65 0.10 240 / 0.3)',
                fillOpacity: 1,
                weight: 1.5,
            })
            .bindTooltip(w.name, { direction: 'top', offset: [0, -6], className: 'route-tooltip' })
            .addTo(map);
        });

    return { polyline, markers };
}
