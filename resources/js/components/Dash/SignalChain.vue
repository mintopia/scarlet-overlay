<template>
    <div class="sc-signal-chain">
        <!-- Header -->
        <div class="sc-rhead">
            <span class="sc-seclabel">Broadcast</span>
            <div class="sc-actions">
                <button
                    class="btn btn--danger"
                    type="button"
                    :disabled="streamPending"
                    @click="showStreamModal = true"
                >
                    <!-- Stop icon -->
                    <svg v-if="pullEnabled" viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><rect x="6" y="6" width="12" height="12" rx="1"/></svg>
                    <!-- Play icon -->
                    <svg v-else viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                    {{ pullEnabled ? 'Stop Stream' : 'Start Stream' }}
                </button>
                <button
                    class="btn btn--ghost"
                    type="button"
                    :disabled="reloadPending"
                    @click="showReloadModal = true"
                >
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M21 12a9 9 0 1 1-3-6.7"/><path d="M21 4v5h-5"/></svg>
                    Refresh Overlay
                </button>
            </div>
        </div>

        <!-- Signal chain nodes -->
        <div class="sc-hero">
            <div class="sc-chain">
                <!-- Node 1: Boat Encoder -->
                <div class="sc-node" :class="encoderNodeClass">
                    <div class="sc-nh">
                        <span class="sc-ico">
                            <svg viewBox="0 0 24 24" fill="none" stroke="var(--color-blue)" stroke-width="2"><rect x="2" y="6" width="14" height="12" rx="2"/><path d="M16 10l6-3v10l-6-3z"/></svg>
                        </span>
                        <div>
                            <div class="sc-nm">Boat Encoder</div>
                            <div class="sc-ns">BELABOX · 1080p60</div>
                        </div>
                    </div>
                    <div class="sc-nst">{{ srtUp ? 'Encoding' : 'Offline' }}</div>
                </div>

                <!-- Link: SRT Publish -->
                <div class="sc-link" :class="{ 'sc-link--idle': !srtPubConnected }">
                    <span class="sc-ltag">SRT Publish</span>
                    <span class="sc-lm" :class="{ 'sc-val--stale': pubBitrateStale }">
                        {{ pubBitrateDisplay }}<span class="sc-lu">Mb/s</span>
                    </span>
                    <div class="sc-flow" :class="{ 'sc-flow--active': srtPubConnected }"></div>
                    <span class="sc-lr">RTT {{ pubRttDisplay }} ms · {{ pubDroppedDisplay }} total dropped</span>
                </div>

                <!-- Node 2: Relay -->
                <div class="sc-node" :class="relayNodeClass">
                    <div class="sc-nh">
                        <span class="sc-ico">
                            <svg viewBox="0 0 24 24" fill="none" stroke="var(--color-teal)" stroke-width="2"><path d="M4 17a8 8 0 0 1 16 0"/><path d="M7 17a5 5 0 0 1 10 0"/><circle cx="12" cy="18" r="1.6" fill="var(--color-teal)"/></svg>
                        </span>
                        <div>
                            <div class="sc-nm">Relay</div>
                            <div class="sc-ns">SRT ingest</div>
                        </div>
                    </div>
                    <div class="sc-nst">{{ relayStatus }}</div>
                </div>

                <!-- Link: SRT Pull -->
                <div class="sc-link" :class="{ 'sc-link--idle': !pullEnabled }">
                    <span class="sc-ltag">SRT Pull</span>
                    <span class="sc-lm" :class="{ 'sc-val--stale': conBitrateStale }">
                        {{ conBitrateDisplay }}<span class="sc-lu">Mb/s</span>
                    </span>
                    <div class="sc-flow" :class="{ 'sc-flow--active': pullEnabled && srtPubConnected }"></div>
                    <span class="sc-lr">RTT {{ conRttDisplay }} ms · lat {{ conLatDisplay }} ms</span>
                </div>

                <!-- Node 3: Overlay -->
                <div class="sc-node" :class="overlayNodeClass">
                    <div class="sc-nh">
                        <span class="sc-ico">
                            <svg viewBox="0 0 24 24" fill="none" stroke="var(--color-blue)" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>
                        </span>
                        <div>
                            <div class="sc-nm">Overlay</div>
                            <div class="sc-ns">browser source</div>
                        </div>
                    </div>
                    <div class="sc-nst">{{ overlayStatus }}</div>
                </div>
            </div>

            <!-- Hero foot: bitrate trend + dropped-frame markers -->
            <div class="sc-herofoot">
                <div class="sc-bgraph">
                    <TrendChart
                        v-if="bitrateHistory.length > 0"
                        :data="bitrateHistory"
                        variant="line"
                        :markers="droppedMarkers"
                        color="var(--color-green)"
                        marker-color="var(--color-scarlet)"
                        :height="46"
                        :width="600"
                    />
                    <div v-else class="sc-bgraph-empty">No Data</div>
                </div>
                <div class="sc-axis">
                    <span>bitrate Mb/s · dropped-frame events ▐</span>
                    <span>now</span>
                </div>
                <!-- Key stats row -->
                <div class="sc-stats">
                    <span class="sc-stat">
                        <span class="sc-stat__label">Pub bitrate</span>
                        <span class="sc-stat__val" :class="{ 'sc-val--stale': pubBitrateStale }">{{ pubBitrateDisplay }} Mb/s</span>
                    </span>
                    <span class="sc-stat">
                        <span class="sc-stat__label">Pub RTT</span>
                        <span class="sc-stat__val" :class="{ 'sc-val--stale': pubRttStale }">{{ pubRttDisplay }} ms</span>
                    </span>
                    <span class="sc-stat">
                        <span class="sc-stat__label">Pub latency</span>
                        <span class="sc-stat__val">{{ pubLatDisplay }} ms</span>
                    </span>
                    <span class="sc-stat">
                        <span class="sc-stat__label">Con bitrate</span>
                        <span class="sc-stat__val" :class="{ 'sc-val--stale': conBitrateStale }">{{ conBitrateDisplay }} Mb/s</span>
                    </span>
                    <span class="sc-stat">
                        <span class="sc-stat__label">Con RTT</span>
                        <span class="sc-stat__val">{{ conRttDisplay }} ms</span>
                    </span>
                    <span class="sc-stat">
                        <span class="sc-stat__label">Con latency</span>
                        <span class="sc-stat__val">{{ conLatDisplay }} ms</span>
                    </span>
                </div>
            </div>
        </div>

        <!-- Stop/Start Stream Modal -->
        <Teleport to="body">
            <div
                v-if="showStreamModal"
                class="sc-modal-backdrop"
                role="dialog"
                aria-modal="true"
                :aria-labelledby="'stream-modal-title'"
                @keydown.escape="showStreamModal = false"
            >
                <div class="sc-modal-overlay" @click="showStreamModal = false"></div>
                <div ref="streamModalRef" tabindex="-1" class="sc-modal-box">
                    <h3 id="stream-modal-title" class="sc-modal-title">
                        {{ pullEnabled ? 'Stop Stream' : 'Start Stream' }}
                    </h3>
                    <p class="sc-modal-body">
                        {{ pullEnabled
                            ? 'This will stop pulling the SRT feed to the overlay. The encoder will keep broadcasting but the overlay will go dark.'
                            : 'This will start pulling the SRT feed to the overlay and go live.'
                        }}
                    </p>
                    <div class="sc-modal-actions">
                        <button type="button" class="btn btn--ghost" @click="showStreamModal = false">Cancel</button>
                        <button
                            type="button"
                            class="btn btn--danger"
                            :disabled="streamPending"
                            @click="confirmStreamToggle"
                        >
                            {{ streamPending ? 'Updating…' : (pullEnabled ? 'Stop Stream' : 'Start Stream') }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Refresh Overlay Modal -->
        <Teleport to="body">
            <div
                v-if="showReloadModal"
                class="sc-modal-backdrop"
                role="dialog"
                aria-modal="true"
                aria-labelledby="reload-modal-title"
                @keydown.escape="showReloadModal = false"
            >
                <div class="sc-modal-overlay" @click="showReloadModal = false"></div>
                <div ref="reloadModalRef" tabindex="-1" class="sc-modal-box">
                    <h3 id="reload-modal-title" class="sc-modal-title">Refresh Overlay</h3>
                    <p class="sc-modal-body">
                        This will send a reload signal to all connected overlay and dashboard browser windows.
                    </p>
                    <div class="sc-modal-actions">
                        <button type="button" class="btn btn--ghost" @click="showReloadModal = false">Cancel</button>
                        <button
                            type="button"
                            class="btn btn--ghost"
                            :disabled="reloadPending"
                            @click="confirmReload"
                        >
                            {{ reloadPending ? 'Sending…' : 'Reload All Clients' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<script setup>
import { ref, computed, watch, nextTick } from 'vue';
import { router } from '@inertiajs/vue3';
import TrendChart from '@/components/Admin/TrendChart.vue';

const props = defineProps({
    contracts: { type: Object, default: () => ({}) },
    bitrateHistory: { type: Array, default: () => [] },
    droppedHistory: { type: Array, default: () => [] },
    pullEnabled: { type: Boolean, default: false },
});

// ── Modal state ───────────────────────────────────────────────────────────────
const showStreamModal = ref(false);
const showReloadModal = ref(false);
const streamPending = ref(false);
const reloadPending = ref(false);
const streamModalRef = ref(null);
const reloadModalRef = ref(null);

watch(showStreamModal, (open) => {
    if (open) nextTick(() => streamModalRef.value?.focus());
});

watch(showReloadModal, (open) => {
    if (open) nextTick(() => reloadModalRef.value?.focus());
});

function confirmStreamToggle() {
    streamPending.value = true;
    router.post(route('admin.broadcast.pull'), { enabled: !props.pullEnabled }, {
        onFinish() {
            streamPending.value = false;
            showStreamModal.value = false;
        },
    });
}

function confirmReload() {
    reloadPending.value = true;
    router.post(route('admin.settings.force-reload'), {}, {
        onFinish() {
            reloadPending.value = false;
            showReloadModal.value = false;
        },
    });
}

// ── Contract helpers ──────────────────────────────────────────────────────────
function cv(key) {
    return props.contracts?.[key] ?? null;
}

function val(key, fallback = null) {
    return cv(key)?.value ?? fallback;
}

function stale(key) {
    return cv(key)?.stale ?? false;
}

function fmt1(v) {
    if (v == null) return '—';
    return Number(v).toFixed(1);
}

function fmtInt(v) {
    if (v == null) return '—';
    return Math.round(v).toString();
}

// ── Computed display values ───────────────────────────────────────────────────
const srtUp = computed(() => !!val('srt_up'));
const srtPubConnected = computed(() => !!val('srt_pub_connected'));

const pubBitrateStale = computed(() => stale('srt_pub_bitrate'));
const pubRttStale = computed(() => stale('srt_pub_rtt'));
const conBitrateStale = computed(() => stale('srt_con_bitrate'));

const pubBitrateDisplay = computed(() => {
    const v = val('srt_pub_bitrate');
    return v == null ? '—' : fmt1(v / 1_000_000);
});

const pubRttDisplay = computed(() => fmtInt(val('srt_pub_rtt')));
const pubLatDisplay = computed(() => fmtInt(val('srt_pub_latency')));
const pubDroppedDisplay = computed(() => {
    const v = val('srt_pub_dropped');
    return v == null ? '—' : fmtInt(v);
});

const conBitrateDisplay = computed(() => {
    const v = val('srt_con_bitrate');
    return v == null ? '—' : fmt1(v / 1_000_000);
});

const conRttDisplay = computed(() => fmtInt(val('srt_con_rtt')));
const conLatDisplay = computed(() => fmtInt(val('srt_con_latency')));

// ── Node status classes ───────────────────────────────────────────────────────
const encoderNodeClass = computed(() => ({
    'sc-node--live': srtUp.value,
    'sc-node--idle': !srtUp.value,
}));

const relayNodeClass = computed(() => ({
    'sc-node--ok': srtPubConnected.value,
    'sc-node--idle': !srtPubConnected.value,
}));

const relayStatus = computed(() => {
    if (!srtPubConnected.value) return 'Disconnected';
    return 'Connected';
});

const overlayNodeClass = computed(() => ({
    'sc-node--ok': props.pullEnabled && srtPubConnected.value,
    'sc-node--idle': !props.pullEnabled || !srtPubConnected.value,
}));

const overlayStatus = computed(() => {
    if (!props.pullEnabled) return 'Stopped';
    if (!srtPubConnected.value) return 'Waiting';
    return 'Live';
});

// ── droppedMarkers: cumulative counter → per-bucket deltas ───────────────────
const droppedMarkers = computed(() => {
    const hist = props.droppedHistory;
    if (!hist || hist.length < 2) return [];

    const markers = [];
    for (let i = 1; i < hist.length; i++) {
        const prev = typeof hist[i - 1] === 'object' ? hist[i - 1].v : hist[i - 1];
        const curr = typeof hist[i] === 'object' ? hist[i].v : hist[i];
        if (prev == null || curr == null) continue;
        const delta = Math.max(0, curr - prev);
        if (delta > 0) {
            markers.push({ x: i, magnitude: delta });
        }
    }

    return markers;
});
</script>

<style scoped>
.sc-signal-chain {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: 14px;
    padding: 20px 24px;
    margin-bottom: 16px;
}

/* Header row */
.sc-rhead {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 18px;
    flex-wrap: wrap;
    gap: 10px;
}

.sc-seclabel {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--color-text-dim);
}

.sc-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

/* Chain layout */
.sc-hero { display: flex; flex-direction: column; gap: 12px; }

.sc-chain {
    display: flex;
    align-items: stretch;
    justify-content: center;
    gap: 0;
    flex-wrap: nowrap;
    overflow-x: auto;
    padding-bottom: 4px;
}

/* Node */
.sc-node {
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 6px;
    background: var(--color-bg);
    border: 1.5px solid var(--color-border);
    border-radius: 10px;
    padding: 10px 14px;
    min-width: 130px;
    transition: border-color 0.2s;
}

.sc-node--live { border-color: var(--color-scarlet); }
.sc-node--ok { border-color: var(--color-teal); }
.sc-node--idle { opacity: 0.5; }

.sc-nh { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; }

.sc-ico {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    background: var(--color-surface);
    border-radius: 6px;
    flex-shrink: 0;
}

.sc-nm { font-size: 13px; font-weight: 600; color: var(--color-text-primary); }
.sc-ns { font-size: 11px; color: var(--color-text-dim); margin-top: 1px; }
.sc-nst {
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: var(--color-text-dim);
}

.sc-node--live .sc-nst { color: var(--color-scarlet); }
.sc-node--ok .sc-nst { color: var(--color-teal); }

/* Link connector — grows to bridge the nodes, but capped so a wide card
   doesn't leave the readouts stranded in cavernous connectors. */
.sc-link {
    flex: 1 1 auto;
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 0 10px;
    min-width: 110px;
    max-width: 220px;
}

.sc-ltag {
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--color-text-dim);
    margin-bottom: 2px;
}

.sc-lm {
    font-size: 18px;
    font-weight: 700;
    color: var(--color-text-primary);
    line-height: 1;
}

.sc-lu { font-size: 12px; font-weight: 500; color: var(--color-text-dim); margin-left: 1px; }

.sc-flow {
    width: 100%;
    height: 3px;
    border-radius: 2px;
    background: var(--color-border);
    margin: 5px 0;
    overflow: hidden;
    position: relative;
}

.sc-flow--active::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(90deg, transparent 0%, var(--color-green) 50%, transparent 100%);
    animation: flow-pulse 1.6s linear infinite;
}

@keyframes flow-pulse {
    from { transform: translateX(-100%); }
    to   { transform: translateX(100%); }
}

.sc-link--idle { opacity: 0.45; }

.sc-lr { font-size: 10px; color: var(--color-text-dim); text-align: center; }

/* Hero foot */
.sc-herofoot { display: flex; flex-direction: column; gap: 6px; }

.sc-bgraph {
    background: var(--color-bg);
    border-radius: 8px;
    padding: 8px 10px 4px;
    overflow: hidden;
}

.sc-bgraph-empty {
    position: relative;
    height: 46px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    letter-spacing: 0.04em;
    color: var(--color-text-tertiary, var(--color-text-dim));
}

.sc-bgraph-empty::before {
    content: '';
    position: absolute;
    left: 2px;
    right: 2px;
    top: 64%;
    border-top: 1px dashed var(--color-border-light);
}

.sc-axis {
    display: flex;
    justify-content: space-between;
    font-size: 10px;
    color: var(--color-text-dim);
    padding: 0 10px;
}

/* Stats strip */
.sc-stats {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
    padding: 8px 10px;
    background: var(--color-bg);
    border-radius: 8px;
}

.sc-stat { display: flex; flex-direction: column; gap: 1px; }

.sc-stat__label {
    font-size: 10px;
    color: var(--color-text-dim);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-weight: 600;
}

.sc-stat__val {
    font-size: 14px;
    font-weight: 700;
    color: var(--color-text-primary);
}

.sc-val--stale { opacity: 0.45; }

/* Modal */
.sc-modal-backdrop {
    position: fixed;
    inset: 0;
    z-index: 100;
    display: flex;
    align-items: center;
    justify-content: center;
}

.sc-modal-overlay {
    position: absolute;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
}

.sc-modal-box {
    position: relative;
    background: var(--color-surface);
    border-radius: 12px;
    padding: 28px;
    max-width: 420px;
    width: calc(100% - 32px);
    box-shadow: var(--shadow-sm);
    color: var(--color-text-primary);
}

.sc-modal-title {
    font-size: 16px;
    font-weight: 700;
    margin-bottom: 10px;
}

.sc-modal-body {
    font-size: 14px;
    color: var(--color-text-secondary);
    line-height: 1.5;
    margin-bottom: 24px;
}

.sc-modal-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}

/* Reduced motion */
@media (prefers-reduced-motion: reduce) {
    .sc-flow--active::after { animation: none; }
}
</style>
