<template>
    <AdminLayout :wide="true">
        <Head title="Broadcast" />

        <!-- Status Ribbon -->
        <div class="flex items-center p-3 px-6 bg-surface border border-border rounded-[14px] mb-4 shadow-sm gap-3">
            <div class="relative flex-shrink-0 w-[8px] h-[8px] flex items-center justify-center">
                <span
                    v-if="isConnected"
                    class="absolute inset-[-4px] rounded-full bg-green opacity-30"
                    style="animation: status-pulse 1.4s ease-out infinite;"
                ></span>
                <span
                    class="w-[8px] h-[8px] rounded-full inline-block"
                    :class="isConnected ? 'bg-green' : 'bg-text-dim opacity-30'"
                ></span>
            </div>
            <span class="text-sm font-semibold" :class="isConnected ? 'text-green' : 'text-text-dim'">
                {{ isConnected ? 'Live' : 'Offline' }}
            </span>
            <span class="text-sm text-text-secondary">
                <template v-if="isConnected">
                    <span class="text-text-dim">&middot;</span>
                    Latency {{ publisher?.latency != null ? Number(publisher.latency).toFixed(0) + ' ms' : '—' }}
                    <span class="text-text-dim">&middot;</span>
                    Network {{ formatBitrate(publisher?.network) }} Mbps
                </template>
                <template v-else>
                    <span class="text-text-dim">&middot; No publisher connected</span>
                </template>
            </span>
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
                <!-- Bitrate -->
                <div class="panel flex-1 flex flex-col">
                    <div class="p-3 px-4.5 flex items-baseline justify-between">
                        <div class="text-[11px] font-body font-extrabold tracking-[2.5px] uppercase text-teal">Bitrate</div>
                        <span class="font-sans text-[22px] font-semibold text-teal tabular-nums">
                            {{ formatBitrate(publisher?.bitrate) }} <span class="text-[13px] font-normal">Mbps</span>
                        </span>
                    </div>
                    <div class="px-4.5 pb-3.5 flex-1 flex flex-col justify-end">
                        <Sparkline :data="bitrateValues" color="var(--color-teal)" :height="36" :fill="true" :showDot="true" />
                    </div>
                </div>

                <!-- Dropped Packets -->
                <div class="panel flex-1 flex flex-col">
                    <div class="p-3 px-4.5 flex items-baseline justify-between">
                        <div class="text-[11px] font-body font-extrabold tracking-[2.5px] uppercase text-scarlet">Dropped Packets</div>
                        <span class="font-sans text-[22px] font-semibold tabular-nums" :class="(publisher?.dropped_pkts ?? 0) > 0 ? 'text-scarlet' : 'text-green'">
                            {{ publisher?.dropped_pkts ?? 0 }}
                        </span>
                    </div>
                    <div class="px-4.5 pb-3.5 flex-1 flex flex-col justify-end">
                        <Sparkline :data="droppedValues" color="var(--color-scarlet)" :height="36" :fill="false" :showDot="true" />
                    </div>
                </div>

                <!-- Round Trip -->
                <div class="panel flex-1 flex flex-col">
                    <div class="p-3 px-4.5 flex items-baseline justify-between">
                        <div class="text-[11px] font-body font-extrabold tracking-[2.5px] uppercase text-amber">Round Trip</div>
                        <span class="font-sans text-[22px] font-semibold text-amber tabular-nums">
                            {{ publisher?.rtt != null ? Number(publisher.rtt).toFixed(1) : '—' }} <span class="text-[13px] font-normal">ms</span>
                        </span>
                    </div>
                    <div class="px-4.5 pb-3.5 flex-1 flex flex-col justify-end">
                        <Sparkline :data="rttValues" color="var(--color-amber)" :height="36" :fill="true" :showDot="true" />
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
