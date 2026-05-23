<template>
    <AdminLayout>
        <Head title="Weather" />
        <h1 class="text-[22px] font-bold mb-6">Weather</h1>

        <template v-if="weather">
            <!-- Conditions -->
            <div class="panel p-4 mb-4">
                <div class="panel-title mb-3">Conditions</div>
                <div class="space-y-2 text-[13px]">
                    <div class="data-row"><span>Sky</span><span>{{ weather.conditionText }}</span></div>
                    <div class="data-row"><span>Air Temperature</span><span>{{ fmt(weather.temp) }}°C</span></div>
                    <div class="data-row"><span>Pressure</span><span>{{ weather.pressure != null ? Math.round(weather.pressure) + ' hPa' : '—' }}</span></div>
                    <div class="data-row"><span>Time of Day</span><span>{{ weather.daytime ? 'Day' : 'Night' }}</span></div>
                </div>
            </div>

            <!-- Wind Comparison -->
            <div class="panel p-4 mb-6">
                <div class="panel-title mb-3">Wind</div>
                <table class="w-full text-[13px]">
                    <thead>
                        <tr class="text-left text-[11px] font-semibold text-text-dim uppercase tracking-wide">
                            <th class="pb-2">Source</th>
                            <th class="pb-2">Speed</th>
                            <th class="pb-2">Direction</th>
                        </tr>
                    </thead>
                    <tbody class="tabular-nums">
                        <tr class="border-t border-border-light">
                            <td class="py-2 text-text-secondary">Forecast</td>
                            <td class="py-2 font-semibold">{{ fmt(weather.wind?.speed) }} kn (G{{ fmt(weather.wind?.gusts) }})</td>
                            <td class="py-2 font-semibold">{{ degreesToCompass(weather.wind?.direction) }} ({{ fmt(weather.wind?.direction, 0) }}°)</td>
                        </tr>
                        <tr class="border-t border-border-light">
                            <td class="py-2 text-text-secondary">Boat (true)</td>
                            <td class="py-2 font-semibold">{{ fmt(boat?.wind_speed_true) }} kn</td>
                            <td class="py-2 font-semibold">{{ degreesToCompass(boat?.wind_direction_true) }} ({{ fmt(boat?.wind_direction_true, 0) }}°)</td>
                        </tr>
                        <tr class="border-t border-border-light">
                            <td class="py-2 text-text-secondary">Boat (apparent)</td>
                            <td class="py-2 font-semibold">{{ fmt(boat?.wind_speed_apparent) }} kn</td>
                            <td class="py-2 font-semibold">{{ fmt(boat?.wind_angle_apparent, 0) }}°</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Sea State -->
            <div class="panel p-4 mb-6">
                <div class="panel-title mb-3">Sea State</div>
                <div class="space-y-2 text-[13px]">
                    <div class="data-row"><span>Wave Height</span><span>{{ fmt(weather.waves?.height) }} m</span></div>
                    <div class="data-row"><span>Wave Direction</span><span>{{ degreesToCompass(weather.waves?.direction) }} ({{ fmt(weather.waves?.direction, 0) }}°)</span></div>
                    <div class="data-row"><span>Wave Period</span><span>{{ fmt(weather.waves?.period) }} s</span></div>
                    <div class="data-row"><span>Sea Surface Temp (forecast)</span><span style="color: oklch(0.55 0.15 240)">{{ fmt(weather.seaTemp) }}°C</span></div>
                    <div class="data-row"><span>Water Temp (boat sensor)</span><span style="color: oklch(0.55 0.15 240)">{{ fmt(boat?.water_temp) }}°C</span></div>
                </div>
            </div>

            <!-- Ocean Current -->
            <div class="panel p-4 mb-4">
                <div class="panel-title mb-3">Ocean Current</div>
                <div class="space-y-2 text-[13px]">
                    <div class="data-row"><span>Current Speed</span><span>{{ fmt(weather.current?.speed) }} kn</span></div>
                    <div class="data-row"><span>Current Direction</span><span>{{ degreesToCompass(weather.current?.direction) }} ({{ fmt(weather.current?.direction, 0) }}°)</span></div>
                </div>
            </div>
        </template>

        <div v-else class="panel p-6 text-center text-[13px] text-text-secondary">
            Weather data unavailable. Check GPS position and network connectivity.
        </div>

        <p v-if="timestamp" class="text-[11px] text-text-dim mt-2 tabular-nums">Updated {{ timestamp }}</p>
    </AdminLayout>
</template>

<script setup>
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { fmt } from '@/composables/useFormatters.js';

defineProps({
    weather: Object,
    boat: Object,
    timestamp: String,
});

function degreesToCompass(deg) {
    if (deg == null) return '—';
    const dirs = ['N','NNE','NE','ENE','E','ESE','SE','SSE','S','SSW','SW','WSW','W','WNW','NW','NNW'];
    return dirs[Math.round(((deg % 360) + 360) % 360 / 22.5) % 16];
}
</script>

<style scoped>
.panel-title { font-size: 15px; font-weight: 600; }
.data-row { display: flex; justify-content: space-between; align-items: baseline; }
.data-row span:first-child { color: var(--color-text-secondary); }
.data-row span:last-child { font-variant-numeric: tabular-nums; font-weight: 600; }
</style>
