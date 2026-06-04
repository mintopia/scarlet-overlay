<template>
    <Head :title="`${plan.title} — Route Plan`" />

    <div class="public-planner">
        <!-- Header -->
        <div class="public-header">
            <div>
                <h1 class="font-sans text-xl font-extrabold tracking-tight text-text-primary">{{ plan.title }}</h1>
                <div class="text-[12px] font-body text-text-dim mt-0.5">
                    {{ plan.groups.length }} {{ plan.groups.length === 1 ? 'group' : 'groups' }}
                    &middot; {{ totalRoutes }} {{ totalRoutes === 1 ? 'route' : 'routes' }}
                    <span class="text-[10px] font-bold uppercase tracking-wide text-text-dim bg-bg px-2 py-0.5 rounded-full ml-2">Read-only</span>
                </div>
            </div>
        </div>

        <!-- Map -->
        <div class="map-section" ref="mapEl">
            <div class="map-tile-switch">
                <button class="map-tile-btn" :class="{ active: tileMode === 'sea' }" @click="setTileMode('sea')">Sea Chart</button>
                <button class="map-tile-btn" :class="{ active: tileMode === 'satellite' }" @click="setTileMode('satellite')">Satellite</button>
            </div>
        </div>

        <!-- Groups grid -->
        <div class="groups-grid" v-if="plan.groups.length">
            <PlanGroupCard
                v-for="group in plan.groups"
                :key="group.id"
                :group="groupWithLocalState(group)"
                :readonly="true"
                @toggle-route="toggleRouteLocal"
                @focus-waypoint="panToWaypoint"
            />
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';
import { Head } from '@inertiajs/vue3';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import PlanGroupCard from '@/Components/Admin/PlanGroupCard.vue';
import { routeColor, routeColorDim } from '@/helpers/planColors.js';

const props = defineProps({
    plan: { type: Object, required: true },
    defaultCenter: { type: Array, default: () => [37.1028, -8.6740] },
});

const mapEl = ref(null);
const tileMode = ref('sea');
let map = null;
let tileLayer = null;
let routeLayerMap = {};

const toggleState = reactive({});
props.plan.groups.forEach(g => g.routes.forEach(r => { toggleState[r.id] = r.is_enabled; }));

const totalRoutes = computed(() => props.plan.groups.reduce((sum, g) => sum + g.routes.length, 0));

function groupWithLocalState(group) {
    return {
        ...group,
        routes: group.routes.map(r => ({ ...r, is_enabled: toggleState[r.id] ?? r.is_enabled })),
    };
}

function toggleRouteLocal(route) {
    toggleState[route.id] = !toggleState[route.id];
    nextTick(buildRouteLayers);
}

function getTileUrl() {
    if (tileMode.value === 'satellite') return '/satellite/{z}/{y}/{x}';
    return '/openseamap/{z}/{x}/{y}';
}

function setTileMode(mode) {
    tileMode.value = mode;
    if (!map) return;
    if (tileLayer) map.removeLayer(tileLayer);
    tileLayer = L.tileLayer(getTileUrl(), { maxZoom: 18 }).addTo(map);
}

function buildRouteLayers() {
    Object.values(routeLayerMap).forEach(layers => layers.forEach(l => map.removeLayer(l)));
    routeLayerMap = {};
    const allPoints = [];

    for (const group of props.plan.groups) {
        for (const route of group.routes) {
            const enabled = toggleState[route.id] ?? route.is_enabled;
            const color = routeColor(group.color_index, route.color_index);
            const dimColor = routeColorDim(group.color_index, route.color_index);
            const layers = [];
            const points = route.track_points?.length ? route.track_points : (route.waypoints || []).map(w => [w.lat, w.lng]);
            if (!points.length) continue;
            if (enabled) allPoints.push(...points);

            const polyline = L.polyline(points, {
                color: enabled ? color : dimColor, weight: enabled ? 3 : 2,
                opacity: enabled ? 0.85 : 0.3, dashArray: enabled ? null : '6 4',
            }).addTo(map);
            layers.push(polyline);

            if (enabled && points.length >= 2) {
                layers.push(L.circleMarker(points[0], { radius: 7, color: '#fff', fillColor: color, fillOpacity: 1, weight: 2.5 }).addTo(map));
                layers.push(L.circleMarker(points[points.length - 1], { radius: 6, color: '#fff', fillColor: color, fillOpacity: 1, weight: 2 }).addTo(map));
            }

            routeLayerMap[route.id] = layers;
        }
    }

    if (allPoints.length) map.fitBounds(L.latLngBounds(allPoints), { padding: [40, 40] });
}

function panToWaypoint(wp) {
    if (map && wp.lat && wp.lng) map.setView([wp.lat, wp.lng], Math.max(map.getZoom(), 12));
}

onMounted(() => {
    if (!mapEl.value) return;
    map = L.map(mapEl.value, { zoomControl: true, attributionControl: false }).setView(props.defaultCenter, 8);
    tileLayer = L.tileLayer(getTileUrl(), { maxZoom: 18 }).addTo(map);
    buildRouteLayers();
});

onUnmounted(() => { map?.remove(); map = null; });
</script>

<style scoped>
.public-planner {
    max-width: 1200px; margin: 0 auto;
    padding: 24px 16px;
    font-family: var(--font-sans); color: var(--color-text-primary);
}
@media (min-width: 768px) { .public-planner { padding: 32px 40px; } }

.public-header { margin-bottom: 16px; }

.map-section {
    border-radius: 16px; overflow: hidden;
    border: 1px solid var(--color-border); box-shadow: var(--shadow-sm);
    position: relative; height: 45vh; min-height: 280px;
}
.map-tile-switch {
    position: absolute; top: 12px; right: 12px; z-index: 1000;
    display: flex; background: var(--color-surface); border-radius: 8px;
    border: 1px solid var(--color-border); overflow: hidden;
}
.map-tile-btn {
    padding: 6px 14px; font-size: 12px; font-weight: 600;
    font-family: var(--font-body); color: var(--color-text-secondary);
    border: none; background: none; cursor: pointer;
}
.map-tile-btn.active { background: var(--color-teal); color: #fff; }
.map-tile-btn + .map-tile-btn { border-left: 1px solid var(--color-border); }

.groups-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-top: 20px; }
@media (max-width: 768px) { .groups-grid { grid-template-columns: 1fr; } }
</style>
