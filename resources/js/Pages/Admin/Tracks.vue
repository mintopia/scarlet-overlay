<template>
    <AdminLayout>
        <Head title="Tracks" />
        <div class="tracks-page">
            <div class="toolbar">
                <div class="toolbar-controls">
                    <label class="toolbar-label">
                        <span class="toolbar-label-text">Period</span>
                        <select v-model="selectedPeriod" @change="changePeriod" class="field-input text-[13px] py-1.5 px-3" style="width: auto; height: auto">
                            <optgroup v-if="journeys.length" label="Journeys">
                                <option v-for="j in journeys" :key="j.id" :value="j.active ? 'journey' : `journey:${j.id}`">{{ j.title }}</option>
                            </optgroup>
                            <optgroup label="Time Period">
                                <option value="24h">Last 24 hours</option>
                                <option value="48h">Last 48 hours</option>
                                <option value="7d">Last 7 days</option>
                                <option value="30d">Last 30 days</option>
                            </optgroup>
                        </select>
                    </label>
                </div>
            </div>
            <div class="map-wrap" ref="mapEl">
                <div v-if="!gpsTrack.length" class="no-track">No track data</div>
            </div>
            <div class="speed-legend">
                <div class="legend-gradient"></div>
                <span>0</span><span>7 kn</span>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { speedToColor, addRouteLayer } from '../../scarlet.js';

const props = defineProps({
    gpsTrack: { type: Array, default: () => [] },
    journeys: { type: Array, default: () => [] },
    period: String,
    routeWaypoints: { type: Array, default: () => [] },
});

const mapEl = ref(null);
const selectedPeriod = ref(props.period);
let map = null;

function changePeriod() {
    router.get('/admin/tracks', { period: selectedPeriod.value }, { preserveState: true });
}

onMounted(() => {
    if (!mapEl.value || !props.gpsTrack.length) return;

    const firstPt = props.gpsTrack[0];
    map = L.map(mapEl.value, { zoomControl: true, attributionControl: false })
        .setView([firstPt[0], firstPt[1]], 14);
    L.tileLayer('/openseamap/{z}/{x}/{y}', { maxZoom: 18 }).addTo(map);

    for (let i = 1; i < props.gpsTrack.length; i++) {
        L.polyline(
            [[props.gpsTrack[i - 1][0], props.gpsTrack[i - 1][1]], [props.gpsTrack[i][0], props.gpsTrack[i][1]]],
            { color: speedToColor(props.gpsTrack[i][2]), weight: 3, opacity: 0.85 }
        ).addTo(map);
    }

    const bounds = L.latLngBounds(props.gpsTrack.map(p => [p[0], p[1]]));
    map.fitBounds(bounds, { padding: [60, 60] });

    for (const journey of props.journeys) {
        if (journey.route_waypoints?.length) {
            addRouteLayer(map, journey.route_waypoints);
        }
    }
});

onUnmounted(() => {
    map?.remove();
});
</script>

<style scoped>
.tracks-page {
    display: flex;
    flex-direction: column;
    height: calc(100vh - 64px);
    margin: -24px;
    position: relative;
}

.toolbar {
    padding: 12px 16px;
    border-bottom: 1px solid oklch(0.90 0.005 70);
    flex-shrink: 0;
}

.toolbar-controls {
    display: flex;
    gap: 12px;
    align-items: center;
}

.toolbar-label {
    display: flex;
    align-items: center;
    gap: 6px;
}

.toolbar-label-text {
    font-size: 12px;
    color: oklch(0.55 0.01 70);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-weight: 600;
}

.map-wrap {
    flex: 1;
    min-height: 0;
    position: relative;
}

.no-track {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    color: oklch(0.55 0.01 70);
}

.speed-legend {
    position: absolute;
    bottom: 16px;
    left: 16px;
    z-index: 1000;
    display: flex;
    align-items: center;
    gap: 6px;
    background: white;
    border-radius: 8px;
    padding: 6px 12px;
    font-size: 11px;
    color: oklch(0.45 0.01 70);
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.1);
}

.legend-gradient {
    width: 60px;
    height: 8px;
    border-radius: 4px;
    background: linear-gradient(to right, oklch(0.50 0.14 265), oklch(0.64 0.20 155), oklch(0.54 0.24 27));
}
</style>
