<template>
    <AdminLayout>
        <Head title="Explore" />

        <div class="explore-dashboard">
            <!-- Header -->
            <div class="explore-header">
                <h1 class="explore-title">Explore</h1>
                <div class="explore-header-right">
                    <span class="staleness" :class="stalenessClass">Updated {{ stalenessText }}</span>
                </div>
            </div>

            <!-- Search -->
            <div class="search-bar">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input v-model="search" type="text" placeholder="Filter metrics..." class="search-input" />
            </div>

            <!-- Metric groups -->
            <div v-for="(groupConfig, groupKey) in groups" :key="groupKey" class="metric-group" :class="{ 'metric-group--hidden': !hasVisibleMetrics(groupKey) }">
                <div class="group-label" :style="{ color: groupConfig.color }">{{ groupConfig.label }}</div>

                <!-- Expanded group: show all rows -->
                <div v-if="isExpanded(groupKey) && hasVisibleMetrics(groupKey)" class="group-panel">
                    <div
                        v-for="(metric, slug) in filteredGroupMetrics(groupKey)"
                        :key="slug"
                        class="metric-row"
                        :class="{ 'metric-row--last': isLastInGroup(groupKey, slug) }"
                        @click="openMetric(slug)"
                    >
                        <div class="metric-row-label">
                            <span class="metric-name">{{ metric.label }}</span>
                        </div>
                        <div class="metric-row-value">
                            <!-- Mini compass for compass type -->
                            <div v-if="metric.dashboard_indicator === 'mini_compass'" class="mini-compass">
                                <svg width="22" height="22" viewBox="0 0 22 22">
                                    <circle cx="11" cy="11" r="10" fill="none" :stroke="groupConfig.color" stroke-opacity="0.2" stroke-width="1.5"/>
                                    <text x="11" y="5" text-anchor="middle" font-size="5" font-weight="700" fill="currentColor" class="compass-n">N</text>
                                    <line
                                        x1="11" y1="11"
                                        :x2="11 + 7 * Math.sin((currentValues[slug] ?? 0) * Math.PI / 180)"
                                        :y2="11 - 7 * Math.cos((currentValues[slug] ?? 0) * Math.PI / 180)"
                                        :stroke="metric.color || groupConfig.color"
                                        stroke-width="1.5"
                                        stroke-linecap="round"
                                    />
                                </svg>
                            </div>

                            <!-- Level bar for gauge type -->
                            <div v-else-if="metric.dashboard_indicator === 'level_bar'" class="level-bar">
                                <div class="level-bar-track">
                                    <div class="level-bar-fill" :style="{ width: Math.min(100, Math.max(0, currentValues[slug] ?? 0)) + '%', background: metric.color }"></div>
                                </div>
                            </div>

                            <!-- Trend arrow for standard -->
                            <div v-else class="trend-spacer"></div>

                            <!-- Value -->
                            <div class="metric-value" :style="{ color: valueColor(metric, slug) }">
                                {{ formatValue(metric, slug) }}
                            </div>
                            <div class="metric-unit">{{ displayUnit(metric, slug) }}</div>
                        </div>
                        <svg class="row-chevron" width="7" height="12" viewBox="0 0 7 12"><polyline points="1,1 6,6 1,11" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </div>
                </div>

                <!-- Collapsed group: headline summary -->
                <div v-else-if="!isExpanded(groupKey) && hasVisibleMetrics(groupKey)" class="group-panel group-collapsed" @click="toggleGroup(groupKey)">
                    <div class="collapsed-headlines">
                        <span v-for="slug in (groupConfig.headlines || [])" :key="slug" class="headline-item">
                            <span class="headline-label">{{ abbreviate(metrics[groupKey]?.[slug]?.label) }}</span>
                            <span class="headline-value" :style="{ color: headlineValueColor(groupKey, slug) }">{{ formatValue(metrics[groupKey]?.[slug], slug) }}</span>
                            <span v-if="metrics[groupKey]?.[slug]?.unit && metrics[groupKey]?.[slug]?.type !== 'compass'" class="headline-unit">{{ metrics[groupKey]?.[slug]?.unit }}</span>
                        </span>
                    </div>
                    <div class="collapsed-meta">
                        <span class="collapsed-count">{{ groupMetricCount(groupKey) }} metrics</span>
                        <svg class="row-chevron" width="7" height="12" viewBox="0 0 7 12"><polyline points="1,1 6,6 1,11" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { fmtVal, bearingToCardinal, fmtDuration } from '@/composables/useFormatters.js';

const props = defineProps({
    groups: Object,
    metrics: Object,
    currentValues: Object,
});

const search = ref('');
const expandedGroups = ref({});
const currentValues = ref({ ...props.currentValues });
const lastUpdated = ref(Date.now());
const now = ref(Date.now());

let refreshTimer = null;
let clockTimer = null;

for (const [key, config] of Object.entries(props.groups)) {
    expandedGroups.value[key] = config.expanded;
}

const stalenessSeconds = computed(() => Math.floor((now.value - lastUpdated.value) / 1000));
const stalenessText = computed(() => {
    const s = stalenessSeconds.value;
    if (s < 5) return 'just now';
    if (s < 60) return `${s}s ago`;
    return `${Math.floor(s / 60)}m ago`;
});
const stalenessClass = computed(() => {
    const s = stalenessSeconds.value;
    if (s > 600) return 'staleness--error';
    if (s > 120) return 'staleness--warn';
    return '';
});

function hasVisibleMetrics(groupKey) {
    const groupMetrics = props.metrics[groupKey];
    if (!groupMetrics) return false;
    if (!search.value) return true;
    const q = search.value.toLowerCase();
    return Object.values(groupMetrics).some(m => m.label.toLowerCase().includes(q));
}

function filteredGroupMetrics(groupKey) {
    const groupMetrics = props.metrics[groupKey];
    if (!groupMetrics) return {};
    if (!search.value) return groupMetrics;
    const q = search.value.toLowerCase();
    const filtered = {};
    for (const [slug, metric] of Object.entries(groupMetrics)) {
        if (metric.label.toLowerCase().includes(q)) filtered[slug] = metric;
    }
    return filtered;
}

function isLastInGroup(groupKey, slug) {
    const filtered = filteredGroupMetrics(groupKey);
    const keys = Object.keys(filtered);
    return keys[keys.length - 1] === slug;
}

function groupMetricCount(groupKey) {
    return Object.keys(props.metrics[groupKey] || {}).length;
}

function isExpanded(groupKey) {
    if (search.value) return true;
    return expandedGroups.value[groupKey] ?? false;
}

function toggleGroup(groupKey) {
    expandedGroups.value[groupKey] = !expandedGroups.value[groupKey];
}

function formatValue(metric, slug) {
    if (!metric) return '—';
    const v = currentValues.value[slug];
    if (v == null) return '—';
    switch (metric.type) {
        case 'compass': return `${Math.round(v)}°`;
        case 'gauge': return Math.round(v).toString();
        case 'signed': {
            const str = Math.abs(v) >= 100 ? Math.round(v) : Math.abs(v) >= 10 ? v.toFixed(1) : v.toFixed(2);
            return v > 0 ? `+${str}` : `${str}`;
        }
        case 'duration': return fmtDuration(v);
        default:
            if (Math.abs(v) >= 100) return v.toFixed(0);
            if (Math.abs(v) >= 10) return v.toFixed(1);
            return v.toFixed(2);
    }
}

function displayUnit(metric, slug) {
    if (!metric) return '';
    if (metric.type === 'compass') return bearingToCardinal(currentValues.value[slug]);
    if (metric.type === 'signed' && metric.polarity?.pos_short) {
        const v = currentValues.value[slug];
        if (v == null) return '';
        return v >= 0 ? metric.polarity.pos_short : metric.polarity.neg_short;
    }
    return metric.unit || '';
}

function valueColor(metric, slug) {
    if (!metric) return '';
    const v = currentValues.value[slug];
    if (v == null) return 'var(--color-text-dim)';
    if (metric.type === 'signed') {
        return v >= 0 ? 'var(--color-green)' : 'var(--color-amber)';
    }
    return metric.color || '';
}

function headlineValueColor(groupKey, slug) {
    const metric = props.metrics[groupKey]?.[slug];
    if (!metric) return '';
    const v = currentValues.value[slug];
    if (v == null) return 'var(--color-text-dim)';
    if (metric.type === 'signed') {
        return v >= 0 ? 'var(--color-green)' : 'var(--color-amber)';
    }
    return metric.color || 'var(--color-text-primary)';
}

function abbreviate(label) {
    if (!label) return '';
    const abbrevs = {
        'True Wind Speed': 'TWS',
        'True Wind Direction': 'TWD',
        'Apparent Wind Speed': 'AWS',
        'Apparent Wind Angle': 'AWA',
        'Temp: Main Cabin': 'Main',
        'Temp: Forepeak': 'Forepeak',
        'Temp: Quarterberth': 'Quarter',
        'Barometric Pressure': 'Pressure',
        'Sea Water Temperature': 'Sea Temp',
        'GPS Satellites': 'GPS',
        'LTE Signal': 'LTE',
        'CPU Usage': 'CPU',
    };
    return abbrevs[label] || label;
}

function openMetric(slug) {
    router.get(`/admin/explore?metric=${slug}`);
}

async function refreshValues() {
    try {
        const res = await fetch('/admin/explore/current');
        if (!res.ok) return;
        const data = await res.json();
        currentValues.value = data;
        lastUpdated.value = Date.now();
    } catch {
        // silent
    }
}

onMounted(() => {
    refreshTimer = setInterval(refreshValues, 15000);
    clockTimer = setInterval(() => { now.value = Date.now(); }, 1000);
});

onUnmounted(() => {
    clearInterval(refreshTimer);
    clearInterval(clockTimer);
});
</script>

<style scoped>
.explore-dashboard {
    max-width: 800px;
}

.explore-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}

.explore-title {
    font-size: 18px;
    font-weight: 800;
    letter-spacing: -0.02em;
    color: var(--color-text-primary);
}

.explore-header-right {
    display: flex;
    align-items: center;
    gap: 10px;
}

.staleness {
    font-size: 10px;
    color: var(--color-text-dim);
}
.staleness--warn { color: var(--color-amber); }
.staleness--error { color: var(--color-scarlet); }

.search-bar {
    display: flex;
    align-items: center;
    gap: 8px;
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: 10px;
    padding: 9px 14px;
    margin-bottom: 16px;
    color: var(--color-text-dim);
}

.search-input {
    flex: 1;
    border: none;
    background: none;
    font-size: 13px;
    color: var(--color-text-primary);
    outline: none;
}
.search-input::placeholder { color: var(--color-text-dim); }

.metric-group {
    margin-bottom: 14px;
}
.metric-group--hidden { display: none; }

.group-label {
    font-size: 10px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    margin-bottom: 5px;
}

.group-panel {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: 10px;
    overflow: hidden;
}

.metric-row {
    display: flex;
    align-items: center;
    padding: 10px 14px;
    cursor: pointer;
    border-bottom: 1px solid var(--color-border-light);
    transition: background 0.1s ease-out;
}
.metric-row:hover { background: var(--color-bg); }
.metric-row--last { border-bottom: none; }

.metric-row-label {
    flex: 1;
    min-width: 0;
}

.metric-name {
    font-size: 13px;
    font-weight: 600;
    color: var(--color-text-primary);
}

.metric-row-value {
    display: flex;
    align-items: center;
    gap: 6px;
}

.mini-compass {
    flex-shrink: 0;
    color: var(--color-text-dim);
}
.compass-n { fill: var(--color-scarlet); }

.level-bar {
    flex-shrink: 0;
    width: 48px;
}
.level-bar-track {
    height: 8px;
    background: var(--color-border-light);
    border-radius: 4px;
    overflow: hidden;
}
.level-bar-fill {
    height: 100%;
    border-radius: 4px;
    transition: width 0.3s ease-out;
}

.trend-spacer { width: 10px; flex-shrink: 0; }

.metric-value {
    width: 72px;
    text-align: right;
    font-size: 17px;
    font-weight: 700;
    font-family: 'Nunito Sans', system-ui, sans-serif;
    font-variant-numeric: tabular-nums;
}

.metric-unit {
    width: 32px;
    font-size: 10px;
    color: var(--color-text-dim);
}

.row-chevron {
    margin-left: 10px;
    flex-shrink: 0;
    color: var(--color-border);
}

/* Collapsed group */
.group-collapsed {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 14px;
    cursor: pointer;
    transition: background 0.1s ease-out;
}
.group-collapsed:hover { background: var(--color-bg); }

.collapsed-headlines {
    display: flex;
    gap: 16px;
    font-size: 13px;
    flex-wrap: wrap;
}

.headline-item {
    display: inline-flex;
    align-items: baseline;
    gap: 4px;
}

.headline-label {
    font-size: 11px;
    color: var(--color-text-dim);
}

.headline-value {
    font-weight: 700;
    font-family: 'Nunito Sans', system-ui, sans-serif;
    font-variant-numeric: tabular-nums;
}

.headline-unit {
    font-size: 10px;
    color: var(--color-text-dim);
}

.collapsed-meta {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
}

.collapsed-count {
    font-size: 10px;
    color: var(--color-text-dim);
}

@media (max-width: 767px) {
    .explore-header { flex-direction: column; align-items: flex-start; gap: 8px; }
    .collapsed-headlines { gap: 10px; }
}
</style>
