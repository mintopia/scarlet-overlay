<template>
    <AdminLayout>
        <Head title="Broadcast" />

        <!-- Header -->
        <div class="flex justify-between items-center mb-5">
            <h1 class="font-sans text-2xl font-extrabold tracking-tight">Broadcast</h1>
            <div class="flex gap-4">
                <a href="/overlay" target="_blank" class="text-[11px] font-body font-bold text-teal hover:underline">Overlay →</a>
                <a href="/dashboard" target="_blank" class="text-[11px] font-body font-bold text-teal hover:underline">Public Dashboard →</a>
            </div>
        </div>

        <!-- Status Hero -->
        <div class="panel p-5 flex items-center gap-5 mb-4">
            <!-- Pulsing status indicator -->
            <div class="relative flex-shrink-0 w-[32px] h-[32px] flex items-center justify-center">
                <span
                    v-if="isConnected"
                    class="absolute inset-0 rounded-full bg-green opacity-30"
                    style="animation: status-pulse 1.4s ease-out infinite;"
                ></span>
                <span
                    class="w-[32px] h-[32px] rounded-full inline-block"
                    :class="isConnected ? 'bg-green' : 'bg-text-dim opacity-30'"
                ></span>
            </div>

            <!-- Status text + connection details -->
            <div class="flex-1 min-w-0">
                <div class="text-[22px] font-sans font-extrabold leading-none mb-1" :class="isConnected ? 'text-green' : 'text-text-dim'">
                    {{ isConnected ? 'Live' : 'Offline' }}
                </div>
                <div class="text-[13px] font-body text-text-dim">
                    <template v-if="isConnected">
                        Latency {{ publisher?.latency != null ? Number(publisher.latency).toFixed(0) + ' ms' : '—' }}
                        &nbsp;&middot;&nbsp;
                        Network {{ formatBitrate(publisher?.network) }} Mbps
                    </template>
                    <template v-else>
                        No publisher connected
                    </template>
                </div>
            </div>

            <!-- At-a-glance stats -->
            <div class="flex gap-6 flex-shrink-0">
                <div class="text-right">
                    <div class="text-[11px] font-body font-extrabold tracking-[2px] uppercase text-text-dim mb-0.5">Bitrate</div>
                    <div class="text-[20px] font-sans font-semibold tabular-nums" :class="isConnected ? 'text-teal' : 'text-text-dim'">
                        {{ formatBitrate(publisher?.bitrate) }} <span class="text-[13px] font-normal">Mbps</span>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-[11px] font-body font-extrabold tracking-[2px] uppercase text-text-dim mb-0.5">Drops</div>
                    <div class="text-[20px] font-sans font-semibold tabular-nums" :class="(publisher?.dropped_pkts ?? 0) > 0 ? 'text-amber' : (isConnected ? 'text-green' : 'text-text-dim')">
                        {{ publisher?.dropped_pkts ?? 0 }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Main: Video + Stats -->
        <div class="grid grid-cols-[1fr_320px] gap-4">
            <!-- Left: 16:9 video -->
            <div class="panel">
                <div class="aspect-video bg-[oklch(0.12_0.01_205)] rounded-[15px] overflow-hidden">
                    <video
                        v-if="isConnected"
                        ref="videoEl"
                        class="w-full h-full object-cover"
                        autoplay
                        muted
                        playsinline
                    />
                    <div v-else class="w-full h-full flex flex-col items-center justify-center text-text-dim">
                        <svg width="48" height="48" viewBox="0 0 48 48" opacity="0.3">
                            <polygon points="18,12 18,36 38,24" fill="currentColor"/>
                        </svg>
                        <span class="text-sm text-text-dim mt-2">No stream</span>
                    </div>
                </div>
            </div>

            <!-- Right: 3 stacked stat panels -->
            <div class="flex flex-col gap-4">
                <!-- Bitrate panel (tallest — primary metric) -->
                <div class="panel flex-[2] flex flex-col">
                    <div class="p-3 px-4.5 flex items-baseline justify-between">
                        <div class="text-[11px] font-body font-extrabold tracking-[2.5px] uppercase text-teal">Bitrate</div>
                        <span class="font-sans text-[22px] font-semibold text-teal tabular-nums">
                            {{ formatBitrate(publisher?.bitrate) }} <span class="text-[13px] font-normal">Mbps</span>
                        </span>
                    </div>
                    <div class="px-4.5 pb-3.5 flex-1 flex flex-col justify-end">
                        <Sparkline :data="bitrateValues" color="var(--color-teal)" :height="44" :fill="true" :showDot="true" />
                    </div>
                </div>

                <!-- Drops panel (compact — counter with context) -->
                <div class="panel flex flex-col">
                    <div class="p-3 px-4.5 flex items-baseline justify-between">
                        <div class="text-[11px] font-body font-extrabold tracking-[2.5px] uppercase text-scarlet">Dropped Packets</div>
                        <span class="font-sans text-[22px] font-semibold tabular-nums" :class="(publisher?.dropped_pkts ?? 0) > 0 ? 'text-scarlet' : 'text-green'">
                            {{ publisher?.dropped_pkts ?? 0 }}
                        </span>
                    </div>
                    <div class="px-4.5 pb-3.5">
                        <Sparkline :data="droppedValues" color="var(--color-scarlet)" :height="28" :fill="false" :showDot="true" />
                    </div>
                </div>

                <!-- RTT panel (with latency detail row) -->
                <div class="panel flex flex-col">
                    <div class="p-3 px-4.5 flex items-baseline justify-between">
                        <div class="text-[11px] font-body font-extrabold tracking-[2.5px] uppercase text-amber">Round Trip</div>
                        <span class="font-sans text-[22px] font-semibold text-amber tabular-nums">
                            {{ publisher?.rtt != null ? Number(publisher.rtt).toFixed(1) : '—' }} <span class="text-[13px] font-normal">ms</span>
                        </span>
                    </div>
                    <div class="px-4.5 pb-3.5 flex flex-col gap-2">
                        <Sparkline :data="rttValues" color="var(--color-amber)" :height="32" :fill="true" :showDot="true" />
                        <div class="flex justify-between text-[12px] font-body">
                            <span class="text-text-dim">Latency</span>
                            <span class="font-sans font-semibold tabular-nums">{{ publisher?.latency != null ? Number(publisher.latency).toFixed(0) + ' ms' : '—' }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Sparkline from '@/Components/Admin/Sparkline.vue';
import { useVideoFeed } from '@/composables/useVideoFeed.js';

const props = defineProps({
    statsUrl: String,
    fetchError: Boolean,
    publisher: Object,
    bitrateHistory: Array,
    rttHistory: Array,
    droppedHistory: Array,
    hlsUrl: String,
});

const videoEl = ref(null);
const { connect, cleanup } = useVideoFeed(videoEl);

const isConnected = computed(() => (props.publisher?.connected ?? 0) >= 1);

const bitrateValues = computed(() => (props.bitrateHistory ?? []).map(h => h.value));
const rttValues = computed(() => (props.rttHistory ?? []).map(h => h.value));
const droppedValues = computed(() => (props.droppedHistory ?? []).map(h => h.value));

/**
 * Format bps to Mbps string.
 * @param {number|null} bps
 * @returns {string}
 */
function formatBitrate(bps) {
    if (bps == null || bps === 0) return '0.0';
    return (bps / 1_000_000).toFixed(1);
}

// Start/stop video feed when connection state changes
watch(isConnected, (connected) => {
    if (connected) {
        // Wait a tick for video element to mount
        setTimeout(() => connect(), 50);
    } else {
        cleanup();
    }
});

let refreshTimer = null;

onMounted(() => {
    if (isConnected.value) {
        connect();
    }
    refreshTimer = setInterval(() => router.reload({ preserveState: true }), 15000);
});

onUnmounted(() => {
    clearInterval(refreshTimer);
    cleanup();
});
</script>

<style scoped>
@keyframes status-pulse {
    0%   { transform: scale(0.9); opacity: 1; }
    100% { transform: scale(1.5); opacity: 0; }
}
</style>
