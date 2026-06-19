<template>
    <div class="tp-panel">
        <div class="sc-rhead">
            <span class="sc-seclabel">Tracker · ESP32</span>
        </div>

        <div class="tp-body">
            <!-- Left: Vitals -->
            <div class="tp-vitals">
                <!-- Mode pill -->
                <div class="tp-mode-pill" :class="modePillClass">
                    {{ modeLabel }}
                </div>

                <!-- Battery -->
                <div class="tp-battery-block">
                    <div class="tp-vrow">
                        <span class="tp-vn">
                            {{ voltageDisplay }}<span class="tp-u">V</span>
                        </span>
                        <span class="tp-vsub">
                            <span v-if="usbPowered" class="tp-chip">⚡ USB powered</span>
                            <span class="tp-vl">battery</span>
                        </span>
                    </div>
                    <!-- Battery bar: voltage 3.0–4.2 V range -->
                    <div class="tp-bat-track">
                        <span
                            class="tp-bat-fill"
                            :class="batteryFillClass"
                            :style="{ width: batteryPct + '%' }"
                        ></span>
                    </div>
                </div>

                <!-- Uptime + Temp -->
                <div class="tp-vsmall">
                    <div class="tp-vi">
                        <div class="tp-vi__n" :class="{ 'tp-stale': stale('tracker_uptime') }">{{ uptimeDisplay }}</div>
                        <div class="tp-vi__l">Uptime</div>
                    </div>
                    <div class="tp-vi">
                        <div class="tp-vi__n" :class="{ 'tp-stale': stale('tracker_temp') }">{{ tempDisplay }}°</div>
                        <div class="tp-vi__l">Device temp</div>
                    </div>
                </div>
            </div>

            <!-- Right: Connectivity + Resources -->
            <div class="tp-conn">
                <div class="tp-conn-grid">
                <!-- LTE row -->
                <div class="tp-crow">
                    <div class="tp-bars" aria-label="LTE signal strength">
                        <i
                            v-for="b in 5"
                            :key="'lte-' + b"
                            :class="{ 'tp-bar-off': b > lteBars }"
                            :style="{ height: (6 + b * 4) + 'px' }"
                        ></i>
                    </div>
                    <div class="tp-cmain">
                        <div class="tp-ct">
                            {{ lteConnected ? lteRatDisplay : 'LTE' }}
                            <span class="tp-conn-dot" :class="lteConnected ? 'tp-conn-dot--on' : 'tp-conn-dot--off'"></span>
                        </div>
                        <div class="tp-cs">{{ lteConnected ? 'connected' : 'disconnected' }}</div>
                    </div>
                    <div class="tp-cval" :class="{ 'tp-stale': stale('tracker_lte_rssi') }">
                        <div class="tp-cv__n">{{ lteRssiDisplay }}<span class="tp-cu">dBm</span></div>
                        <div class="tp-cv__l">{{ lteQualityDisplay }} quality</div>
                    </div>
                </div>

                <!-- WiFi row -->
                <div class="tp-crow">
                    <div class="tp-bars" aria-label="WiFi signal strength">
                        <i
                            v-for="b in 5"
                            :key="'wifi-' + b"
                            :class="{ 'tp-bar-off': b > wifiBars }"
                            :style="{ height: (6 + b * 4) + 'px' }"
                        ></i>
                    </div>
                    <div class="tp-cmain">
                        <div class="tp-ct">
                            WiFi
                            <span class="tp-conn-dot" :class="wifiConnected ? 'tp-conn-dot--on' : 'tp-conn-dot--off'"></span>
                        </div>
                        <div class="tp-cs">{{ wifiConnected ? 'connected' : 'disconnected' }}</div>
                    </div>
                    <div class="tp-cval" :class="{ 'tp-stale': stale('tracker_wifi_rssi') }">
                        <div class="tp-cv__n">{{ wifiRssiDisplay }}<span class="tp-cu">dBm</span></div>
                        <div class="tp-cv__l">2.4 GHz</div>
                    </div>
                </div>

                </div>

                <!-- Resource mini-bars: CPU, Free heap, Humidity -->
                <div class="tp-res">
                    <div class="tp-rc">
                        <div class="tp-rl" :class="{ 'tp-stale': stale('tracker_cpu') }">
                            CPU<b>{{ cpuDisplay }}%</b>
                        </div>
                        <div class="tp-rb">
                            <span :style="{ width: cpuPct + '%', background: 'var(--color-teal)' }"></span>
                        </div>
                    </div>
                    <div class="tp-rc">
                        <div class="tp-rl" :class="{ 'tp-stale': stale('tracker_free_heap') }">
                            Free heap<b>{{ heapDisplay }} KB</b>
                        </div>
                        <div class="tp-rb">
                            <span :style="{ width: heapBarPct + '%', background: 'var(--color-amber)' }"></span>
                        </div>
                    </div>
                    <div class="tp-rc">
                        <div class="tp-rl" :class="{ 'tp-stale': stale('tracker_humidity') }">
                            Humidity<b>{{ humidityDisplay }}%</b>
                        </div>
                        <div class="tp-rb">
                            <span :style="{ width: humidityPct + '%', background: 'var(--color-blue)' }"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    contracts: { type: Object, default: () => ({}) },
});

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

function fmtInt(v) {
    if (v == null) return '—';
    return Math.round(v).toString();
}

// ── Mode ──────────────────────────────────────────────────────────────────────
const modeLabel = computed(() => {
    const m = val('tracker_mode');
    if (!m) return 'Unknown';
    return m.toString().toUpperCase();
});

const modePillClass = computed(() => {
    const m = val('tracker_mode', '').toString().toLowerCase();
    if (m.includes('realtime') || m.includes('real')) return 'tp-mode--realtime';
    if (m.includes('saver') || m.includes('save')) return 'tp-mode--saver';
    return 'tp-mode--unknown';
});

// ── Battery / Voltage ─────────────────────────────────────────────────────────
const usbPowered = computed(() => !!val('tracker_usb_powered'));

const voltageDisplay = computed(() => {
    const v = val('tracker_battery_voltage');
    if (v == null) return '—';
    return Number(v).toFixed(2);
});

const batteryPct = computed(() => {
    const v = val('tracker_battery_voltage');
    if (v == null) return 0;
    const MIN_V = 3.0;
    const MAX_V = 4.2;
    return Math.min(100, Math.max(0, ((v - MIN_V) / (MAX_V - MIN_V)) * 100));
});

const batteryFillClass = computed(() => {
    const pct = batteryPct.value;
    if (stale('tracker_battery_voltage')) return 'tp-bat-fill--stale';
    if (pct <= 20) return 'tp-bat-fill--low';
    if (pct <= 50) return 'tp-bat-fill--mid';
    return 'tp-bat-fill--ok';
});

// ── Uptime / Temp ─────────────────────────────────────────────────────────────
const uptimeDisplay = computed(() => {
    const s = val('tracker_uptime');
    if (s == null) return '—';
    const h = Math.floor(s / 3600);
    const m = Math.floor((s % 3600) / 60);
    if (h > 0) return `${h} h ${m.toString().padStart(2, '0')} m`;
    return `${m} m`;
});

const tempDisplay = computed(() => {
    const v = val('tracker_temp');
    if (v == null) return '—';
    return Number(v).toFixed(1);
});

// ── LTE ───────────────────────────────────────────────────────────────────────
const lteConnected = computed(() => !!val('tracker_lte_connected'));

const lteRssiDisplay = computed(() => {
    const v = val('tracker_lte_rssi');
    if (v == null) return '—';
    return Math.round(v).toString();
});

const lteQualityDisplay = computed(() => {
    const v = val('tracker_lte_quality');
    if (v == null) return '—/31';
    return `${Math.round(v)}/31`;
});

const lteRatDisplay = computed(() => {
    const r = val('tracker_lte_rat');
    if (!r) return 'LTE';
    return r.toString();
});

/**
 * Signal bars from RSSI (dBm). LTE RSSI typically -50 (excellent) to -110 (poor).
 * Bars 1-5 where ≤-110 = 0 bars (all off), ≥-65 = 5 bars.
 */
const lteBars = computed(() => {
    if (!lteConnected.value) return 0;

    // Prefer quality (0-31) if available
    const q = val('tracker_lte_quality');
    if (q != null) {
        const pct = q / 31;
        return Math.max(1, Math.ceil(pct * 5));
    }

    const rssi = val('tracker_lte_rssi');
    if (rssi == null) return 0;
    if (rssi >= -65) return 5;
    if (rssi >= -75) return 4;
    if (rssi >= -85) return 3;
    if (rssi >= -95) return 2;
    return 1;
});

// ── WiFi ──────────────────────────────────────────────────────────────────────
const wifiConnected = computed(() => !!val('tracker_wifi_connected'));

const wifiRssiDisplay = computed(() => {
    const v = val('tracker_wifi_rssi');
    if (v == null) return '—';
    return Math.round(v).toString();
});

/**
 * WiFi bars from RSSI (dBm). Typical: -50 excellent, -80 poor.
 */
const wifiBars = computed(() => {
    if (!wifiConnected.value) return 0;
    const rssi = val('tracker_wifi_rssi');
    if (rssi == null) return 0;
    if (rssi >= -55) return 5;
    if (rssi >= -65) return 4;
    if (rssi >= -70) return 3;
    if (rssi >= -78) return 2;
    return 1;
});

// ── Resources ─────────────────────────────────────────────────────────────────
const cpuDisplay = computed(() => fmtInt(val('tracker_cpu')));
const cpuPct = computed(() => {
    const v = val('tracker_cpu');
    return v == null ? 0 : Math.min(100, Math.max(0, v));
});

const heapDisplay = computed(() => {
    const v = val('tracker_free_heap');
    if (v == null) return '—';
    return Math.round(v / 1024).toString();
});

// ESP32 typically has ~300 KB total heap; show fill relative to 300 KB total
const heapBarPct = computed(() => {
    const v = val('tracker_free_heap');
    if (v == null) return 0;
    const TOTAL = 300 * 1024;
    return Math.min(100, Math.max(0, (v / TOTAL) * 100));
});

const humidityDisplay = computed(() => fmtInt(val('tracker_humidity')));
const humidityPct = computed(() => {
    const v = val('tracker_humidity');
    return v == null ? 0 : Math.min(100, Math.max(0, v));
});
</script>

<style scoped>
.tp-panel {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: 14px;
    padding: 20px 24px;
    margin-bottom: 16px;
}

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

/* Two-column body */
.tp-body {
    display: grid;
    grid-template-columns: minmax(170px, 220px) 1fr;
    gap: 24px;
}

@media (max-width: 640px) {
    .tp-body { grid-template-columns: 1fr; }
}

.tp-vitals {
    display: flex;
    flex-direction: column;
    gap: 14px;
    min-width: 0;
}

.tp-conn {
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.tp-conn-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 4px 24px;
}

@media (max-width: 520px) {
    .tp-conn-grid { grid-template-columns: 1fr; }
}

/* Mode pill */
.tp-mode-pill {
    display: inline-flex;
    align-items: center;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.08em;
    width: fit-content;
}

.tp-mode--realtime {
    background: var(--color-green-bg);
    color: var(--color-green);
}

.tp-mode--saver {
    background: var(--color-amber-bg);
    color: var(--color-amber);
}

.tp-mode--unknown {
    background: var(--color-bg);
    color: var(--color-text-dim);
}

/* Battery vitals */
.tp-vrow {
    display: flex;
    align-items: baseline;
    gap: 8px;
}

.tp-vn {
    font-size: 28px;
    font-weight: 700;
    line-height: 1;
    color: var(--color-text-primary);
}

.tp-u { font-size: 14px; font-weight: 500; color: var(--color-text-dim); }

.tp-vsub {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.tp-chip {
    font-size: 10px;
    font-weight: 600;
    color: var(--color-amber);
    background: var(--color-amber-bg);
    border-radius: 4px;
    padding: 1px 5px;
    line-height: 1.4;
}

.tp-vl { font-size: 11px; color: var(--color-text-dim); }

/* Battery bar */
.tp-bat-track {
    height: 8px;
    background: var(--color-bg);
    border-radius: 4px;
    overflow: hidden;
}

.tp-bat-fill {
    display: block;
    height: 100%;
    border-radius: 4px;
    transition: width 0.6s cubic-bezier(0.25, 1, 0.5, 1);
    background: var(--color-green);
}

.tp-bat-fill--ok { background: var(--color-green); }
.tp-bat-fill--mid { background: var(--color-amber); }
.tp-bat-fill--low { background: var(--color-scarlet); }
.tp-bat-fill--stale { opacity: 0.45; }

/* Uptime / Temp small row */
.tp-vsmall {
    display: flex;
    gap: 16px;
}

.tp-vi__n {
    font-size: 16px;
    font-weight: 700;
    color: var(--color-text-primary);
}

.tp-vi__l {
    font-size: 11px;
    color: var(--color-text-dim);
    margin-top: 1px;
}

/* Connectivity row */
.tp-crow {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 0;
}

/* Signal bars */
.tp-bars {
    display: flex;
    align-items: flex-end;
    gap: 2px;
    width: 28px;
    flex-shrink: 0;
}

.tp-bars i {
    display: block;
    width: 4px;
    border-radius: 2px;
    background: var(--color-green);
    flex-shrink: 0;
}

.tp-bars i.tp-bar-off {
    background: var(--color-border);
}

.tp-cmain { flex: 1; }

.tp-ct {
    font-size: 13px;
    font-weight: 600;
    color: var(--color-text-primary);
    display: flex;
    align-items: center;
    gap: 5px;
}

.tp-cs { font-size: 11px; color: var(--color-text-dim); margin-top: 2px; }

.tp-conn-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    flex-shrink: 0;
}

.tp-conn-dot--on { background: var(--color-green); }
.tp-conn-dot--off { background: var(--color-border); }

.tp-cval {
    text-align: right;
    flex-shrink: 0;
}

.tp-cv__n {
    font-size: 15px;
    font-weight: 700;
    color: var(--color-text-primary);
}

.tp-cu { font-size: 11px; font-weight: 500; color: var(--color-text-dim); }

.tp-cv__l { font-size: 11px; color: var(--color-text-dim); margin-top: 1px; }

/* Resource mini-bars */
.tp-res {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px 24px;
    margin-top: 10px;
    padding-top: 14px;
    border-top: 1px solid var(--color-border-light);
}

@media (max-width: 520px) {
    .tp-res { grid-template-columns: 1fr; }
}

.tp-rc { display: flex; flex-direction: column; gap: 6px; }

.tp-rl {
    display: flex;
    justify-content: space-between;
    font-size: 11px;
    color: var(--color-text-secondary);
}

.tp-rl b {
    font-weight: 700;
    color: var(--color-text-primary);
}

.tp-rb {
    height: 6px;
    background: var(--color-bg);
    border-radius: 3px;
    overflow: hidden;
}

.tp-rb span {
    display: block;
    height: 100%;
    border-radius: 3px;
    transition: width 0.6s cubic-bezier(0.25, 1, 0.5, 1);
}

/* Stale treatment */
.tp-stale { opacity: 0.45; }
</style>
