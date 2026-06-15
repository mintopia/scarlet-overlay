import { ref, shallowRef, computed, onMounted, onUnmounted } from 'vue';
import { pluck } from '../lcars/contract.js';

const WINDOW_MS = 6 * 60 * 60 * 1000;
const MAX_POINTS = 1200;          // safety cap per series (≈ 6h @ 18s)
const PUSH_INTERVAL_MS = 15000;
const STALE_AFTER_MS = 3 * PUSH_INTERVAL_MS;

/**
 * The LCARS data engine. Lean wrapper over the existing public `metrics` Echo
 * channel (no Leaflet baggage). Holds the live snapshot, the per-key history ring
 * buffers (server-preloaded + live-appended), per-reading signal-loss, and a
 * feed-level freshness clock. See PLAN.md §2b.
 */
export function useLcarsTelemetry({ initialMetrics = null } = {}) {
    const metrics = shallowRef(initialMetrics ?? { boat: {}, gps: {}, tracker: {}, weather: null, sun: null });
    const lastUpdate = ref(initialMetrics ? Date.now() : null);
    const nowTick = ref(Date.now());
    const signalLost = ref(new Set());          // keys that arrived present-but-null
    const series = new Map();                    // key -> { points: [{t,value}], lastKnown: {t,value}|null }

    // ── History buffers ────────────────────────────────────────────────
    function ensure(key) {
        if (!series.has(key)) series.set(key, { points: [], lastKnown: null });
        return series.get(key);
    }

    function evict(buf) {
        const cutoff = Date.now() - WINDOW_MS;
        while (buf.points.length && buf.points[0].t < cutoff) buf.points.shift();
        if (buf.points.length > MAX_POINTS) {
            // Decimate: keep every Nth point.
            const stride = Math.ceil(buf.points.length / MAX_POINTS);
            buf.points = buf.points.filter((_, i) => i % stride === 0);
        }
    }

    /** Seed a key's history from a server series payload (sorted, deduped). */
    function loadHistory(key, points) {
        const buf = ensure(key);
        const seen = new Set(buf.points.map(p => p.t));
        const incoming = (points ?? [])
            .filter(p => p && p.value != null && p.t != null && !seen.has(p.t))
            .map(p => ({ t: p.t * 1000, value: p.value }));
        buf.points = [...incoming, ...buf.points].sort((a, b) => a.t - b.t);
        if (buf.points.length) buf.lastKnown = buf.points[buf.points.length - 1];
        evict(buf);
    }

    function appendPoint(key, value, t) {
        const buf = ensure(key);
        const last = buf.points[buf.points.length - 1];
        if (last && t <= last.t) return;                 // reject out-of-order / duplicate
        if (last && last.value === value) { buf.lastKnown = { t, value }; return; } // changed-only
        buf.points.push({ t, value });
        buf.lastKnown = { t, value };
        evict(buf);
    }

    /** Reactive accessor: returns { points, lastKnown, hasInWindow }. */
    function history(key) {
        nowTick.value; // reactive dependency so graphs re-evaluate on tick
        const buf = series.get(key) ?? { points: [], lastKnown: null };
        return {
            points: buf.points,
            lastKnown: buf.lastKnown,
            hasInWindow: buf.points.length > 0,
        };
    }

    // ── Live ingest ────────────────────────────────────────────────────
    function ingest(data) {
        const t = data.timestamp ? Date.parse(data.timestamp) : Date.now();
        const eventT = Number.isNaN(t) ? Date.now() : t;
        const lost = new Set();

        // Merge each group, distinguishing present-null (clear + signal-loss) from absent (keep).
        const next = { ...metrics.value };
        for (const group of ['boat', 'gps', 'tracker', 'weather', 'sun']) {
            if (data[group] == null) continue;           // group absent this tick
            const merged = { ...(next[group] ?? {}) };
            for (const [k, v] of Object.entries(data[group])) {
                if (v != null) {
                    merged[k] = v;
                } else if (merged[k] != null) {
                    merged[k] = null;
                    lost.add(`${group}.${k}`);
                }
            }
            next[group] = merged;
        }
        metrics.value = next;
        signalLost.value = lost;
        lastUpdate.value = Date.now();
        return eventT;
    }

    /**
     * Append a live value for a contract metric. The live tip is stamped at ARRIVAL
     * time (now), not the event's source timestamp: a live console places each incoming
     * reading at the right edge as it arrives. This is monotonic (no out-of-order),
     * robust to source-clock skew/buffering, and keeps replayed feeds in-window. History
     * preload (loadHistory) keeps true source timestamps.
     */
    function appendLive(key, srcPath) {
        const v = pluck(metrics.value, srcPath);
        if (v == null) return;
        appendPoint(key, v, Date.now());
    }

    // ── Freshness (feed-level) ─────────────────────────────────────────
    const isStale = computed(() => lastUpdate.value != null && (nowTick.value - lastUpdate.value) > STALE_AFTER_MS);
    const isConnected = ref(false);
    const lastContact = computed(() => lastUpdate.value);

    // ── Echo wiring + clock ────────────────────────────────────────────
    // The `metrics` channel is shared with the public dashboard composable, which
    // calls Echo.leave('metrics') on unmount. During an SPA swap dashboard→LCARS that
    // teardown can race our subscription and silently kill it ("AWAITING LINK"). To be
    // order-independent we (a) subscribe in onMounted AFTER the swap fully settles
    // (deferred a tick), and (b) on cleanup remove ONLY our own listeners — never
    // Echo.leave the shared channel, so we don't break other consumers either.
    let channel = null;
    let onTip = null;
    const updateHandler = (data) => {
        isConnected.value = true;
        const eventT = ingest(data);
        if (onTip) onTip(eventT);
    };
    const reloadHandler = () => window.location.reload();

    function connect() {
        if (typeof window === 'undefined' || !window.Echo) return;
        channel = window.Echo.channel('metrics');
        channel.listen('.metrics.updated', updateHandler);
        channel.listen('.force-reload', reloadHandler);
    }

    /** Page registers a callback to append live points for the active station. */
    function onLiveTip(cb) { onTip = cb; }

    const clockInterval = setInterval(() => { nowTick.value = Date.now(); }, 1000);

    let connectTimer = null;
    onMounted(() => { connectTimer = setTimeout(connect, 0); });

    function cleanup() {
        clearInterval(clockInterval);
        if (connectTimer) clearTimeout(connectTimer);
        if (channel) {
            channel.stopListening('.metrics.updated', updateHandler);
            channel.stopListening('.force-reload', reloadHandler);
            channel = null;
        }
    }
    onUnmounted(cleanup);

    return {
        metrics, lastUpdate, lastContact, isStale, isConnected, signalLost, nowTick,
        loadHistory, history, appendLive, onLiveTip, cleanup,
    };
}
