<template>
    <AdminLayout>
        <Head title="Dashboard" />
        <h1 class="text-[22px] font-bold mb-6">Dashboard</h1>

        <!-- Active Journey / Start Journey -->
        <div class="panel p-4 mb-6">
            <div class="panel-title mb-3">Journey</div>
            <template v-if="activeJourney">
                <div class="flex items-baseline justify-between mb-2">
                    <div>
                        <span class="text-[16px] font-semibold">{{ activeJourney.title }}</span>
                        <span class="ml-2 text-[10px] font-semibold tracking-wide px-2 py-0.5 rounded bg-green/10 text-green">ACTIVE</span>
                    </div>
                </div>
                <div class="space-y-2 text-[13px] mb-4">
                    <div class="data-row"><span>Elapsed</span><span>{{ fmtDuration(activeJourney.duration) }}</span></div>
                    <div class="data-row"><span>Distance</span><span>{{ activeJourney.distance }} nm</span></div>
                    <div class="data-row"><span>Speed</span><span class="text-scarlet">{{ fmt(boat?.speed_sog) }} kn</span></div>
                </div>
                <div class="flex gap-2">
                    <button @click="showEndModal = true" class="btn btn--danger">End Journey</button>
                    <Link href="/admin/journeys" class="btn btn--ghost">All Journeys</Link>
                </div>
            </template>
            <template v-else-if="plannedJourney">
                <div class="flex items-baseline justify-between mb-2">
                    <div>
                        <span class="text-[16px] font-semibold">{{ plannedJourney.title }}</span>
                        <span class="ml-2 text-[10px] font-semibold tracking-wide px-2 py-0.5 rounded bg-amber-100 text-amber-700">PLANNED</span>
                    </div>
                </div>
                <div class="space-y-2 text-[13px] mb-4">
                    <div class="data-row"><span>Route</span><span>{{ plannedJourney.from_port }} → {{ plannedJourney.to_port }}</span></div>
                    <div class="data-row"><span>GPX</span><span>{{ plannedJourney.has_gpx ? 'Uploaded' : 'None' }}</span></div>
                </div>
                <div class="flex gap-2">
                    <button @click="showStartModal = true" class="btn btn--primary">Start Recording</button>
                    <Link :href="`/admin/journeys/${plannedJourney.id}/edit`" class="btn btn--ghost">Edit</Link>
                </div>
            </template>
            <template v-else>
                <p class="text-[13px] text-text-secondary mb-3">No voyage underway. Plan a new journey to get started.</p>
                <div class="flex gap-2">
                    <Link href="/admin/journeys/create" class="btn btn--primary">Plan Journey</Link>
                    <Link href="/admin/journeys/import" class="btn btn--ghost">Import from History</Link>
                </div>
            </template>
        </div>

        <!-- Route Map -->
        <div v-if="routeWaypoints.length" class="panel p-0 mb-6 overflow-hidden">
            <div ref="mapEl" class="route-map"></div>
        </div>

        <!-- Boat Status -->
        <div class="panel p-4 mb-4">
            <div class="flex items-baseline justify-between mb-3">
                <span class="panel-title">Boat Status</span>
                <div class="flex items-center gap-3">
                    <span v-if="props.timestamp" class="text-[11px] text-text-dim tabular-nums">{{ timeSinceUpdate }}</span>
                    <Link href="/admin/metrics" class="text-[12px] text-scarlet font-medium hover:underline">View Metrics →</Link>
                </div>
            </div>
            <div class="strip">
                <div class="strip-cell"><div class="strip-label">SOG</div><div class="strip-value text-scarlet">{{ fmt(boat?.speed_sog) }}</div><div class="strip-unit">kn</div></div>
                <div class="strip-cell"><div class="strip-label">Heading</div><div class="strip-value">{{ fmt(boat?.heading, 0) }}</div><div class="strip-unit">°</div></div>
                <div class="strip-cell"><div class="strip-label">Depth</div><div class="strip-value" style="color: oklch(0.55 0.15 240)">{{ fmt(boat?.depth) }}</div><div class="strip-unit">m</div></div>
                <div class="strip-cell"><div class="strip-label">Battery</div><div class="strip-value text-green">{{ fmt(boat?.house_battery_voltage, 2) }}</div><div class="strip-unit">V</div></div>
            </div>
            <div v-if="gps?.latitude != null" class="text-[12px] text-text-secondary mt-2 tabular-nums">
                {{ fmtCoord(gps.latitude, gps.longitude) }}
            </div>
        </div>

        <!-- Navigation (autopilot waypoint) -->
        <div v-if="boat?.nav_wp_distance > 0 && boat?.nav_wp_ttg > 0" class="bg-surface border border-border rounded-[10px] p-4 mb-4">
            <div class="text-[11px] font-semibold text-text-dim uppercase tracking-wide mb-3">Navigation</div>
            <div class="space-y-2 text-[13px]">
                <div class="flex justify-between">
                    <span class="text-text-secondary">Next Waypoint</span>
                    <span class="font-semibold tabular-nums" style="color: oklch(0.55 0.15 240)">{{ fmtNav(boat.nav_wp_distance) }} nm</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-text-secondary">Time to Go</span>
                    <span class="font-semibold tabular-nums">{{ formatTtg(boat.nav_wp_ttg) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-text-secondary">ETA</span>
                    <span class="font-semibold tabular-nums">{{ formatEta(boat.nav_wp_ttg) }}</span>
                </div>
            </div>
        </div>

        <!-- Tracker Status -->
        <div class="panel p-4 mb-6">
            <div class="flex items-baseline justify-between mb-3">
                <span class="panel-title">Tracker</span>
                <Link href="/admin/tracker" class="text-[12px] text-scarlet font-medium hover:underline">View Tracker →</Link>
            </div>
            <div class="space-y-2 text-[13px]">
                <div class="data-row">
                    <span>Connection</span>
                    <span>{{ tracker?.wifi_rssi != null ? 'WiFi' : tracker?.lte_rssi != null ? 'LTE' : 'Disconnected' }}</span>
                </div>
                <div class="data-row">
                    <span>Signal</span>
                    <span>{{ tracker?.wifi_rssi != null ? fmt(tracker.wifi_rssi, 0) + ' dBm' : tracker?.lte_rssi != null ? fmt(tracker.lte_rssi, 0) + ' dBm' : '—' }}</span>
                </div>
                <div class="data-row">
                    <span>Battery</span>
                    <span>{{ tracker?.battery_percent != null ? fmt(tracker.battery_percent, 0) + '%' : '—' }}</span>
                </div>
            </div>
        </div>

        <!-- Recent Journeys -->
        <div class="panel p-4 mb-4" v-if="recentJourneys.length">
            <div class="flex items-baseline justify-between mb-3">
                <span class="panel-title">Recent Journeys</span>
                <Link href="/admin/journeys" class="text-[12px] text-scarlet font-medium hover:underline">View All →</Link>
            </div>
            <div class="space-y-2">
                <Link v-for="j in recentJourneys" :key="j.id" :href="`/journey/${j.slug}`" class="flex items-baseline justify-between text-[13px] py-1.5 hover:text-scarlet transition-colors">
                    <span class="font-medium">{{ j.title }}</span>
                    <span class="text-text-dim tabular-nums">{{ fmtDate(j.started_at) }} · {{ j.distance }} nm</span>
                </Link>
            </div>
        </div>

        <!-- Start Journey Confirm Modal -->
        <Transition name="modal">
        <div v-if="showStartModal" class="modal-overlay" @click.self="showStartModal = false">
            <div class="modal-card" role="dialog" aria-modal="true" aria-label="Confirm start journey">
                <h3 class="text-[16px] font-semibold mb-2">Start recording?</h3>
                <p class="text-[13px] text-text-secondary mb-5">This will begin track recording for <strong>{{ plannedJourney?.title }}</strong>. GPS position and boat data will be logged from now.</p>
                <div class="flex items-center justify-end gap-3">
                    <button @click="showStartModal = false" class="btn btn--ghost">Cancel</button>
                    <button @click="startJourney" :disabled="startingJourney" class="btn btn--primary">Start Recording</button>
                </div>
            </div>
        </div>
        </Transition>

        <!-- End Journey Confirm Modal -->
        <Transition name="modal">
        <div v-if="showEndModal" class="modal-overlay" @click.self="showEndModal = false">
            <div class="modal-card" role="dialog" aria-modal="true" aria-label="Confirm end journey">
                <h3 class="text-[16px] font-semibold mb-2">End this journey?</h3>
                <p class="text-[13px] text-text-secondary mb-5">This will mark <strong>{{ activeJourney?.title }}</strong> as completed. You can still edit it afterwards.</p>
                <div class="flex items-center justify-end gap-3">
                    <button @click="showEndModal = false" class="btn btn--ghost">Cancel</button>
                    <button @click="endJourney" :disabled="endingJourney" class="btn btn--danger">End Journey</button>
                </div>
            </div>
        </div>
        </Transition>
    </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { addRouteLayer } from '../../scarlet';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { fmt, fmtDuration } from '@/composables/useFormatters.js';

const props = defineProps({
    boat: Object,
    gps: Object,
    tracker: Object,
    activeJourney: Object,
    plannedJourney: Object,
    routeWaypoints: { type: Array, default: () => [] },
    recentJourneys: Array,
    timestamp: String,
});

const mapEl = ref(null);
let map = null;

const now = ref(Date.now());
let ticker;
onMounted(() => {
    ticker = setInterval(() => { now.value = Date.now(); }, 1000);

    if (mapEl.value && props.routeWaypoints.length) {
        const first = props.routeWaypoints[0];
        map = L.map(mapEl.value, { zoomControl: false, attributionControl: false })
            .setView([first.lat, first.lng], 12);
        L.tileLayer('/openseamap/{z}/{x}/{y}', { maxZoom: 18 }).addTo(map);
        addRouteLayer(map, props.routeWaypoints);
        const bounds = L.latLngBounds(props.routeWaypoints.map(w => [w.lat, w.lng]));
        map.fitBounds(bounds, { padding: [30, 30] });
    }
});
onUnmounted(() => {
    clearInterval(ticker);
    map?.remove();
});

const timeSinceUpdate = computed(() => {
    if (!props.timestamp) return '';
    const diff = Math.floor((now.value - new Date(props.timestamp).getTime()) / 1000);
    if (diff < 5) return 'just now';
    if (diff < 60) return `${diff}s ago`;
    return `${Math.floor(diff / 60)}m ago`;
});

const showStartModal = ref(false);
const startingJourney = ref(false);
const showEndModal = ref(false);
const endingJourney = ref(false);

function startJourney() {
    startingJourney.value = true;
    router.post(`/admin/journeys/${props.plannedJourney.id}/start`, {}, {
        onFinish: () => {
            startingJourney.value = false;
            showStartModal.value = false;
        },
    });
}

function endJourney() {
    endingJourney.value = true;
    router.post(`/admin/journeys/${props.activeJourney.id}/end`, {}, {
        onFinish: () => {
            endingJourney.value = false;
            showEndModal.value = false;
        },
    });
}

function fmtDate(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });
}

function fmtCoord(lat, lon) {
    if (lat == null || lon == null) return '';
    const latDir = lat >= 0 ? 'N' : 'S';
    const lonDir = lon >= 0 ? 'E' : 'W';
    return `${Math.abs(lat).toFixed(4)}°${latDir}  ${Math.abs(lon).toFixed(4)}°${lonDir}`;
}

function fmtNav(v) {
    return v != null ? v.toFixed(1) : '—';
}

function formatTtg(seconds) {
    if (seconds == null || seconds <= 0) return '—';
    const s = Math.floor(seconds);
    const d = Math.floor(s / 86400);
    const h = Math.floor((s % 86400) / 3600);
    const m = Math.floor((s % 3600) / 60);
    if (d > 0) return `${d}d ${h}h`;
    if (h > 0) return `${h}h ${m}m`;
    return `${m}m`;
}

function formatEta(seconds) {
    if (seconds == null || seconds <= 0) return '—';
    const eta = new Date(Date.now() + seconds * 1000);
    return eta.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
}
</script>

<style scoped>
.panel-title {
    font-size: 15px;
    font-weight: 600;
}

.data-row {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
}

.data-row span:first-child { color: var(--color-text-secondary); }
.data-row span:last-child { font-variant-numeric: tabular-nums; font-weight: 600; }

.strip {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    background: var(--color-bg);
    border: 1px solid var(--color-border-light);
    border-radius: 8px;
    overflow: hidden;
}

@media (min-width: 640px) {
    .strip { grid-template-columns: repeat(4, 1fr); }
}

.strip-cell { padding: 10px 12px; text-align: center; }
.strip-cell { border-bottom: 1px solid var(--color-border-light); border-right: 1px solid var(--color-border-light); }
.strip-cell:nth-child(even) { border-right: none; }
.strip-cell:nth-last-child(-n+2) { border-bottom: none; }

@media (min-width: 640px) {
    .strip-cell { border-bottom: none; border-right: none; }
    .strip-cell + .strip-cell { border-left: 1px solid var(--color-border-light); }
}
.strip-label { font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; color: var(--color-text-dim); margin-bottom: 2px; }
.strip-value { font-size: 20px; font-weight: 700; font-variant-numeric: tabular-nums; line-height: 1; }
.strip-unit { font-size: 10px; color: var(--color-text-dim); margin-top: 2px; }

.route-map {
    height: 220px;
}

@media (min-width: 640px) {
    .route-map { height: 280px; }
}

.modal-overlay {
    position: fixed; inset: 0;
    background: oklch(0.05 0.008 40 / 0.45);
    display: flex; align-items: center; justify-content: center;
    z-index: 100; padding: 24px;
}
.modal-card {
    background: var(--color-surface);
    border-radius: 12px;
    padding: 28px 28px 24px;
    width: 100%; max-width: 400px;
    box-shadow: 0 8px 40px oklch(0.05 0.008 40 / 0.14);
}
</style>
