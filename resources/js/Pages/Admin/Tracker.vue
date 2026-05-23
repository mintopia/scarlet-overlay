<template>
    <AdminLayout>
        <Head title="Tracker" />
        <!-- Page header -->
        <div class="flex items-baseline justify-between mb-6">
            <h1 class="text-[22px] font-bold">Tracker</h1>
            <div class="flex items-center gap-2 text-[13px] text-text-dim">
                <span class="w-2 h-2 rounded-full bg-green inline-block" :class="lastUpdate ? 'opacity-100' : 'opacity-30'"></span>
                <span class="tabular-nums">{{ timeSinceUpdate }}</span>
            </div>
        </div>

        <!-- Device status strip -->
        <div class="grid grid-cols-2 md:grid-cols-5 gap-3 md:gap-4 mb-6">
            <!-- Status -->
            <div class="bg-surface border border-border rounded-[10px] p-4">
                <div class="text-[11px] font-semibold text-text-dim uppercase tracking-wide mb-2">Status</div>
                <div class="mb-2">
                    <span
                        class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[12px] font-semibold"
                        :class="isConnected ? 'bg-green-bg text-green' : 'bg-amber-bg text-amber'"
                    >
                        <span class="w-1.5 h-1.5 rounded-full inline-block" :class="isConnected ? 'bg-green' : 'bg-amber'"></span>
                        {{ isConnected ? 'Connected' : 'Disconnected' }}
                    </span>
                </div>
                <div class="text-[13px] text-text-secondary tabular-nums">Up {{ formatUptime(live?.uptime) }}</div>
            </div>

            <!-- Connection -->
            <div class="bg-surface border border-border rounded-[10px] p-4">
                <div class="text-[11px] font-semibold text-text-dim uppercase tracking-wide mb-2">Connection</div>
                <div class="text-[13px] font-semibold mb-1">
                    <span v-if="primaryConnection === 'lte'" class="text-scarlet">LTE</span>
                    <span v-else class="text-blue">WiFi</span>
                </div>
                <div class="text-[13px] tabular-nums">
                    <div v-if="primaryConnection === 'lte'" class="text-text-secondary">
                        {{ live?.lte_rssi != null ? live.lte_rssi.toFixed(0) + ' dBm' : '—' }}
                    </div>
                    <div v-else class="text-text-secondary">
                        {{ live?.wifi_rssi != null ? live.wifi_rssi.toFixed(0) + ' dBm' : '—' }}
                    </div>
                </div>
                <div class="text-[11px] text-text-dim mt-1">
                    <span v-if="primaryConnection === 'lte'">WiFi standby</span>
                    <span v-else>LTE standby</span>
                </div>
            </div>

            <!-- Mode -->
            <div class="bg-surface border border-border rounded-[10px] p-4">
                <div class="text-[11px] font-semibold text-text-dim uppercase tracking-wide mb-2">Mode</div>
                <div class="text-[13px] font-semibold">
                    <span v-if="live?.lte_rssi != null" class="text-green">Realtime</span>
                    <span v-else class="text-amber">Saver</span>
                </div>
                <div class="text-[12px] text-text-dim mt-1">
                    <span v-if="live?.lte_rssi != null">15s intervals</span>
                    <span v-else>60s intervals</span>
                </div>
            </div>

            <!-- Battery -->
            <div class="bg-surface border border-border rounded-[10px] p-4">
                <div class="flex items-center justify-between mb-2">
                    <div class="text-[11px] font-semibold text-text-dim uppercase tracking-wide">Battery</div>
                    <span
                        v-if="isUsbPowered"
                        class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-green-bg text-green"
                    >
                        <svg viewBox="0 0 16 16" width="10" height="10" fill="currentColor"><path d="M9.5 1L4 9h4l-1.5 6L13 7H9l.5-6z"/></svg>
                        USB
                    </span>
                </div>
                <div class="text-[20px] font-bold tabular-nums" :class="batteryColor">
                    {{ live?.battery_percent != null ? live.battery_percent.toFixed(0) + '%' : '—' }}
                </div>
                <div class="text-[12px] text-text-secondary tabular-nums mt-0.5">
                    {{ live?.battery_voltage != null ? Number(live.battery_voltage).toFixed(2) + ' V' : '' }}
                </div>
            </div>

            <!-- CPU -->
            <div class="bg-surface border border-border rounded-[10px] p-4">
                <div class="text-[11px] font-semibold text-text-dim uppercase tracking-wide mb-2">CPU</div>
                <div class="text-[20px] font-bold tabular-nums" :class="cpuColor">
                    {{ live?.cpu_usage != null ? live.cpu_usage.toFixed(0) + '%' : '—' }}
                </div>
                <div class="text-[12px] text-text-secondary mt-0.5">Usage</div>
            </div>
        </div>

        <!-- Signal + GPS + CPU charts -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 md:gap-4 mb-4">
            <!-- Signal Strength chart -->
            <Link href="/admin/explore?metric=lte_rssi&range=1h" class="bg-surface border border-border rounded-[10px] p-4 explore-link">
                <svg class="explore-icon" viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M6 2H2v4M14 10v4h-4M2 6l4-4M10 14l4-4"/></svg>
                <div class="text-[15px] font-semibold mb-0.5">Signal Strength</div>
                <div class="text-[12px] text-text-dim mb-3 tabular-nums">
                    LTE
                    <span class="text-scarlet font-medium">{{ live?.lte_rssi != null ? live.lte_rssi.toFixed(0) + ' dBm' : '—' }}</span>
                    &nbsp;·&nbsp;WiFi
                    <span class="font-medium text-blue">{{ live?.wifi_rssi != null ? live.wifi_rssi.toFixed(0) + ' dBm' : '—' }}</span>
                </div>
                <svg viewBox="0 0 400 120" class="w-full" preserveAspectRatio="none">
                    <defs>
                        <linearGradient id="lteGrad" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="var(--color-scarlet)" stop-opacity="0.20"/>
                            <stop offset="100%" stop-color="var(--color-scarlet)" stop-opacity="0.02"/>
                        </linearGradient>
                        <linearGradient id="wifiGrad" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="var(--color-blue)" stop-opacity="0.15"/>
                            <stop offset="100%" stop-color="var(--color-blue)" stop-opacity="0.02"/>
                        </linearGradient>
                    </defs>
                    <!-- LTE fill -->
                    <polygon
                        v-if="signalHistory?.lte?.length"
                        :points="toAreaPolygon(signalHistory.lte, 400, 120, signalMin, signalMax)"
                        fill="url(#lteGrad)"
                    />
                    <!-- WiFi fill -->
                    <polygon
                        v-if="signalHistory?.wifi?.length"
                        :points="toAreaPolygon(signalHistory.wifi, 400, 120, signalMin, signalMax)"
                        fill="url(#wifiGrad)"
                    />
                    <!-- LTE line -->
                    <polyline
                        v-if="signalHistory?.lte?.length"
                        :points="toPolyline(signalHistory.lte, 400, 120, signalMin, signalMax)"
                        fill="none"
                        stroke="var(--color-scarlet)"
                        stroke-width="1.5"
                        stroke-linejoin="round"
                        stroke-linecap="round"
                    />
                    <!-- WiFi line (dashed) -->
                    <polyline
                        v-if="signalHistory?.wifi?.length"
                        :points="toPolyline(signalHistory.wifi, 400, 120, signalMin, signalMax)"
                        fill="none"
                        stroke="var(--color-blue)"
                        stroke-width="1.5"
                        stroke-dasharray="4 3"
                        stroke-linejoin="round"
                        stroke-linecap="round"
                    />
                    <text v-if="!signalHistory?.lte?.length && !signalHistory?.wifi?.length" x="200" y="65" text-anchor="middle" font-size="12" fill="var(--color-text-dim)">No data</text>
                </svg>
                <div class="flex justify-between text-[10px] text-text-dim mt-1">
                    <span>1h ago</span><span>now</span>
                </div>
            </Link>

            <!-- GPS Quality chart -->
            <Link href="/admin/explore?metric=gps_satellites&range=1h" class="bg-surface border border-border rounded-[10px] p-4 explore-link">
                <svg class="explore-icon" viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M6 2H2v4M14 10v4h-4M2 6l4-4M10 14l4-4"/></svg>
                <div class="text-[15px] font-semibold mb-0.5">GPS Quality</div>
                <div class="text-[12px] text-text-dim mb-3 tabular-nums">
                    Satellites
                    <span class="text-green font-medium">{{ liveGps?.satellites ?? '—' }}</span>
                    &nbsp;·&nbsp;HDOP
                    <span class="font-medium">{{ liveGps?.hdop != null ? liveGps.hdop.toFixed(1) : '—' }}</span>
                </div>
                <svg viewBox="0 0 400 120" class="w-full" preserveAspectRatio="none">
                    <defs>
                        <linearGradient id="gpsGrad" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="var(--color-green)" stop-opacity="0.18"/>
                            <stop offset="100%" stop-color="var(--color-green)" stop-opacity="0.02"/>
                        </linearGradient>
                    </defs>
                    <polygon
                        v-if="gpsHistory?.length"
                        :points="toAreaPolygon(gpsHistory, 400, 120, 0, gpsMax)"
                        fill="url(#gpsGrad)"
                    />
                    <polyline
                        v-if="gpsHistory?.length"
                        :points="toPolyline(gpsHistory, 400, 120, 0, gpsMax)"
                        fill="none"
                        stroke="var(--color-green)"
                        stroke-width="1.5"
                        stroke-linejoin="round"
                        stroke-linecap="round"
                    />
                    <text v-if="!gpsHistory?.length" x="200" y="65" text-anchor="middle" font-size="12" fill="var(--color-text-dim)">No data</text>
                </svg>
                <div class="flex justify-between text-[10px] text-text-dim mt-1">
                    <span>1h ago</span><span>now</span>
                </div>
            </Link>

            <!-- CPU Usage chart -->
            <Link href="/admin/explore?metric=cpu_usage&range=1h" class="bg-surface border border-border rounded-[10px] p-4 explore-link">
                <svg class="explore-icon" viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M6 2H2v4M14 10v4h-4M2 6l4-4M10 14l4-4"/></svg>
                <div class="text-[15px] font-semibold mb-0.5">CPU Usage</div>
                <div class="text-[12px] text-text-dim mb-3 tabular-nums">
                    <span class="font-medium" style="color: oklch(0.60 0.16 330)">{{ live?.cpu_usage != null ? live.cpu_usage.toFixed(0) + '%' : '—' }}</span>
                </div>
                <svg viewBox="0 0 400 120" class="w-full" preserveAspectRatio="none">
                    <defs>
                        <linearGradient id="cpuGrad" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="oklch(0.60 0.16 330)" stop-opacity="0.18"/>
                            <stop offset="100%" stop-color="oklch(0.60 0.16 330)" stop-opacity="0.02"/>
                        </linearGradient>
                    </defs>
                    <polygon
                        v-if="cpuHistory?.length"
                        :points="toAreaPolygon(cpuHistory, 400, 120, 0, 100)"
                        fill="url(#cpuGrad)"
                    />
                    <polyline
                        v-if="cpuHistory?.length"
                        :points="toPolyline(cpuHistory, 400, 120, 0, 100)"
                        fill="none"
                        stroke="oklch(0.60 0.16 330)"
                        stroke-width="1.5"
                        stroke-linejoin="round"
                        stroke-linecap="round"
                    />
                    <text v-if="!cpuHistory?.length" x="200" y="65" text-anchor="middle" font-size="12" fill="var(--color-text-dim)">No data</text>
                </svg>
                <div class="flex justify-between text-[10px] text-text-dim mt-1">
                    <span>1h ago</span><span>now</span>
                </div>
            </Link>
        </div>

        <!-- Temp + Humidity charts -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 md:gap-4 mb-6">
            <!-- Cabin Temperature chart -->
            <Link href="/admin/explore?metric=temp_forepeak&range=6h" class="bg-surface border border-border rounded-[10px] p-4 explore-link">
                <svg class="explore-icon" viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M6 2H2v4M14 10v4h-4M2 6l4-4M10 14l4-4"/></svg>
                <div class="text-[15px] font-semibold mb-0.5">Cabin Temperature</div>
                <div class="text-[12px] text-text-dim mb-3 tabular-nums">
                    <span class="text-amber font-medium">
                        {{ live?.cabin_temp != null ? live.cabin_temp.toFixed(1) + '°C' : (props.tracker?.cabin_temp != null ? props.tracker.cabin_temp.toFixed(1) + '°C' : '—') }}
                    </span>
                </div>
                <svg viewBox="0 0 400 120" class="w-full" preserveAspectRatio="none">
                    <defs>
                        <linearGradient id="tempGrad" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="var(--color-amber)" stop-opacity="0.20"/>
                            <stop offset="100%" stop-color="var(--color-amber)" stop-opacity="0.02"/>
                        </linearGradient>
                    </defs>
                    <polygon
                        v-if="tempHistory?.length"
                        :points="toAreaPolygon(tempHistory, 400, 120, tempMin, tempMax)"
                        fill="url(#tempGrad)"
                    />
                    <polyline
                        v-if="tempHistory?.length"
                        :points="toPolyline(tempHistory, 400, 120, tempMin, tempMax)"
                        fill="none"
                        stroke="var(--color-amber)"
                        stroke-width="1.5"
                        stroke-linejoin="round"
                        stroke-linecap="round"
                    />
                    <text v-if="!tempHistory?.length" x="200" y="65" text-anchor="middle" font-size="12" fill="var(--color-text-dim)">No data</text>
                </svg>
                <div class="flex justify-between text-[10px] text-text-dim mt-1">
                    <span>6h ago</span><span>now</span>
                </div>
            </Link>

            <!-- Humidity chart -->
            <Link href="/admin/explore?metric=humidity_forepeak&range=6h" class="bg-surface border border-border rounded-[10px] p-4 explore-link">
                <svg class="explore-icon" viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M6 2H2v4M14 10v4h-4M2 6l4-4M10 14l4-4"/></svg>
                <div class="text-[15px] font-semibold mb-0.5">Humidity</div>
                <div class="text-[12px] text-text-dim mb-3 tabular-nums">
                    <span class="font-medium text-blue">
                        {{ live?.cabin_humidity != null ? live.cabin_humidity.toFixed(0) + '%' : (props.tracker?.cabin_humidity != null ? props.tracker.cabin_humidity.toFixed(0) + '%' : '—') }}
                    </span>
                </div>
                <svg viewBox="0 0 400 120" class="w-full" preserveAspectRatio="none">
                    <defs>
                        <linearGradient id="humGrad" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="var(--color-blue)" stop-opacity="0.18"/>
                            <stop offset="100%" stop-color="var(--color-blue)" stop-opacity="0.02"/>
                        </linearGradient>
                    </defs>
                    <polygon
                        v-if="humidityHistory?.length"
                        :points="toAreaPolygon(humidityHistory, 400, 120, 0, 100)"
                        fill="url(#humGrad)"
                    />
                    <polyline
                        v-if="humidityHistory?.length"
                        :points="toPolyline(humidityHistory, 400, 120, 0, 100)"
                        fill="none"
                        stroke="var(--color-blue)"
                        stroke-width="1.5"
                        stroke-linejoin="round"
                        stroke-linecap="round"
                    />
                    <text v-if="!humidityHistory?.length" x="200" y="65" text-anchor="middle" font-size="12" fill="var(--color-text-dim)">No data</text>
                </svg>
                <div class="flex justify-between text-[10px] text-text-dim mt-1">
                    <span>6h ago</span><span>now</span>
                </div>
            </Link>
        </div>

        <!-- Device Details table -->
        <div class="bg-surface border border-border rounded-[10px] p-4 mb-6">
            <div class="text-[15px] font-semibold mb-4">Device Details</div>
            <table class="w-full text-[13px]">
                <tbody class="divide-y divide-border">
                    <tr>
                        <td class="py-2.5 text-text-secondary w-1/3">WiFi RSSI</td>
                        <td class="py-2.5 font-medium tabular-nums">{{ live?.wifi_rssi != null ? live.wifi_rssi.toFixed(0) + ' dBm' : '—' }}</td>
                    </tr>
                    <tr>
                        <td class="py-2.5 text-text-secondary">LTE RSSI</td>
                        <td class="py-2.5 font-medium tabular-nums">{{ live?.lte_rssi != null ? live.lte_rssi.toFixed(0) + ' dBm' : '—' }}</td>
                    </tr>
                    <tr>
                        <td class="py-2.5 text-text-secondary">GPS Position</td>
                        <td class="py-2.5 font-medium tabular-nums">
                            <span v-if="liveGps?.latitude != null && liveGps?.longitude != null">
                                {{ liveGps.latitude.toFixed(5) }}, {{ liveGps.longitude.toFixed(5) }}
                            </span>
                            <span v-else>—</span>
                        </td>
                    </tr>
                    <tr>
                        <td class="py-2.5 text-text-secondary">GPS Satellites</td>
                        <td class="py-2.5 font-medium tabular-nums">{{ liveGps?.satellites ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td class="py-2.5 text-text-secondary">HDOP</td>
                        <td class="py-2.5 font-medium tabular-nums">{{ liveGps?.hdop != null ? liveGps.hdop.toFixed(1) : '—' }}</td>
                    </tr>
                    <tr>
                        <td class="py-2.5 text-text-secondary">Power Source</td>
                        <td class="py-2.5 font-medium">
                            <span v-if="isUsbPowered" class="text-green">USB</span>
                            <span v-else>Battery</span>
                        </td>
                    </tr>
                    <tr>
                        <td class="py-2.5 text-text-secondary">CPU Usage</td>
                        <td class="py-2.5 font-medium tabular-nums">{{ live?.cpu_usage != null ? live.cpu_usage.toFixed(0) + '%' : '—' }}</td>
                    </tr>
                    <tr>
                        <td class="py-2.5 text-text-secondary">Heap Free</td>
                        <td class="py-2.5 font-medium tabular-nums">{{ live?.heap_free != null ? formatBytes(live.heap_free) : '—' }}</td>
                    </tr>
                    <tr>
                        <td class="py-2.5 text-text-secondary">Uptime</td>
                        <td class="py-2.5 font-medium tabular-nums">{{ formatUptime(live?.uptime) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { computed, ref, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    tracker: Object,
    gps: Object,
    signalHistory: Object,
    gpsHistory: Array,
    cpuHistory: Array,
    tempHistory: Array,
    humidityHistory: Array,
});

const metrics = ref(null);
const lastUpdate = ref(props.tracker ? Date.now() : null);
let echoChannel = null;
if (window.Echo) {
    echoChannel = window.Echo.channel('metrics');
    echoChannel.listen('.metrics.updated', (data) => {
        metrics.value = data;
        lastUpdate.value = Date.now();
    });
}

const live = computed(() => metrics.value?.tracker ?? props.tracker);
const liveGps = computed(() => metrics.value?.gps ?? props.gps);

// Tick every second to refresh "Xs ago"
const now = ref(Date.now());
let ticker = null;
onMounted(() => { ticker = setInterval(() => { now.value = Date.now(); }, 1000); });
onUnmounted(() => {
    if (ticker) clearInterval(ticker);
    if (echoChannel) window.Echo?.leave('metrics');
});

const timeSinceUpdate = computed(() => {
    if (!lastUpdate.value) return 'Loading...';
    const seconds = Math.floor((now.value - lastUpdate.value) / 1000);
    return `last update ${seconds}s ago`;
});

const isConnected = computed(() => {
    const t = live.value;
    return t && (t.lte_rssi != null || t.wifi_rssi != null);
});

const primaryConnection = computed(() => {
    const t = live.value;
    if (!t) return 'lte';
    if (t.wifi_rssi != null) return 'wifi';
    return 'lte';
});

const isUsbPowered = computed(() => (live.value?.usb_powered ?? 0) >= 1);

const batteryColor = computed(() => {
    if (isUsbPowered.value) return 'text-green';
    const pct = live.value?.battery_percent;
    if (pct == null) return 'text-text-primary';
    if (pct > 50) return 'text-green';
    if (pct > 20) return 'text-amber';
    return 'text-scarlet';
});

const cpuColor = computed(() => {
    const pct = live.value?.cpu_usage;
    if (pct == null) return 'text-text-primary';
    if (pct < 50) return 'text-green';
    if (pct < 80) return 'text-amber';
    return 'text-scarlet';
});

// Chart range helpers
const signalMin = computed(() => {
    const lte = props.signalHistory?.lte ?? [];
    const wifi = props.signalHistory?.wifi ?? [];
    const all = [...lte, ...wifi].map(d => d.value);
    return all.length ? Math.min(...all) - 5 : -110;
});
const signalMax = computed(() => {
    const lte = props.signalHistory?.lte ?? [];
    const wifi = props.signalHistory?.wifi ?? [];
    const all = [...lte, ...wifi].map(d => d.value);
    return all.length ? Math.max(...all) + 5 : -40;
});
const gpsMax = computed(() => {
    const vals = (props.gpsHistory ?? []).map(d => d.value);
    return vals.length ? Math.max(...vals) + 2 : 20;
});
const tempMin = computed(() => {
    const vals = (props.tempHistory ?? []).map(d => d.value);
    return vals.length ? Math.min(...vals) - 2 : 0;
});
const tempMax = computed(() => {
    const vals = (props.tempHistory ?? []).map(d => d.value);
    return vals.length ? Math.max(...vals) + 2 : 40;
});

// SVG chart helpers
function toPolyline(data, viewWidth, viewHeight, minVal, maxVal) {
    if (!data || data.length === 0) return '';
    const range = maxVal - minVal || 1;
    return data.map((d, i) => {
        const x = (i / (data.length - 1)) * viewWidth;
        const y = viewHeight - ((d.value - minVal) / range) * (viewHeight - 10) - 5;
        return `${x},${y}`;
    }).join(' ');
}

function toAreaPolygon(data, viewWidth, viewHeight, minVal, maxVal) {
    if (!data || data.length === 0) return '';
    const line = toPolyline(data, viewWidth, viewHeight, minVal, maxVal);
    const lastX = viewWidth;
    const firstX = 0;
    return `${firstX},${viewHeight} ${line} ${lastX},${viewHeight}`;
}

// Utility formatters
function formatUptime(seconds) {
    if (seconds == null) return '—';
    const s = Math.floor(seconds);
    const d = Math.floor(s / 86400);
    const h = Math.floor((s % 86400) / 3600);
    const m = Math.floor((s % 3600) / 60);
    if (d > 0) return `${d}d ${h}h`;
    if (h > 0) return `${h}h ${m}m`;
    return `${m}m`;
}

function formatBytes(bytes) {
    if (bytes == null) return '—';
    if (bytes >= 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return bytes + ' B';
}
</script>
