<template>
    <AdminLayout>
        <Head :title="metric.label" />

        <!-- Page header / toolbar -->
        <div class="toolbar">
            <div class="toolbar-left">
                <Link href="/admin/metrics" class="back-link">&larr; Metrics</Link>
                <span class="toolbar-sep">/</span>
                <div class="metric-select-wrap">
                    <select
                        :value="metric.slug"
                        class="metric-select"
                        @change="switchMetric($event.target.value)"
                    >
                        <optgroup v-for="(groupMetrics, group) in metrics" :key="group" :label="groupLabel(group)">
                            <option v-for="(m, slug) in groupMetrics" :key="slug" :value="slug">
                                {{ m.label }}{{ m.unit ? ` (${m.unit})` : '' }}
                            </option>
                        </optgroup>
                    </select>
                </div>
            </div>

            <div class="toolbar-right">
                <!-- Recent presets -->
                <div class="preset-cluster">
                    <button
                        v-for="p in recentPresets"
                        :key="p"
                        class="preset-btn"
                        :class="{ 'preset-btn--active': range === p && !zoomed }"
                        @click="switchRange(p)"
                    >{{ p }}</button>
                </div>
                <!-- Extended presets -->
                <div class="preset-cluster">
                    <button
                        v-for="p in extendedPresets"
                        :key="p"
                        class="preset-btn"
                        :class="{ 'preset-btn--active': range === p && !zoomed }"
                        @click="switchRange(p)"
                    >{{ p }}</button>
                </div>
                <!-- Special presets -->
                <div class="preset-cluster">
                    <button
                        class="preset-btn preset-btn--passage"
                        :class="{ 'preset-btn--active': range === 'passage' && !zoomed, 'preset-btn--disabled': !passage.available }"
                        :disabled="!passage.available"
                        :title="passage.available ? '' : 'No journeys'"
                        @click="switchRange('passage')"
                    >&#9875; Passage</button>
                    <button
                        class="preset-btn"
                        :class="{ 'preset-btn--active': range === 'custom' || zoomed }"
                        @click="showCustomPicker = !showCustomPicker"
                    >Custom</button>
                </div>

                <span class="toolbar-sep">|</span>

                <!-- Refresh interval -->
                <select v-model.number="refreshInterval" class="refresh-select" @change="restartRefresh">
                    <option :value="0">Off</option>
                    <option :value="15">15s</option>
                    <option :value="30">30s</option>
                    <option :value="60">1m</option>
                    <option :value="300">5m</option>
                </select>

                <!-- Reset zoom button -->
                <button v-if="zoomed" class="preset-btn preset-btn--reset" @click="resetZoom">Reset zoom</button>
            </div>
        </div>

        <!-- Custom date picker (inline dropdown) -->
        <div v-if="showCustomPicker" class="custom-picker">
            <label class="custom-picker-label">
                From
                <input type="datetime-local" v-model="customStart" class="custom-picker-input" />
            </label>
            <label class="custom-picker-label">
                To
                <input type="datetime-local" v-model="customEnd" class="custom-picker-input" />
            </label>
            <button class="custom-picker-apply" @click="applyCustomRange">Apply</button>
        </div>

        <!-- Chart panel (placeholder for Task 5) -->
        <div class="panel mt-4">
            <div class="panel-head">
                <span class="panel-title">{{ metric.label }}</span>
                <span class="text-[12px] text-text-dim tabular-nums">{{ data?.length ?? 0 }} data points</span>
            </div>
            <div class="text-center text-text-dim py-20 text-[13px]">
                Chart renders in Task 5
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { ref, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    metric: Object,
    metrics: Object,
    data: Array,
    overlays: Array,
    range: String,
    start: Number,
    end: Number,
    step: String,
    refresh: Number,
    passage: Object,
});

const recentPresets = ['1h', '6h', '24h'];
const extendedPresets = ['3d', '7d', '30d'];

const zoomed = ref(false);
const showCustomPicker = ref(false);
const customStart = ref('');
const customEnd = ref('');
const refreshInterval = ref(props.refresh);
let refreshTimer = null;

const groupLabels = {
    navigation: 'Navigation',
    wind: 'Wind',
    power: 'Power',
    cabin: 'Cabin',
    tanks: 'Tanks',
    tracker: 'Tracker',
};

function groupLabel(group) {
    return groupLabels[group] || group;
}

function buildUrl(params) {
    const base = '/admin/explore';
    const query = new URLSearchParams();
    for (const [k, v] of Object.entries(params)) {
        if (v != null && v !== '') query.set(k, v);
    }
    return `${base}?${query.toString()}`;
}

function switchMetric(slug) {
    router.get(buildUrl({ metric: slug, range: props.range }));
}

function switchRange(range) {
    zoomed.value = false;
    showCustomPicker.value = false;
    router.get(buildUrl({ metric: props.metric.slug, range }));
}

function applyCustomRange() {
    if (!customStart.value || !customEnd.value) return;
    const start = Math.floor(new Date(customStart.value).getTime() / 1000);
    const end = Math.floor(new Date(customEnd.value).getTime() / 1000);
    showCustomPicker.value = false;
    router.get(buildUrl({ metric: props.metric.slug, range: 'custom', start, end }));
}

function resetZoom() {
    zoomed.value = false;
    switchRange(props.range === 'custom' ? '24h' : props.range);
}

function restartRefresh() {
    clearInterval(refreshTimer);
    if (refreshInterval.value > 0) {
        refreshTimer = setInterval(() => doRefresh(), refreshInterval.value * 1000);
    }
}

async function doRefresh() {
    if (zoomed.value) return;
    const end = Math.floor(Date.now() / 1000);
    const start = end - (props.end - props.start);
    const url = `/admin/explore/series?metric=${props.metric.slug}&start=${start}&end=${end}&step=${props.step}`;
    try {
        const res = await fetch(url);
        if (!res.ok) return;
    } catch {
        // Silent failure — last data stays rendered
    }
}

onMounted(() => {
    restartRefresh();
});

onUnmounted(() => {
    clearInterval(refreshTimer);
});
</script>

<style scoped>
.toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 8px;
}

.toolbar-left {
    display: flex;
    align-items: center;
    gap: 8px;
}

.toolbar-right {
    display: flex;
    align-items: center;
    gap: 4px;
    flex-wrap: wrap;
}

.toolbar-sep {
    color: var(--color-border);
}

.back-link {
    font-size: 13px;
    color: var(--color-text-secondary);
    text-decoration: none;
}

.back-link:hover {
    color: var(--color-text-primary);
}

.metric-select-wrap {
    position: relative;
}

.metric-select {
    font-weight: 600;
    font-size: 15px;
    border: none;
    background: transparent;
    color: var(--color-text-primary);
    cursor: pointer;
    padding: 2px 4px;
    -webkit-appearance: none;
    appearance: none;
}

.metric-select:focus-visible {
    outline: 2px solid var(--color-scarlet);
    outline-offset: 2px;
    border-radius: 4px;
}

.preset-cluster {
    display: flex;
    gap: 3px;
}

.preset-btn {
    padding: 4px 8px;
    background: var(--color-bg);
    border: 1px solid var(--color-border);
    border-radius: 5px;
    font-size: 11px;
    color: var(--color-text-secondary);
    cursor: pointer;
    font-weight: 500;
    transition: background 0.1s ease-out, color 0.1s ease-out;
}

.preset-btn:hover {
    border-color: var(--color-text-dim);
    color: var(--color-text-primary);
}

.preset-btn--active {
    background: var(--color-scarlet);
    border-color: var(--color-scarlet);
    color: white;
}

.preset-btn--passage {
    color: var(--color-scarlet);
    font-weight: 600;
}

.preset-btn--passage.preset-btn--active {
    background: var(--color-scarlet);
    color: white;
}

.preset-btn--disabled {
    opacity: 0.4;
    cursor: default;
}

.preset-btn--reset {
    color: var(--color-scarlet);
    border-color: var(--color-scarlet);
    background: transparent;
}

.refresh-select {
    font-size: 11px;
    border: 1px solid var(--color-border);
    border-radius: 5px;
    padding: 4px 6px;
    background: var(--color-bg);
    color: var(--color-text-secondary);
    cursor: pointer;
}

.custom-picker {
    display: flex;
    gap: 12px;
    align-items: flex-end;
    padding: 12px 16px;
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: 10px;
    margin-bottom: 8px;
    flex-wrap: wrap;
}

.custom-picker-label {
    font-size: 11px;
    font-weight: 600;
    color: var(--color-text-dim);
    text-transform: uppercase;
    letter-spacing: 0.04em;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.custom-picker-input {
    font-size: 13px;
    border: 1px solid var(--color-border);
    border-radius: 6px;
    padding: 6px 10px;
    background: var(--color-bg);
    color: var(--color-text-primary);
}

.custom-picker-input:focus {
    outline: 2px solid var(--color-scarlet);
    outline-offset: -1px;
}

.custom-picker-apply {
    padding: 6px 16px;
    background: var(--color-scarlet);
    color: white;
    border: none;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
}

.custom-picker-apply:hover {
    background: var(--color-scarlet-hover);
}

.panel {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: 10px;
    padding: 16px;
}

.panel-head {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    margin-bottom: 8px;
}

.panel-title {
    font-size: 15px;
    font-weight: 600;
}

/* Mobile: stack toolbar rows */
@media (max-width: 767px) {
    .toolbar {
        flex-direction: column;
        align-items: stretch;
    }
    .toolbar-right {
        overflow-x: auto;
        flex-wrap: nowrap;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
    }
    .toolbar-right::-webkit-scrollbar { display: none; }
    .custom-picker { flex-direction: column; }
}
</style>
