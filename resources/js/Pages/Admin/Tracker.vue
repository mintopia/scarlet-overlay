<template>
    <AdminLayout :wide="true">
        <Head title="Tracker" />

        <!-- Page header -->
        <div class="flex items-baseline justify-between mb-5">
            <h1 class="font-sans text-2xl font-extrabold tracking-tight">Tracker</h1>
            <div class="flex items-center gap-2 text-[13px] font-body text-text-dim">
                <span class="w-2 h-2 rounded-full inline-block" :class="lastUpdate ? 'bg-green' : 'bg-text-dim opacity-30'"></span>
                <span class="tabular-nums">{{ timeSinceUpdate }}</span>
            </div>
        </div>

        <!-- Status ribbon -->
        <div class="panel p-4 px-6 mb-5">
            <div class="flex items-center gap-6 flex-wrap">
                <!-- Status + uptime -->
                <div class="flex items-center gap-3 pr-6 border-r border-border-light">
                    <span
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[12px] font-body font-extrabold uppercase tracking-wide"
                        :class="isConnected ? 'bg-green-bg text-green' : 'bg-amber-bg text-amber'"
                    >
                        <span class="w-1.5 h-1.5 rounded-full inline-block" :class="isConnected ? 'bg-green' : 'bg-amber'"></span>
                        {{ isConnected ? 'Connected' : 'Disconnected' }}
                    </span>
                    <span class="text-[13px] font-body text-text-secondary tabular-nums">{{ formatUptime(live?.uptime) }}</span>
                </div>

                <!-- Connection -->
                <div class="pr-6 border-r border-border-light">
                    <div class="text-[10px] font-body font-bold text-text-dim uppercase tracking-wide mb-0.5">Connection</div>
                    <div class="text-[14px] font-sans font-semibold tabular-nums">
                        <span v-if="primaryConnection === 'lte'" class="text-scarlet">LTE</span>
                        <span v-else class="text-blue">WiFi</span>
                        <span class="text-text-dim font-normal ml-1">
                            {{ primaryConnection === 'lte'
                                ? (live?.lte_rssi != null ? live.lte_rssi.toFixed(0) + ' dBm' : '—')
                                : (live?.wifi_rssi != null ? live.wifi_rssi.toFixed(0) + ' dBm' : '—')
                            }}
                        </span>
                    </div>
                </div>

                <!-- Mode -->
                <div class="pr-6 border-r border-border-light">
                    <div class="text-[10px] font-body font-bold text-text-dim uppercase tracking-wide mb-0.5">Mode</div>
                    <div class="text-[14px] font-sans font-semibold">
                        <span v-if="live?.lte_rssi != null" class="text-green">Realtime</span>
                        <span v-else class="text-amber">Saver</span>
                        <span class="text-text-dim font-normal ml-1 text-[12px]">
                            {{ live?.lte_rssi != null ? '15s' : '60s' }}
                        </span>
                    </div>
                </div>

                <!-- Battery -->
                <div class="pr-6 border-r border-border-light">
                    <div class="flex items-center gap-1.5 mb-0.5">
                        <span class="text-[10px] font-body font-bold text-text-dim uppercase tracking-wide">Battery</span>
                        <span
                            v-if="isUsbPowered"
                            class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded-full text-[9px] font-body font-extrabold bg-green-bg text-green uppercase tracking-wide"
                        >
                            <svg viewBox="0 0 16 16" width="9" height="9" fill="currentColor"><path d="M9.5 1L4 9h4l-1.5 6L13 7H9l.5-6z"/></svg>
                            USB
                        </span>
                    </div>
                    <div class="text-[18px] font-sans font-bold tabular-nums" :class="batteryColor">
                        {{ live?.battery_percent != null ? live.battery_percent.toFixed(0) + '%' : '—' }}
                    </div>
                </div>

                <!-- CPU -->
                <div>
                    <div class="text-[10px] font-body font-bold text-text-dim uppercase tracking-wide mb-0.5">CPU</div>
                    <div class="text-[18px] font-sans font-bold tabular-nums" :class="cpuColor">
                        {{ live?.cpu_usage != null ? live.cpu_usage.toFixed(0) + '%' : '—' }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Signal + GPS + CPU charts -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-4">
            <!-- Signal Strength -->
            <div class="panel p-4">
                <div class="flex items-baseline justify-between mb-1">
                    <div class="text-[11px] font-body font-extrabold tracking-[2.5px] uppercase text-teal">Signal Strength</div>
                </div>
                <div class="text-[12px] font-body text-text-dim mb-3 tabular-nums">
                    LTE
                    <span class="text-scarlet font-sans font-semibold">{{ live?.lte_rssi != null ? live.lte_rssi.toFixed(0) + ' dBm' : '—' }}</span>
                    &nbsp;&middot;&nbsp;WiFi
                    <span class="font-sans font-semibold text-blue">{{ live?.wifi_rssi != null ? live.wifi_rssi.toFixed(0) + ' dBm' : '—' }}</span>
                </div>
                <Sparkline :data="lteHistoryValues" color="var(--color-scarlet)" :height="44" :fill="true" :showDot="true" />
                <div class="mt-2">
                    <Sparkline :data="wifiHistoryValues" color="var(--color-blue)" :height="32" :fill="false" :showDot="true" />
                </div>
                <div class="flex justify-between text-[10px] font-body text-text-dim mt-1">
                    <span>1h ago</span><span>now</span>
                </div>
                <Link href="/admin/explore?metric=lte_rssi&range=1h" class="text-[11px] font-body font-bold text-teal hover:underline mt-2 inline-block">Explore →</Link>
            </div>

            <!-- GPS Quality -->
            <div class="panel p-4">
                <div class="flex items-baseline justify-between mb-1">
                    <div class="text-[11px] font-body font-extrabold tracking-[2.5px] uppercase text-green">GPS Quality</div>
                </div>
                <div class="text-[12px] font-body text-text-dim mb-3 tabular-nums">
                    Satellites
                    <span class="text-green font-sans font-semibold">{{ liveGps?.satellites ?? '—' }}</span>
                    &nbsp;&middot;&nbsp;HDOP
                    <span class="font-sans font-semibold">{{ liveGps?.hdop != null ? liveGps.hdop.toFixed(1) : '—' }}</span>
                </div>
                <Sparkline :data="gpsHistoryValues" color="var(--color-green)" :height="44" :fill="true" :showDot="true" />
                <div class="flex justify-between text-[10px] font-body text-text-dim mt-1">
                    <span>1h ago</span><span>now</span>
                </div>
                <Link href="/admin/explore?metric=gps_satellites&range=1h" class="text-[11px] font-body font-bold text-teal hover:underline mt-2 inline-block">Explore →</Link>
            </div>

            <!-- CPU Usage -->
            <div class="panel p-4">
                <div class="flex items-baseline justify-between mb-1">
                    <div class="text-[11px] font-body font-extrabold tracking-[2.5px] uppercase text-amber">CPU Usage</div>
                </div>
                <div class="text-[12px] font-body text-text-dim mb-3 tabular-nums">
                    <span class="font-sans font-semibold text-amber">{{ live?.cpu_usage != null ? live.cpu_usage.toFixed(0) + '%' : '—' }}</span>
                </div>
                <Sparkline :data="cpuHistoryValues" color="var(--color-amber)" :height="44" :fill="true" :showDot="true" />
                <div class="flex justify-between text-[10px] font-body text-text-dim mt-1">
                    <span>1h ago</span><span>now</span>
                </div>
                <Link href="/admin/explore?metric=cpu_usage&range=1h" class="text-[11px] font-body font-bold text-teal hover:underline mt-2 inline-block">Explore →</Link>
            </div>
        </div>

        <!-- Temp + Humidity charts -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-5">
            <!-- Cabin Temperature -->
            <div class="panel p-4">
                <div class="flex items-baseline justify-between mb-1">
                    <div class="text-[11px] font-body font-extrabold tracking-[2.5px] uppercase text-amber">Cabin Temperature</div>
                    <span class="font-sans text-base font-semibold text-amber tabular-nums">
                        {{ live?.cabin_temp != null ? live.cabin_temp.toFixed(1) + '°C' : (props.tracker?.cabin_temp != null ? props.tracker.cabin_temp.toFixed(1) + '°C' : '—') }}
                    </span>
                </div>
                <div class="mt-3">
                    <Sparkline :data="tempHistoryValues" color="var(--color-amber)" :height="44" :fill="true" :showDot="true" />
                </div>
                <div class="flex justify-between text-[10px] font-body text-text-dim mt-1">
                    <span>6h ago</span><span>now</span>
                </div>
                <Link href="/admin/explore?metric=temp_forepeak&range=6h" class="text-[11px] font-body font-bold text-teal hover:underline mt-2 inline-block">Explore →</Link>
            </div>

            <!-- Humidity -->
            <div class="panel p-4">
                <div class="flex items-baseline justify-between mb-1">
                    <div class="text-[11px] font-body font-extrabold tracking-[2.5px] uppercase text-blue">Humidity</div>
                    <span class="font-sans text-base font-semibold text-blue tabular-nums">
                        {{ live?.cabin_humidity != null ? live.cabin_humidity.toFixed(0) + '%' : (props.tracker?.cabin_humidity != null ? props.tracker.cabin_humidity.toFixed(0) + '%' : '—') }}
                    </span>
                </div>
                <div class="mt-3">
                    <Sparkline :data="humidityHistoryValues" color="var(--color-blue)" :height="44" :fill="true" :showDot="true" />
                </div>
                <div class="flex justify-between text-[10px] font-body text-text-dim mt-1">
                    <span>6h ago</span><span>now</span>
                </div>
                <Link href="/admin/explore?metric=humidity_forepeak&range=6h" class="text-[11px] font-body font-bold text-teal hover:underline mt-2 inline-block">Explore →</Link>
            </div>
        </div>

        <!-- Device Details table -->
        <div class="panel p-4">
            <div class="text-[11px] font-body font-extrabold tracking-[2.5px] uppercase text-teal mb-4">Device Details</div>
            <table class="w-full text-[13px]">
                <tbody class="divide-y divide-border-light">
                    <tr>
                        <td class="py-2.5 font-body text-text-secondary w-1/3">WiFi RSSI</td>
                        <td class="py-2.5 font-sans font-semibold tabular-nums">{{ live?.wifi_rssi != null ? live.wifi_rssi.toFixed(0) + ' dBm' : '—' }}</td>
                    </tr>
                    <tr>
                        <td class="py-2.5 font-body text-text-secondary">LTE RSSI</td>
                        <td class="py-2.5 font-sans font-semibold tabular-nums">{{ live?.lte_rssi != null ? live.lte_rssi.toFixed(0) + ' dBm' : '—' }}</td>
                    </tr>
                    <tr>
                        <td class="py-2.5 font-body text-text-secondary">GPS Position</td>
                        <td class="py-2.5 font-sans font-semibold tabular-nums">
                            <span v-if="liveGps?.latitude != null && liveGps?.longitude != null">
                                {{ liveGps.latitude.toFixed(5) }}, {{ liveGps.longitude.toFixed(5) }}
                            </span>
                            <span v-else>—</span>
                        </td>
                    </tr>
                    <tr>
                        <td class="py-2.5 font-body text-text-secondary">GPS Satellites</td>
                        <td class="py-2.5 font-sans font-semibold tabular-nums">{{ liveGps?.satellites ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td class="py-2.5 font-body text-text-secondary">HDOP</td>
                        <td class="py-2.5 font-sans font-semibold tabular-nums">{{ liveGps?.hdop != null ? liveGps.hdop.toFixed(1) : '—' }}</td>
                    </tr>
                    <tr>
                        <td class="py-2.5 font-body text-text-secondary">Power Source</td>
                        <td class="py-2.5 font-sans font-semibold">
                            <span v-if="isUsbPowered" class="text-green">USB</span>
                            <span v-else>Battery</span>
                        </td>
                    </tr>
                    <tr>
                        <td class="py-2.5 font-body text-text-secondary">CPU Usage</td>
                        <td class="py-2.5 font-sans font-semibold tabular-nums">{{ live?.cpu_usage != null ? live.cpu_usage.toFixed(0) + '%' : '—' }}</td>
                    </tr>
                    <tr>
                        <td class="py-2.5 font-body text-text-secondary">Heap Free</td>
                        <td class="py-2.5 font-sans font-semibold tabular-nums">{{ live?.heap_free != null ? formatBytes(live.heap_free) : '—' }}</td>
                    </tr>
                    <tr>
                        <td class="py-2.5 font-body text-text-secondary">Uptime</td>
                        <td class="py-2.5 font-sans font-semibold tabular-nums">{{ formatUptime(live?.uptime) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Sparkline from '@/Components/Admin/Sparkline.vue';
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
if (typeof window !== 'undefined' && window.Echo) {
    echoChannel = window.Echo.channel('metrics');
    echoChannel.listen('.metrics.updated', (data) => {
        metrics.value = data;
        lastUpdate.value = Date.now();
    });
}

const live = computed(() => metrics.value?.tracker ?? props.tracker);
const liveGps = computed(() => metrics.value?.gps ?? props.gps);

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

const lteHistoryValues = computed(() => (props.signalHistory?.lte ?? []).map(d => d.value));
const wifiHistoryValues = computed(() => (props.signalHistory?.wifi ?? []).map(d => d.value));
const gpsHistoryValues = computed(() => (props.gpsHistory ?? []).map(d => d.value));
const cpuHistoryValues = computed(() => (props.cpuHistory ?? []).map(d => d.value));
const tempHistoryValues = computed(() => (props.tempHistory ?? []).map(d => d.value));
const humidityHistoryValues = computed(() => (props.humidityHistory ?? []).map(d => d.value));

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
