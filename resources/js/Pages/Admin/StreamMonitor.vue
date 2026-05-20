<template>
    <AdminLayout>
        <Head title="Stream Monitor" />
        <div class="flex items-baseline justify-between mb-6">
            <h1 class="text-[22px] font-bold">Stream Monitor</h1>
            <div class="flex items-center gap-2 text-[13px] text-text-dim">
                <span class="w-2 h-2 rounded-full inline-block" :class="isConnected ? 'bg-green' : 'opacity-30 bg-text-dim'"></span>
                <span class="tabular-nums">{{ refreshLabel }}</span>
            </div>
        </div>

        <div v-if="!statsUrl" class="bg-surface border border-border rounded-[10px] p-6 text-center">
            <p class="text-text-secondary text-[14px] mb-2">No SRT stats URL configured.</p>
            <a href="/admin/settings" class="text-scarlet text-[13px] font-semibold hover:underline">Configure in Settings</a>
        </div>

        <template v-else>
            <!-- Publisher status strip -->
            <div class="grid grid-cols-4 gap-4 mb-6">
                <div class="bg-surface border border-border rounded-[10px] p-4">
                    <div class="text-[11px] font-semibold text-text-dim uppercase tracking-wide mb-2">Publisher</div>
                    <div class="mb-1">
                        <span
                            class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[12px] font-semibold"
                            :class="isConnected ? 'bg-green-bg text-green' : 'bg-amber-bg text-amber'"
                        >
                            <span class="w-1.5 h-1.5 rounded-full inline-block" :class="isConnected ? 'bg-green' : 'bg-amber'"></span>
                            {{ isConnected ? 'Connected' : 'Disconnected' }}
                        </span>
                    </div>
                </div>

                <div class="bg-surface border border-border rounded-[10px] p-4">
                    <div class="text-[11px] font-semibold text-text-dim uppercase tracking-wide mb-2">Bitrate</div>
                    <div class="text-[24px] font-bold tabular-nums leading-none mb-1" :class="isConnected ? 'text-scarlet' : 'text-text-dim'">
                        {{ formatBitrate(publisher?.bitrate) }}
                    </div>
                    <div class="text-[11px] text-text-dim">Mbps</div>
                </div>

                <div class="bg-surface border border-border rounded-[10px] p-4">
                    <div class="text-[11px] font-semibold text-text-dim uppercase tracking-wide mb-2">RTT</div>
                    <div class="text-[24px] font-bold tabular-nums leading-none mb-1" style="color: oklch(0.55 0.15 240)">
                        {{ publisher?.rtt != null ? Number(publisher.rtt).toFixed(1) : '—' }}
                    </div>
                    <div class="text-[11px] text-text-dim">ms</div>
                </div>

                <div class="bg-surface border border-border rounded-[10px] p-4">
                    <div class="text-[11px] font-semibold text-text-dim uppercase tracking-wide mb-2">Dropped</div>
                    <div class="text-[24px] font-bold tabular-nums leading-none mb-1" :class="(publisher?.dropped_pkts ?? 0) > 0 ? 'text-amber' : ''">
                        {{ publisher?.dropped_pkts ?? 0 }}
                    </div>
                    <div class="text-[11px] text-text-dim">packets</div>
                </div>
            </div>

            <!-- Bitrate chart -->
            <div class="bg-surface border border-border rounded-[10px] p-4 mb-4">
                <div class="flex items-baseline justify-between mb-3">
                    <div>
                        <div class="text-[15px] font-semibold">Bitrate</div>
                        <div class="text-[12px] text-text-dim tabular-nums">
                            <span class="text-scarlet font-medium">{{ formatBitrate(publisher?.bitrate) }} Mbps</span>
                            &nbsp;current
                        </div>
                    </div>
                    <div class="text-[11px] text-text-dim tabular-nums">{{ historyWindow(bitrateHistory) }}</div>
                </div>
                <svg viewBox="0 0 400 120" class="w-full" preserveAspectRatio="none">
                    <defs>
                        <linearGradient id="bitrateGrad" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="oklch(0.54 0.22 27)" stop-opacity="0.20"/>
                            <stop offset="100%" stop-color="oklch(0.54 0.22 27)" stop-opacity="0.02"/>
                        </linearGradient>
                    </defs>
                    <polygon v-if="bitrateHistory.length > 1" :points="toArea(bitrateValues, bitrateMax)" fill="url(#bitrateGrad)" />
                    <polyline v-if="bitrateHistory.length > 1" :points="toLine(bitrateValues, bitrateMax)" fill="none" stroke="oklch(0.54 0.22 27)" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round" />
                    <text v-if="bitrateHistory.length < 2" x="200" y="65" text-anchor="middle" font-size="12" fill="oklch(0.70 0.005 40)">Waiting for data…</text>
                </svg>
                <div class="flex justify-between text-[10px] text-text-dim mt-1">
                    <span>{{ historyWindow(bitrateHistory) }} ago</span><span>now</span>
                </div>
            </div>

            <!-- RTT + Dropped charts side by side -->
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div class="bg-surface border border-border rounded-[10px] p-4">
                    <div class="text-[15px] font-semibold mb-0.5">Round Trip Time</div>
                    <div class="text-[12px] text-text-dim mb-3 tabular-nums">
                        <span style="color: oklch(0.55 0.15 240)" class="font-medium">{{ publisher?.rtt != null ? Number(publisher.rtt).toFixed(1) + ' ms' : '—' }}</span>
                        &nbsp;current
                    </div>
                    <svg viewBox="0 0 400 120" class="w-full" preserveAspectRatio="none">
                        <defs>
                            <linearGradient id="rttGrad" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="oklch(0.55 0.15 240)" stop-opacity="0.20"/>
                                <stop offset="100%" stop-color="oklch(0.55 0.15 240)" stop-opacity="0.02"/>
                            </linearGradient>
                        </defs>
                        <polygon v-if="rttHistory.length > 1" :points="toArea(rttValues, rttMax)" fill="url(#rttGrad)" />
                        <polyline v-if="rttHistory.length > 1" :points="toLine(rttValues, rttMax)" fill="none" stroke="oklch(0.55 0.15 240)" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round" />
                        <text v-if="rttHistory.length < 2" x="200" y="65" text-anchor="middle" font-size="12" fill="oklch(0.70 0.005 40)">Waiting for data…</text>
                    </svg>
                    <div class="flex justify-between text-[10px] text-text-dim mt-1">
                        <span>{{ historyWindow(rttHistory) }} ago</span><span>now</span>
                    </div>
                </div>

                <div class="bg-surface border border-border rounded-[10px] p-4">
                    <div class="text-[15px] font-semibold mb-0.5">Dropped Packets</div>
                    <div class="text-[12px] text-text-dim mb-3 tabular-nums">
                        <span class="font-medium" :class="(publisher?.dropped_pkts ?? 0) > 0 ? 'text-amber' : 'text-green'">{{ publisher?.dropped_pkts ?? 0 }}</span>
                        &nbsp;total
                    </div>
                    <svg viewBox="0 0 400 120" class="w-full" preserveAspectRatio="none">
                        <defs>
                            <linearGradient id="dropGrad" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="oklch(0.75 0.15 65)" stop-opacity="0.20"/>
                                <stop offset="100%" stop-color="oklch(0.75 0.15 65)" stop-opacity="0.02"/>
                            </linearGradient>
                        </defs>
                        <polygon v-if="droppedHistory.length > 1" :points="toArea(droppedValues, droppedMax)" fill="url(#dropGrad)" />
                        <polyline v-if="droppedHistory.length > 1" :points="toLine(droppedValues, droppedMax)" fill="none" stroke="oklch(0.75 0.15 65)" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round" />
                        <text v-if="droppedHistory.length < 2" x="200" y="65" text-anchor="middle" font-size="12" fill="oklch(0.70 0.005 40)">Waiting for data…</text>
                    </svg>
                    <div class="flex justify-between text-[10px] text-text-dim mt-1">
                        <span>{{ historyWindow(droppedHistory) }} ago</span><span>now</span>
                    </div>
                </div>
            </div>

            <!-- Publisher detail -->
            <div class="grid grid-cols-2 gap-4 mb-6">
                <div class="bg-surface border border-border rounded-[10px] p-4">
                    <div class="text-[11px] font-semibold text-text-dim uppercase tracking-wide mb-2">Latency</div>
                    <div class="text-[20px] font-bold tabular-nums leading-none mb-1">
                        {{ publisher?.latency != null ? Number(publisher.latency).toFixed(0) : '—' }}
                    </div>
                    <div class="text-[11px] text-text-dim">ms</div>
                </div>

                <div class="bg-surface border border-border rounded-[10px] p-4">
                    <div class="text-[11px] font-semibold text-text-dim uppercase tracking-wide mb-2">Network</div>
                    <div class="text-[20px] font-bold tabular-nums leading-none mb-1">
                        {{ formatBitrate(publisher?.network) }}
                    </div>
                    <div class="text-[11px] text-text-dim">Mbps</div>
                </div>
            </div>
        </template>
    </AdminLayout>
</template>

<script setup>
import { computed, onMounted, onUnmounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    statsUrl: String,
    publisher: Object,
    bitrateHistory: Array,
    rttHistory: Array,
    droppedHistory: Array,
});

let refreshTimer = null;
let tickTimer = null;

const isConnected = computed(() => (props.publisher?.connected ?? 0) >= 1);

const bitrateValues = computed(() => (props.bitrateHistory ?? []).map(p => p.value));
const rttValues = computed(() => (props.rttHistory ?? []).map(p => p.value));
const droppedValues = computed(() => (props.droppedHistory ?? []).map(p => p.value));

const bitrateMax = computed(() => {
    const vals = bitrateValues.value;
    if (!vals.length) return 10;
    return Math.max(...vals, 0.1) * 1.15;
});

const rttMax = computed(() => {
    const vals = rttValues.value;
    if (!vals.length) return 50;
    return Math.max(...vals, 1) * 1.15;
});

const droppedMax = computed(() => {
    const vals = droppedValues.value;
    if (!vals.length) return 10;
    return Math.max(...vals, 1) * 1.15;
});

const refreshLabel = computed(() => {
    if (!props.statsUrl) return '';
    return isConnected.value ? 'live' : 'polling';
});

function historyWindow(history) {
    if (!history || history.length < 2) return '—';
    const span = history[history.length - 1].timestamp - history[0].timestamp;
    if (span < 120) return `${span}s`;
    return `${Math.round(span / 60)}m`;
}

function formatBitrate(bps) {
    if (bps == null || bps === 0) return '0.00';
    return (bps / 1_000_000).toFixed(2);
}

function toLine(data, max) {
    if (!data.length) return '';
    const h = 120, w = 400;
    const m = max || 1;
    return data.map((v, i) => {
        const x = (i / (data.length - 1)) * w;
        const y = h - (v / m) * (h - 10) - 5;
        return `${x},${y}`;
    }).join(' ');
}

function toArea(data, max) {
    if (!data.length) return '';
    const h = 120, w = 400;
    return `0,${h} ${toLine(data, max)} ${w},${h}`;
}

function refresh() {
    router.reload({ only: ['publisher', 'bitrateHistory', 'rttHistory', 'droppedHistory'], preserveScroll: true });
}

onMounted(() => {
    refreshTimer = setInterval(refresh, 15000);
});

onUnmounted(() => {
    clearInterval(refreshTimer);
});
</script>
