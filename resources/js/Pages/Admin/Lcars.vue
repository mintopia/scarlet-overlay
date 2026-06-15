<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import { router, Head } from '@inertiajs/vue3';
import { useLcarsTelemetry } from '../../composables/useLcarsTelemetry.js';
import { useKonami } from '../../composables/useKonami.js';
import { STATIONS, CONTRACT, pluck } from '../../lcars/contract.js';
import { fmtMetric } from '../../lcars/format.js';
import LcarsLineGraph from '../../components/Lcars/LcarsLineGraph.vue';
import LcarsDial from '../../components/Lcars/LcarsDial.vue';
import LcarsBar from '../../components/Lcars/LcarsBar.vue';
import LcarsStat from '../../components/Lcars/LcarsStat.vue';
import LcarsSignalBars from '../../components/Lcars/LcarsSignalBars.vue';
import LcarsSunArc from '../../components/Lcars/LcarsSunArc.vue';
import LcarsNavFix from '../../components/Lcars/LcarsNavFix.vue';
import LcarsClimate from '../../components/Lcars/LcarsClimate.vue';
import LcarsSchematic from '../../components/Lcars/LcarsSchematic.vue';
import LcarsSplash from '../../components/Lcars/LcarsSplash.vue';
import LcarsDetail from '../../components/Lcars/LcarsDetail.vue';
import '../../../css/lcars.css';

const props = defineProps({
    initialMetrics: { type: Object, default: null },
    boatName: { type: String, default: 'Scarlet' },
    registry: { type: String, default: '' },
    msdHistory: { type: Array, default: () => [] },
});

const tele = useLcarsTelemetry({ initialMetrics: props.initialMetrics });
const active = ref('msd');
const loaded = ref(new Set(['msd']));

// Flatten contract → historical key → src path (for live-tip appends).
const HIST_SRC = {};
for (const metrics of Object.values(CONTRACT)) {
    for (const m of metrics) if (m.cls === 'historical') HIST_SRC[m.key] = m.src;
}
const tracked = ref(new Set(['speed_sog', 'house_battery_soc'])); // msd preloads

const activeStation = computed(() => STATIONS.find(s => s.id === active.value));
const activeMetrics = computed(() => CONTRACT[active.value] ?? []);

// ── Seed MSD history, wire the live tip ────────────────────────────────
onMounted(() => {
    for (const s of props.msdHistory ?? []) tele.loadHistory(s.key, s.points);
    tele.onLiveTip(() => {
        for (const key of tracked.value) tele.appendLive(key, HIST_SRC[key]);
    });
});

// ── Station switching (View Transitions + lazy history + bleep) ─────────
async function fetchStationHistory(id) {
    if (loaded.value.has(id)) return;
    const hist = (CONTRACT[id] ?? []).filter(m => m.cls === 'historical');
    if (hist.length) {
        for (const m of hist) tracked.value.add(m.key);
        try {
            const { data } = await window.axios.get('/lcars/series', { params: { station: id } });
            for (const s of data) tele.loadHistory(s.key, s.points);
        } catch (e) {
            // history stays empty; live tip will still populate. (logged server-side)
        }
    }
    loaded.value = new Set([...loaded.value, id]);
}

function selectStation(id) {
    if (id === active.value) return;
    bleep();
    const swap = () => { active.value = id; };
    if (document.startViewTransition && !reducedMotion) {
        document.startViewTransition(swap);
    } else {
        swap();
    }
    fetchStationHistory(id);
}

// Return to whichever dashboard the visitor came from; fall back to the public one.
// ── Intro splash (UFP → MSD), once per mount ───────────────────────────
const showSplash = ref(true);

// ── Operable: click a reading → morph to a full-screen analysis ────────
const CLICKABLE = new Set(['line', 'dial', 'compass', 'bar', 'stat', 'signal']);
const detailKey = ref(null);
const morphKey = ref(null);
const detailMetric = computed(() => activeMetrics.value.find(m => m.key === detailKey.value) ?? null);

function withTransition(mutate) {
    if (document.startViewTransition && !reducedMotion) {
        const vt = document.startViewTransition(async () => { mutate(); await nextTick(); });
        vt.finished.finally(() => { morphKey.value = null; });
    } else {
        mutate();
        morphKey.value = null;
    }
}
function openDetail(m) {
    if (!CLICKABLE.has(m.viz)) return;
    bleep();
    morphKey.value = m.key;
    withTransition(() => { detailKey.value = m.key; });
}
function closeDetail() {
    bleep();
    morphKey.value = detailKey.value;     // returning tile reclaims the morph
    withTransition(() => { detailKey.value = null; });
}

function exit() {
    bleep();
    const ref = document.referrer;
    if (ref && new URL(ref, location.origin).origin === location.origin && /\/(admin|dashboard)/.test(ref)) {
        history.back();
    } else {
        router.visit('/dashboard');
    }
}

// ── Sound (opt-in, lazy AudioContext, persisted) ───────────────────────
const reducedMotion = typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const soundOn = ref(false);
let audioCtx = null;
onMounted(() => { soundOn.value = localStorage.getItem('lcars_sound') === '1'; });
function toggleSound() {
    soundOn.value = !soundOn.value;
    localStorage.setItem('lcars_sound', soundOn.value ? '1' : '0');
    if (soundOn.value && !audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
    bleep();
}
function bleep() {
    if (!soundOn.value || !audioCtx) return;
    try {
        const o = audioCtx.createOscillator(), g = audioCtx.createGain();
        o.type = 'square';
        o.frequency.value = 440 + Math.floor(Math.random() * 6) * 110;
        g.gain.value = 0.035;
        o.connect(g); g.connect(audioCtx.destination);
        o.start(); o.stop(audioCtx.currentTime + 0.07);
    } catch (e) { /* noop */ }
}

// ── Konami (re-arm: ↑↑↓↓←→←→BA flashes the panel) ──────────────────────
const flash = ref(false);
useKonami(() => { flash.value = true; setTimeout(() => { flash.value = false; }, 600); });

// ── Clock + stardate ───────────────────────────────────────────────────
const utc = computed(() => { void tele.nowTick.value; return new Date().toISOString().slice(11, 19); });
const local = computed(() => { void tele.nowTick.value; return new Date().toTimeString().slice(0, 8); });
const stardate = computed(() => {
    void tele.nowTick.value;
    const d = new Date();
    const start = Date.UTC(d.getUTCFullYear(), 0, 0);
    const doy = (Date.now() - start) / 86400000;
    return (47000 + doy + (d.getUTCHours() / 24)).toFixed(1);
});

// ── Visibility pausing (decorative motion only) ────────────────────────
const paused = ref(false);
function onVis() { paused.value = document.hidden; }
onMounted(() => document.addEventListener('visibilitychange', onVis));
onUnmounted(() => document.removeEventListener('visibilitychange', onVis));

// Decorative cascade columns
const cascade = Array.from({ length: 3 }, (_, c) =>
    Array.from({ length: 28 }, (_, i) => ((i * 7 + c * 131) % 9000 + 1000).toString()).join('\n'));

// ── Per-metric helpers ─────────────────────────────────────────────────
function cur(m) { return pluck(tele.metrics.value, m.src); }
function lost(m) { return m.src ? tele.signalLost.value.has(m.src) : false; }
function colorFor(m) { return m.color ?? 'var(--orange)'; }
const HEADER_VIZ = ['line', 'badge', 'navfix', 'sunarc'];
function showHeader(m) { return HEADER_VIZ.includes(m.viz); }
function statFor(m) {
    const v = cur(m);
    const { text, unit } = fmtMetric(m, v);
    return { text, unit, pct: m.unit === '%' && typeof v === 'number' ? v : null };
}
function badgeText(m, v) {
    if (v == null) return '--';
    if (m.key.endsWith('connected')) return v ? 'LINK' : 'DOWN';
    return String(v).toUpperCase();
}
const G = (path) => pluck(tele.metrics.value, path);
</script>

<template>
    <Head title="LCARS" />
    <div class="lcars" :class="{ paused, flash }">
        <!-- Desktop frame -->
        <div class="lcars-wrap">
            <div class="lcars-elbow"><span>LCARS<br>24613</span></div>

            <div class="lcars-topbar">
                <div class="lcars-title">
                    <span class="name">S.V. {{ (boatName || 'SCARLET').toUpperCase() }} · SHIP SYSTEMS</span>
                    <span class="reg" v-if="registry">NCC · {{ registry }}</span>
                </div>
                <div class="lcars-tag"><span>SD {{ stardate }}</span><span>{{ utc }} UTC</span></div>
                <div class="lcars-tag b" :class="{ stale: tele.isStale.value }">
                    <span>{{ tele.isStale.value ? 'SIGNAL' : 'LIVE' }}</span>
                    <span>{{ tele.isStale.value ? 'LOST' : local }}</span>
                </div>
            </div>

            <!-- Rail -->
            <nav class="lcars-rail">
                <button v-for="s in STATIONS" :key="s.id" class="lcars-btn"
                    :class="{ active: active === s.id }" :aria-label="s.title" :aria-pressed="active === s.id"
                    @click="selectStation(s.id)">{{ s.label }}</button>
                <button class="lcars-btn" :class="{ active: soundOn }" :aria-pressed="soundOn"
                    :aria-label="soundOn ? 'Interface sound on' : 'Interface sound off'" @click="toggleSound">{{ soundOn ? '♪ ON' : '♪ OFF' }}</button>
                <div class="num lcars-num" aria-hidden="true">47·02·19</div>
                <div class="spacer"></div>
                <button class="lcars-btn exit" aria-label="End program and return to dashboard" @click="exit">◄ END</button>
            </nav>

            <!-- Main -->
            <main class="lcars-main">
                <div class="lcars-sysbar">
                    <span class="dot" aria-hidden="true"></span>
                    <h1>{{ activeStation?.title }}</h1>
                    <span class="meta">{{ tele.isStale.value ? 'LAST CONTACT LOST' : 'SENSORS NOMINAL' }}</span>
                </div>

                <!-- MSD -->
                <div v-if="active === 'msd'" class="lcars-grid" style="grid-template-columns: 1fr;">
                    <LcarsSchematic :metrics="tele.metrics.value" :boat-name="boatName" :registry="registry" />
                </div>

                <!-- Bridge stations (bento layout) -->
                <div v-else-if="!detailKey" class="lcars-grid">
                    <div v-for="m in activeMetrics" :key="m.key" class="lcars-panel"
                        :class="[`s-${m.size || 'md'}`, { clickable: CLICKABLE.has(m.viz) }]"
                        :style="morphKey === m.key ? { viewTransitionName: 'lcars-morph' } : null"
                        :role="CLICKABLE.has(m.viz) ? 'button' : null"
                        :tabindex="CLICKABLE.has(m.viz) ? 0 : null"
                        :aria-label="CLICKABLE.has(m.viz) ? `Analyse ${m.label}` : null"
                        @click="openDetail(m)" @keydown.enter="openDetail(m)">
                        <span v-if="CLICKABLE.has(m.viz)" class="analyse-corner" aria-hidden="true">◹ ANALYSE</span>
                        <div v-if="showHeader(m)" class="hd">
                            <span>{{ m.label }}</span>
                            <span v-if="lost(m)" class="lost-flag">SIGNAL LOST</span>
                        </div>

                        <!-- line history (hero gets a larger readout + taller graph) -->
                        <template v-if="m.viz === 'line'">
                            <div class="body" style="justify-content:flex-start">
                                <div class="lcars-readout" :style="{ fontSize: (m.size === 'hero' ? 56 : 32) + 'px' }">
                                    {{ statFor(m).text }}<span class="u">{{ m.unit }}</span>
                                </div>
                                <LcarsLineGraph :points="tele.history(m.key).points" :last-known="tele.history(m.key).lastKnown"
                                    :range="m.range" :color="colorFor(m)" :now-ms="tele.nowTick.value"
                                    :height="m.size === 'hero' ? 150 : 88" />
                            </div>
                        </template>

                        <div v-else class="body">
                            <LcarsBar v-if="m.viz === 'bar'" :value="cur(m)" :range="m.range" :unit="m.unit"
                                :label="m.label" :dp="m.dp ?? 0" :color="colorFor(m)" :lost="lost(m)" />

                            <LcarsDial v-else-if="m.viz === 'dial' || m.viz === 'compass'" :value="cur(m)" :range="m.range"
                                :unit="m.unit" :label="m.label" :dp="m.dp ?? 1" :compass="m.viz === 'compass' || m.compass" :lost="lost(m)" />

                            <LcarsStat v-else-if="m.viz === 'stat'" :label="m.label" :text="statFor(m).text"
                                :unit="statFor(m).unit" :pct="statFor(m).pct" :color="colorFor(m)"
                                :size="m.size === 'lg' ? 44 : 34" :lost="lost(m)" />

                            <LcarsSignalBars v-else-if="m.viz === 'signal'" :value="cur(m)" :range="m.range"
                                :label="m.label" :lost="lost(m)" />

                            <LcarsClimate v-else-if="m.viz === 'climate'" :label="m.label"
                                :temp="G(m.tempSrc)" :humidity="G(m.humSrc)" />

                            <LcarsNavFix v-else-if="m.viz === 'navfix'"
                                :lat="G('gps.latitude')" :lon="G('gps.longitude')" :heading="G('boat.heading')"
                                :cog="G('boat.cog')" :satellites="G('gps.satellites')" :hdop="G('gps.hdop')" />

                            <LcarsSunArc v-else-if="m.viz === 'sunarc'" :sun="tele.metrics.value?.sun" :now-ms="tele.nowTick.value" />

                            <div v-else-if="m.viz === 'badge'" class="badge"
                                :class="{ on: cur(m), off: cur(m) === 0 || cur(m) === false }">
                                {{ badgeText(m, cur(m)) }}
                            </div>
                        </div>

                        <div v-if="m.size === 'hero'" class="lcars-cascade" aria-hidden="true">
                            <div class="col" style="white-space:pre">{{ cascade[0] }}{{ '\n' }}{{ cascade[0] }}</div>
                        </div>
                    </div>
                </div>
            </main>

            <!-- Footer -->
            <footer class="lcars-foot">
                <div class="seg" :class="tele.isStale.value ? 'lost' : 'live'">
                    {{ tele.isStale.value ? '⚠ TELEMETRY STALE' : 'TELEMETRY ONLINE' }}
                </div>
                <div class="seg b">{{ tele.isConnected.value ? 'REVERB LINKED' : 'AWAITING LINK' }}</div>
                <div class="seg">STARDATE {{ stardate }}</div>
            </footer>
        </div>

        <!-- Operable: full-screen analysis that morphs from the clicked reading -->
        <LcarsDetail v-if="detailMetric" :metric="detailMetric" :value="cur(detailMetric)"
            :points="tele.history(detailMetric.key).points" :last-known="tele.history(detailMetric.key).lastKnown"
            :now-ms="tele.nowTick.value" :color="colorFor(detailMetric)" :lost="lost(detailMetric)"
            style="view-transition-name: lcars-morph" @close="closeDetail" />

        <!-- UFP intro splash → MSD -->
        <LcarsSplash v-if="showSplash" :boat-name="boatName" :registry="registry" @done="showSplash = false" />

        <!-- Responsive floor -->
        <div class="lcars-toosmall">
            <div class="pill">LCARS</div>
            <p>This console requires a display at least 1024px wide. Access from a wider terminal.</p>
            <button class="pill" style="cursor:pointer;background:var(--red)" @click="exit">◄ END PROGRAM</button>
        </div>
    </div>
</template>

<style scoped>
.badge { align-self: center; padding: 8px 20px; border-radius: 16px; font-weight: 700; font-size: 20px; background: var(--panel-2); color: var(--lilac); letter-spacing: 0.05em; }
.badge.on { background: var(--orange); color: #000; }
.badge.off { background: var(--red); color: #000; }
.lcars.flash::after { content: ''; position: fixed; inset: 0; background: var(--red); opacity: 0.4; pointer-events: none; animation: lcars-blink 0.2s steps(1) 3; }
.lcars.paused :deep(.lcars-cascade .col) { animation-play-state: paused; }

/* Operable tiles */
.lcars-panel.clickable { cursor: pointer; transition: background 0.18s, transform 0.18s cubic-bezier(0.22, 1, 0.36, 1); }
.lcars-panel.clickable:hover { background: var(--panel-2); transform: translateY(-2px); }
.lcars-panel.clickable:hover .analyse-corner { opacity: 1; }
.lcars-panel.clickable:focus-visible { outline: 3px solid var(--ice); outline-offset: -3px; }
.analyse-corner { position: absolute; top: 10px; right: 16px; z-index: 3; font-size: 11px; letter-spacing: 0.12em; color: var(--ice); opacity: 0; transition: opacity 0.18s; pointer-events: none; }
.s-hero .analyse-corner { top: 16px; right: 22px; }

/* View-Transition morph easing for the operable drill-in */
:global(::view-transition-old(lcars-morph)),
:global(::view-transition-new(lcars-morph)) { animation-duration: 0.5s; animation-timing-function: cubic-bezier(0.22, 1, 0.36, 1); }
@media (prefers-reduced-motion: reduce) {
    .lcars-panel.clickable:hover { transform: none; }
}
</style>
