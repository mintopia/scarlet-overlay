<template>
    <AdminLayout>
        <Head title="Tech Dashboard" />

        <div class="tech-dash">
            <!-- Region 1: Broadcast Signal Chain (Hero) -->
            <SignalChain
                :contracts="liveContracts"
                :bitrateHistory="bitrateHistory"
                :droppedHistory="droppedHistory"
                :pullEnabled="pullEnabled"
            />

            <!-- Region 2: Tracker · ESP32 -->
            <TrackerPanel :contracts="liveContracts" />

            <!-- Region 3: Power -->
            <div class="power-panel">
                <div class="sc-rhead">
                    <span class="sc-seclabel">Power</span>
                </div>

                <div class="power-grid">
                    <!-- House Battery -->
                    <div class="power-sys">
                        <div class="power-sh">
                            <span class="power-seclabel" style="color: var(--color-green)">House Battery</span>
                        </div>
                        <div class="power-main">
                            <span class="power-soc" :class="{ 'power-stale': stale('house_battery_soc') }">
                                {{ houseSocDisplay }}
                            </span>
                            <span class="power-rem">
                                <span class="power-rem__lab">runtime</span>
                                <span class="power-rem__val">{{ houseRuntimeDisplay }}</span>
                            </span>
                        </div>
                        <div class="power-det" :class="{ 'power-stale': stale('house_battery_voltage') }">
                            <b>{{ houseVoltDisplay }}</b> V ·
                            <span :class="housePowerClass">{{ housePowerDisplay }}</span>
                        </div>
                        <div class="power-chart">
                            <TrendChart
                                v-if="housePowerHistory.length > 0"
                                :data="housePowerHistory"
                                variant="bipolar"
                                color-positive="var(--color-green)"
                                color-negative="var(--color-scarlet)"
                                :height="88"
                                :width="300"
                            />
                            <div v-else class="power-chart-empty">No history</div>
                        </div>
                        <div class="power-axis">
                            <span>net power · 6 h · <span style="color: oklch(0.34 0.11 150)">charge</span>/<span style="color: var(--color-scarlet)">discharge</span></span>
                            <span>now</span>
                        </div>
                    </div>

                    <!-- EcoFlow Delta -->
                    <div class="power-sys">
                        <div class="power-sh">
                            <span class="power-seclabel" style="color: var(--color-teal)">EcoFlow Delta</span>
                        </div>
                        <div class="power-main">
                            <span class="power-soc" :class="{ 'power-stale': stale('ecoflow_soc') }">
                                {{ ecoflowSocDisplay }}
                            </span>
                            <span class="power-rem">
                                <span class="power-rem__lab">{{ ecoflowRemLabel }}</span>
                                <span class="power-rem__val">{{ ecoflowRemDisplay }}</span>
                            </span>
                        </div>
                        <div class="power-det">
                            <span :class="ecoflowPowerClass">{{ ecoflowPowerDisplay }}</span>
                        </div>
                        <div class="power-chart">
                            <TrendChart
                                v-if="ecoflowPowerHistory.length > 0"
                                :data="ecoflowPowerHistory"
                                variant="bipolar"
                                color-positive="var(--color-green)"
                                color-negative="var(--color-scarlet)"
                                :height="88"
                                :width="300"
                            />
                            <div v-else class="power-chart-empty">No history</div>
                        </div>
                        <div class="power-axis">
                            <span>net flow · 6 h · <span style="color: oklch(0.34 0.11 150)">charge</span>/<span style="color: var(--color-scarlet)">discharge</span></span>
                            <span>now</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import SignalChain from '@/components/Dash/SignalChain.vue';
import TrackerPanel from '@/components/Dash/TrackerPanel.vue';
import TrendChart from '@/components/Admin/TrendChart.vue';
import { useScarletMetrics } from '@/composables/useScarletMetrics.js';

const props = defineProps({
    contracts: { type: Object, default: () => ({}) },
    bitrateHistory: { type: Array, default: () => [] },
    droppedHistory: { type: Array, default: () => [] },
    housePowerHistory: { type: Array, default: () => [] },
    ecoflowPowerHistory: { type: Array, default: () => [] },
    pullEnabled: { type: Boolean, default: false },
    reverb: { type: Object, default: null },
    reverbKey: { type: String, default: null },
});

// ── Live updates via Echo ─────────────────────────────────────────────────────
// useScarletMetrics wires window.Echo on the 'metrics' channel and exposes
// a `canonical` ref updated on each .metrics.updated broadcast.
const { canonical } = useScarletMetrics({
    initialMetrics: { canonical: props.contracts },
});

// Merge SSR props with live canonical updates.
// canonical.value is the full canonical map; fall back to SSR contracts
// when a key is absent from the live update.
const liveContracts = computed(() => {
    return Object.assign({}, props.contracts, canonical.value ?? {});
});

// ── Contract helpers ──────────────────────────────────────────────────────────
function val(key, fallback = null) {
    return liveContracts.value?.[key]?.value ?? fallback;
}

function stale(key) {
    return liveContracts.value?.[key]?.stale ?? false;
}

function fmtInt(v) {
    if (v == null) return '—';
    return Math.round(v).toString();
}

// ── House Battery ─────────────────────────────────────────────────────────────
const houseSocDisplay = computed(() => {
    const v = val('house_battery_soc');
    return v == null ? '—%' : `${fmtInt(v)}%`;
});

const houseVoltDisplay = computed(() => {
    const v = val('house_battery_voltage');
    if (v == null) return '—';
    return Number(v).toFixed(1);
});

const houseRuntimeDisplay = computed(() => {
    return '—';
});

const housePowerDisplay = computed(() => {
    const hist = props.housePowerHistory;
    if (!hist || hist.length === 0) return '—';
    const last = hist[hist.length - 1];
    const w = typeof last === 'object' ? last.v : last;
    if (w == null) return '—';
    const abs = Math.abs(w).toFixed(0);
    return w >= 0 ? `+${abs} W charge` : `−${Math.abs(w).toFixed(0)} W discharge`;
});

const housePowerClass = computed(() => {
    const hist = props.housePowerHistory;
    if (!hist || hist.length === 0) return '';
    const last = hist[hist.length - 1];
    const w = typeof last === 'object' ? last.v : last;
    if (w == null) return '';
    return w >= 0 ? 'power-chg' : 'power-dis';
});

// ── EcoFlow ───────────────────────────────────────────────────────────────────
const ecoflowSocDisplay = computed(() => {
    const v = val('ecoflow_soc');
    return v == null ? '—%' : `${fmtInt(v)}%`;
});

const ecoflowPowerDisplay = computed(() => {
    const hist = props.ecoflowPowerHistory;
    if (!hist || hist.length === 0) return '—';
    const last = hist[hist.length - 1];
    const w = typeof last === 'object' ? last.v : last;
    if (w == null) return '—';
    const abs = Math.abs(w).toFixed(0);
    return w >= 0 ? `+${abs} W in` : `${abs} W out`;
});

const ecoflowPowerClass = computed(() => {
    const hist = props.ecoflowPowerHistory;
    if (!hist || hist.length === 0) return '';
    const last = hist[hist.length - 1];
    const w = typeof last === 'object' ? last.v : last;
    if (w == null) return '';
    return w >= 0 ? 'power-chg' : 'power-dis';
});

const ecoflowRemLabel = computed(() => {
    const inputW = val('ecoflow_input_watts');
    const outputW = val('ecoflow_output_watts');
    if (inputW != null && outputW != null) {
        return inputW > outputW ? 'to full' : 'remaining';
    }
    return 'remaining';
});

const ecoflowRemDisplay = computed(() => {
    const minutes = val('ecoflow_remain_time');
    if (minutes == null) return '—';
    const totalMin = Math.round(minutes);
    if (totalMin <= 0) return '—';
    const h = Math.floor(totalMin / 60);
    const m = totalMin % 60;
    if (h === 0) return `${m} m`;
    return `${h} h ${m.toString().padStart(2, '0')} m`;
});
</script>

<style scoped>
.tech-dash {
    max-width: 1100px;
}

/* Shared section header */
.sc-rhead {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
}

.sc-seclabel {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--color-text-dim);
}

/* Power panel */
.power-panel {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: 14px;
    padding: 20px 24px;
    margin-bottom: 16px;
}

.power-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
}

@media (max-width: 640px) {
    .power-grid { grid-template-columns: 1fr; }
}

.power-sys { display: flex; flex-direction: column; gap: 8px; }

.power-sh { margin-bottom: 2px; }

.power-seclabel {
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}

.power-main {
    display: flex;
    align-items: baseline;
    gap: 10px;
}

.power-soc {
    font-size: 32px;
    font-weight: 700;
    color: var(--color-text-primary);
    line-height: 1;
}

.power-rem {
    display: flex;
    flex-direction: column;
    gap: 1px;
}

.power-rem__lab {
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--color-text-dim);
}

.power-rem__val {
    font-size: 15px;
    font-weight: 700;
    color: var(--color-text-primary);
}

.power-det {
    font-size: 13px;
    color: var(--color-text-secondary);
}

.power-det b { font-weight: 700; color: var(--color-text-primary); }

.power-chg { color: oklch(0.34 0.11 150); font-weight: 600; }
.power-dis { color: var(--color-scarlet); font-weight: 600; }

.power-chart {
    background: var(--color-bg);
    border-radius: 8px;
    padding: 6px 8px 2px;
    overflow: hidden;
}

.power-chart-empty {
    height: 88px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    color: var(--color-text-dim);
}

.power-axis {
    display: flex;
    justify-content: space-between;
    font-size: 10px;
    color: var(--color-text-dim);
    padding: 0 8px;
}

.power-stale { opacity: 0.45; }
</style>
