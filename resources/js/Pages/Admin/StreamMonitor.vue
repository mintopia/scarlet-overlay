<template>
    <AdminLayout>
        <Head title="Stream Monitor" />
        <div class="flex items-baseline justify-between mb-6">
            <h1 class="text-[22px] font-bold">Stream Monitor</h1>
            <div class="flex items-center gap-2 text-[13px] text-text-dim">
                <span class="w-2 h-2 rounded-full inline-block" :class="connected ? 'bg-green' : 'opacity-30 bg-text-dim'"></span>
                <span class="tabular-nums">{{ timeSinceUpdate }}</span>
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
                            :class="connected ? 'bg-green-bg text-green' : 'bg-amber-bg text-amber'"
                        >
                            <span class="w-1.5 h-1.5 rounded-full inline-block" :class="connected ? 'bg-green' : 'bg-amber'"></span>
                            {{ connected ? 'Connected' : 'Disconnected' }}
                        </span>
                    </div>
                    <div class="text-[13px] text-text-secondary tabular-nums">{{ publisherName }}</div>
                </div>

                <div class="bg-surface border border-border rounded-[10px] p-4">
                    <div class="text-[11px] font-semibold text-text-dim uppercase tracking-wide mb-2">Bitrate</div>
                    <div class="text-[24px] font-bold tabular-nums leading-none mb-1" :class="connected ? 'text-scarlet' : 'text-text-dim'">
                        {{ formatBitrate(publisher?.bitrate) }}
                    </div>
                    <div class="text-[11px] text-text-dim">Mbps</div>
                </div>

                <div class="bg-surface border border-border rounded-[10px] p-4">
                    <div class="text-[11px] font-semibold text-text-dim uppercase tracking-wide mb-2">RTT</div>
                    <div class="text-[24px] font-bold tabular-nums leading-none mb-1" style="color: oklch(0.55 0.15 240)">
                        {{ publisher?.rtt != null ? publisher.rtt.toFixed(1) : '—' }}
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
                    <div class="text-[11px] text-text-dim tabular-nums">{{ historyWindow }}</div>
                </div>
                <svg viewBox="0 0 400 120" class="w-full" preserveAspectRatio="none">
                    <defs>
                        <linearGradient id="bitrateGrad" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="oklch(0.54 0.22 27)" stop-opacity="0.20"/>
                            <stop offset="100%" stop-color="oklch(0.54 0.22 27)" stop-opacity="0.02"/>
                        </linearGradient>
                    </defs>
                    <polygon v-if="bitrateHistory.length > 1" :points="toArea(bitrateHistory, bitrateMax)" fill="url(#bitrateGrad)" />
                    <polyline v-if="bitrateHistory.length > 1" :points="toLine(bitrateHistory, bitrateMax)" fill="none" stroke="oklch(0.54 0.22 27)" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round" />
                    <text v-if="bitrateHistory.length < 2" x="200" y="65" text-anchor="middle" font-size="12" fill="oklch(0.70 0.005 40)">Collecting data…</text>
                </svg>
                <div class="flex justify-between text-[10px] text-text-dim mt-1">
                    <span>{{ historyWindow }} ago</span><span>now</span>
                </div>
            </div>

            <!-- RTT + Dropped charts side by side -->
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div class="bg-surface border border-border rounded-[10px] p-4">
                    <div class="text-[15px] font-semibold mb-0.5">Round Trip Time</div>
                    <div class="text-[12px] text-text-dim mb-3 tabular-nums">
                        <span style="color: oklch(0.55 0.15 240)" class="font-medium">{{ publisher?.rtt != null ? publisher.rtt.toFixed(1) + ' ms' : '—' }}</span>
                        &nbsp;current
                    </div>
                    <svg viewBox="0 0 400 120" class="w-full" preserveAspectRatio="none">
                        <defs>
                            <linearGradient id="rttGrad" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="oklch(0.55 0.15 240)" stop-opacity="0.20"/>
                                <stop offset="100%" stop-color="oklch(0.55 0.15 240)" stop-opacity="0.02"/>
                            </linearGradient>
                        </defs>
                        <polygon v-if="rttHistory.length > 1" :points="toArea(rttHistory, rttMax)" fill="url(#rttGrad)" />
                        <polyline v-if="rttHistory.length > 1" :points="toLine(rttHistory, rttMax)" fill="none" stroke="oklch(0.55 0.15 240)" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round" />
                        <text v-if="rttHistory.length < 2" x="200" y="65" text-anchor="middle" font-size="12" fill="oklch(0.70 0.005 40)">Collecting data…</text>
                    </svg>
                    <div class="flex justify-between text-[10px] text-text-dim mt-1">
                        <span>{{ historyWindow }} ago</span><span>now</span>
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
                        <polygon v-if="droppedHistory.length > 1" :points="toArea(droppedHistory, droppedMax)" fill="url(#dropGrad)" />
                        <polyline v-if="droppedHistory.length > 1" :points="toLine(droppedHistory, droppedMax)" fill="none" stroke="oklch(0.75 0.15 65)" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round" />
                        <text v-if="droppedHistory.length < 2" x="200" y="65" text-anchor="middle" font-size="12" fill="oklch(0.70 0.005 40)">Collecting data…</text>
                    </svg>
                    <div class="flex justify-between text-[10px] text-text-dim mt-1">
                        <span>{{ historyWindow }} ago</span><span>now</span>
                    </div>
                </div>
            </div>

            <!-- Publisher detail -->
            <div class="grid grid-cols-2 gap-4 mb-6">
                <div class="bg-surface border border-border rounded-[10px] p-4">
                    <div class="text-[11px] font-semibold text-text-dim uppercase tracking-wide mb-2">Latency</div>
                    <div class="text-[20px] font-bold tabular-nums leading-none mb-1">
                        {{ publisher?.latency != null ? publisher.latency : '—' }}
                    </div>
                    <div class="text-[11px] text-text-dim">ms</div>
                </div>

                <div class="bg-surface border border-border rounded-[10px] p-4">
                    <div class="text-[11px] font-semibold text-text-dim uppercase tracking-wide mb-2">Network</div>
                    <div class="text-[20px] font-bold tabular-nums leading-none mb-1">
                        {{ publisher?.network != null ? publisher.network : '—' }}
                    </div>
                    <div class="text-[11px] text-text-dim">bytes</div>
                </div>
            </div>

            <!-- Consumers -->
            <div v-if="consumers.length" class="mb-4">
                <h2 class="text-[15px] font-semibold mb-3">Relay Servers</h2>
                <div class="grid grid-cols-1 gap-3">
                    <div v-for="(c, i) in consumers" :key="i" class="bg-surface border border-border rounded-[10px] p-4">
                        <div class="flex items-center justify-between mb-2">
                            <div class="text-[13px] font-semibold">{{ c.server || 'Consumer ' + (i + 1) }}</div>
                            <span
                                class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-semibold"
                                :class="c.bitrate > 0 ? 'bg-green-bg text-green' : 'bg-surface text-text-dim'"
                            >
                                <span class="w-1.5 h-1.5 rounded-full inline-block" :class="c.bitrate > 0 ? 'bg-green' : 'bg-text-dim opacity-40'"></span>
                                {{ c.bitrate > 0 ? 'Active' : 'Idle' }}
                            </span>
                        </div>
                        <div class="grid grid-cols-4 gap-4 text-[13px] tabular-nums">
                            <div>
                                <div class="text-[11px] text-text-dim">Bitrate</div>
                                <div class="font-medium">{{ formatBitrate(c.bitrate) }} Mbps</div>
                            </div>
                            <div>
                                <div class="text-[11px] text-text-dim">RTT</div>
                                <div class="font-medium">{{ c.rtt?.toFixed(1) ?? '—' }} ms</div>
                            </div>
                            <div>
                                <div class="text-[11px] text-text-dim">Latency</div>
                                <div class="font-medium">{{ c.latency ?? '—' }} ms</div>
                            </div>
                            <div>
                                <div class="text-[11px] text-text-dim">Dropped</div>
                                <div class="font-medium" :class="c.dropped_pkts > 0 ? 'text-amber' : ''">{{ c.dropped_pkts ?? 0 }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    statsUrl: String,
});

const publisher = ref(null);
const publisherName = ref('—');
const consumers = ref([]);
const connected = ref(false);
const lastUpdateTime = ref(null);
const timeSinceUpdate = ref('waiting…');
const error = ref(null);

const MAX_HISTORY = 120;
const bitrateHistory = ref([]);
const rttHistory = ref([]);
const droppedHistory = ref([]);

let pollTimer = null;
let tickTimer = null;

const bitrateMax = computed(() => {
    const vals = bitrateHistory.value;
    if (!vals.length) return 10;
    return Math.max(...vals, 1) * 1.15;
});

const rttMax = computed(() => {
    const vals = rttHistory.value;
    if (!vals.length) return 50;
    return Math.max(...vals, 1) * 1.15;
});

const droppedMax = computed(() => {
    const vals = droppedHistory.value;
    if (!vals.length) return 10;
    return Math.max(...vals, 1) * 1.15;
});

const historyWindow = computed(() => {
    const n = bitrateHistory.value.length;
    if (n < 2) return '—';
    const secs = n * 3;
    if (secs < 120) return `${secs}s`;
    return `${Math.round(secs / 60)}m`;
});

function formatBitrate(bps) {
    if (bps == null || bps === 0) return '0.00';
    return (bps / 1_000_000).toFixed(2);
}

function pushHistory(arr, val) {
    arr.push(val);
    if (arr.length > MAX_HISTORY) arr.shift();
}

async function poll() {
    if (!props.statsUrl) return;

    try {
        const res = await fetch('/admin/stream/stats');
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const data = await res.json();

        const pubs = data.publishers ?? {};
        const name = Object.keys(pubs)[0] ?? null;
        publisherName.value = name ?? '—';
        publisher.value = name ? pubs[name] : null;
        connected.value = publisher.value?.connected ?? false;
        consumers.value = data.consumers ?? [];
        lastUpdateTime.value = Date.now();
        error.value = null;

        pushHistory(bitrateHistory.value, (publisher.value?.bitrate ?? 0) / 1_000_000);
        pushHistory(rttHistory.value, publisher.value?.rtt ?? 0);
        pushHistory(droppedHistory.value, publisher.value?.dropped_pkts ?? 0);
    } catch (e) {
        error.value = e.message;
    }
}

function updateTimeSince() {
    if (!lastUpdateTime.value) {
        timeSinceUpdate.value = 'waiting…';
        return;
    }
    const secs = Math.round((Date.now() - lastUpdateTime.value) / 1000);
    timeSinceUpdate.value = secs < 5 ? 'just now' : `${secs}s ago`;
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

onMounted(() => {
    poll();
    pollTimer = setInterval(poll, 3000);
    tickTimer = setInterval(updateTimeSince, 1000);
});

onUnmounted(() => {
    clearInterval(pollTimer);
    clearInterval(tickTimer);
});
</script>
