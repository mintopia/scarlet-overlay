<template>
    <AdminLayout>
        <Head title="Dashboard" />
        <h1 class="text-[22px] font-bold mb-6">Dashboard</h1>

        <!-- Active Journey / Start Journey -->
        <div class="panel mb-4">
            <div class="panel-title mb-3">Journey</div>
            <template v-if="activeJourney">
                <div class="flex items-baseline justify-between mb-2">
                    <div>
                        <span class="text-[16px] font-semibold">{{ activeJourney.title }}</span>
                        <span class="ml-2 text-[10px] font-semibold tracking-wide px-2 py-0.5 rounded bg-green/10 text-green">ACTIVE</span>
                    </div>
                </div>
                <div class="space-y-2 text-[13px] mb-4">
                    <div class="data-row"><span>Elapsed</span><span>{{ fmtDuration(activeJourney.duration) }}</span></div>
                    <div class="data-row"><span>Distance</span><span>{{ activeJourney.distance }} nm</span></div>
                    <div class="data-row"><span>Speed</span><span class="text-scarlet">{{ fmt(boat?.speed_sog) }} kn</span></div>
                </div>
                <div class="flex gap-2">
                    <Link :href="`/admin/journeys/${activeJourney.id}/end`" method="post" as="button" class="btn btn--danger">End Journey</Link>
                    <Link href="/admin/journeys" class="btn btn--ghost">All Journeys</Link>
                </div>
            </template>
            <template v-else>
                <p class="text-[13px] text-text-secondary mb-3">No active journey.</p>
                <div class="flex gap-2">
                    <Link href="/admin/journeys/create" class="btn btn--primary">Start Journey</Link>
                    <Link href="/admin/journeys/import" class="btn btn--ghost">Import from History</Link>
                </div>
            </template>
        </div>

        <!-- Boat Status -->
        <div class="panel mb-4">
            <div class="flex items-baseline justify-between mb-3">
                <span class="panel-title">Boat Status</span>
                <Link href="/admin/metrics" class="text-[12px] text-scarlet font-medium hover:underline">View Metrics →</Link>
            </div>
            <div class="strip">
                <div class="strip-cell"><div class="strip-label">SOG</div><div class="strip-value text-scarlet">{{ fmt(boat?.speed_sog) }}</div><div class="strip-unit">kn</div></div>
                <div class="strip-cell"><div class="strip-label">Heading</div><div class="strip-value">{{ fmt(boat?.heading, 0) }}</div><div class="strip-unit">°</div></div>
                <div class="strip-cell"><div class="strip-label">Depth</div><div class="strip-value" style="color: oklch(0.55 0.15 240)">{{ fmt(boat?.depth) }}</div><div class="strip-unit">m</div></div>
                <div class="strip-cell"><div class="strip-label">Battery</div><div class="strip-value text-green">{{ fmt(boat?.house_battery_voltage, 2) }}</div><div class="strip-unit">V</div></div>
            </div>
            <div v-if="gps?.latitude != null" class="text-[12px] text-text-secondary mt-2 tabular-nums">
                {{ fmtCoord(gps.latitude, gps.longitude) }}
            </div>
        </div>

        <!-- Tracker Status -->
        <div class="panel mb-4">
            <div class="flex items-baseline justify-between mb-3">
                <span class="panel-title">Tracker</span>
                <Link href="/admin/tracker" class="text-[12px] text-scarlet font-medium hover:underline">View Tracker →</Link>
            </div>
            <div class="space-y-2 text-[13px]">
                <div class="data-row">
                    <span>Connection</span>
                    <span>{{ tracker?.wifi_rssi != null ? 'WiFi' : tracker?.lte_rssi != null ? 'LTE' : 'Disconnected' }}</span>
                </div>
                <div class="data-row">
                    <span>Signal</span>
                    <span>{{ tracker?.wifi_rssi != null ? fmt(tracker.wifi_rssi, 0) + ' dBm' : tracker?.lte_rssi != null ? fmt(tracker.lte_rssi, 0) + ' dBm' : '—' }}</span>
                </div>
                <div class="data-row">
                    <span>Battery</span>
                    <span>{{ tracker?.battery_percent != null ? fmt(tracker.battery_percent, 0) + '%' : '—' }}</span>
                </div>
            </div>
        </div>

        <!-- Recent Journeys -->
        <div class="panel mb-4" v-if="recentJourneys.length">
            <div class="flex items-baseline justify-between mb-3">
                <span class="panel-title">Recent Journeys</span>
                <Link href="/admin/journeys" class="text-[12px] text-scarlet font-medium hover:underline">View All →</Link>
            </div>
            <div class="space-y-2">
                <Link v-for="j in recentJourneys" :key="j.id" :href="`/journey/${j.slug}`" class="flex items-baseline justify-between text-[13px] py-1.5 hover:text-scarlet transition-colors">
                    <span class="font-medium">{{ j.title }}</span>
                    <span class="text-text-dim tabular-nums">{{ fmtDate(j.started_at) }} · {{ j.distance }} nm</span>
                </Link>
            </div>
        </div>

        <!-- Quick Links -->
        <div class="panel">
            <div class="panel-title mb-3">Quick Links</div>
            <div class="flex flex-wrap gap-2">
                <Link href="/admin/settings" class="btn btn--ghost">Settings</Link>
                <Link href="/admin/stream" class="btn btn--ghost">Stream Monitor</Link>
                <Link href="/admin/team" class="btn btn--ghost">Team</Link>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineProps({
    boat: Object,
    gps: Object,
    tracker: Object,
    activeJourney: Object,
    recentJourneys: Array,
});

function fmt(val, decimals = 1) {
    if (val == null || isNaN(val)) return '—';
    return Number(val).toFixed(decimals);
}

function fmtDuration(seconds) {
    if (seconds == null) return '—';
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    return h > 0 ? `${h}h ${m}m` : `${m}m`;
}

function fmtDate(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });
}

function fmtCoord(lat, lon) {
    if (lat == null || lon == null) return '';
    const latDir = lat >= 0 ? 'N' : 'S';
    const lonDir = lon >= 0 ? 'E' : 'W';
    return `${Math.abs(lat).toFixed(4)}°${latDir}  ${Math.abs(lon).toFixed(4)}°${lonDir}`;
}
</script>

<style scoped>
.panel {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: 10px;
    padding: 16px;
}

.panel-title {
    font-size: 15px;
    font-weight: 600;
}

.data-row {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
}

.data-row span:first-child { color: var(--color-text-secondary); }
.data-row span:last-child { font-variant-numeric: tabular-nums; font-weight: 600; }

.strip {
    display: flex;
    background: var(--color-bg);
    border: 1px solid var(--color-border-light);
    border-radius: 8px;
    overflow: hidden;
}

.strip-cell { flex: 1; padding: 10px 12px; text-align: center; }
.strip-cell + .strip-cell { border-left: 1px solid var(--color-border-light); }
.strip-label { font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; color: var(--color-text-dim); margin-bottom: 2px; }
.strip-value { font-size: 20px; font-weight: 700; font-variant-numeric: tabular-nums; line-height: 1; }
.strip-unit { font-size: 10px; color: var(--color-text-dim); margin-top: 2px; }

.btn {
    display: inline-flex;
    align-items: center;
    height: 36px;
    padding: 0 14px;
    border-radius: 7px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.12s ease-out;
}

.btn--primary { background: oklch(0.54 0.22 27); color: white; }
.btn--primary:hover { background: oklch(0.48 0.22 27); }
.btn--ghost { background: var(--color-bg); border: 1px solid var(--color-border); color: var(--color-text-secondary); }
.btn--ghost:hover { border-color: var(--color-text-dim); color: var(--color-text-primary); }
.btn--danger { background: oklch(0.55 0.20 25); color: white; }
.btn--danger:hover { background: oklch(0.48 0.20 25); }
</style>
