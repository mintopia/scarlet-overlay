<template>
    <AdminLayout>
        <Head :title="`Data · ${metricLabel}`" />

        <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
            <div>
                <h1 class="text-[22px] font-bold">{{ metricLabel }}</h1>
                <p class="text-[13px] text-text-secondary mt-0.5">
                    <code class="font-mono">{{ metricKey }}</code>
                    <span v-if="metricUnit"> · {{ metricUnit }}</span>
                </p>
            </div>
            <div class="flex gap-1">
                <button
                    v-for="r in ranges"
                    :key="r"
                    type="button"
                    class="range-pill"
                    :class="{ 'range-pill--active': range === r }"
                    @click="range = r"
                >{{ r }}</button>
            </div>
        </div>

        <div v-for="(panel, pi) in panels" :key="panel.id" class="panel p-4 mb-4">
            <div class="flex items-center justify-between mb-3 flex-wrap gap-2">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-[12px] font-semibold text-text-secondary">{{ panelTitle(panel) }}</span>
                    <button
                        v-if="panel.overlayKey"
                        type="button"
                        class="text-[11px] text-error hover:underline"
                        @click="removeOverlay(pi)"
                    >remove overlay</button>
                </div>
                <div class="flex items-center gap-2">
                    <div v-if="!panel.overlayKey" class="w-56">
                        <MetricSelect :options="overlayOptions(panel)" placeholder="+ Overlay metric…" @select="(m) => setOverlay(pi, m)" />
                    </div>
                    <button v-if="pi > 0" type="button" class="text-[12px] text-text-dim hover:text-error" @click="removePanel(pi)">✕</button>
                </div>
            </div>

            <div v-if="panel.loading" class="explorer-skeleton"></div>
            <UplotChart v-else-if="panel.series.length" :series="panel.series" :height="260" />
            <p v-else class="text-[13px] text-text-dim py-10 text-center">No data for this range.</p>
        </div>

        <div class="flex items-center gap-3 flex-wrap">
            <div class="w-72">
                <MetricSelect :options="catalog" placeholder="+ Add graph for metric…" @select="addPanel" />
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import UplotChart from '@/components/Admin/UplotChart.vue';
import MetricSelect from '@/components/Admin/MetricSelect.vue';

const props = defineProps({
    metricKey: { type: String, required: true },
    metricLabel: { type: String, default: '' },
    metricUnit: { type: String, default: '' },
    catalog: { type: Array, default: () => [] },
});

const ranges = ['6h', '24h', '7d', '30d'];
const range = ref('24h');
let nextId = 1;

const panels = ref([{ id: nextId++, primaryKey: props.metricKey, overlayKey: null, loading: false, series: [] }]);

async function fetchSeriesFor(keys) {
    const url = route('admin.data.series', { metrics: keys.join(','), range: range.value });
    const response = await fetch(url, { headers: { Accept: 'application/json' } });
    return response.ok ? await response.json() : {};
}

function metaFor(key) {
    return props.catalog.find((m) => m.key === key) || { label: key, display_unit: '' };
}

async function loadPanel(panel) {
    panel.loading = true;
    try {
        const keys = [panel.primaryKey, panel.overlayKey].filter(Boolean);
        const json = await fetchSeriesFor(keys);
        panel.series = keys.map((key, i) => {
            const s = json[key];
            return {
                key,
                label: s?.label ?? metaFor(key).label,
                unit: s?.unit ?? metaFor(key).display_unit,
                axis: i === 0 ? 'left' : 'right',
                data: s?.data ?? [],
            };
        }).filter((s) => s.data.length);
    } finally {
        panel.loading = false;
    }
}

function reloadAll() {
    panels.value.forEach(loadPanel);
}

function panelTitle(panel) {
    const primary = metaFor(panel.primaryKey).label;
    if (!panel.overlayKey) { return primary; }
    return `${primary} + ${metaFor(panel.overlayKey).label}`;
}

function overlayOptions(panel) {
    return props.catalog.filter((m) => m.key !== panel.primaryKey);
}

function addPanel(metric) {
    const panel = { id: nextId++, primaryKey: metric.key, overlayKey: null, loading: false, series: [] };
    panels.value.push(panel);
    loadPanel(panel);
}

function removePanel(index) {
    panels.value.splice(index, 1);
}

function setOverlay(index, metric) {
    panels.value[index].overlayKey = metric.key;
    loadPanel(panels.value[index]);
}

function removeOverlay(index) {
    panels.value[index].overlayKey = null;
    loadPanel(panels.value[index]);
}

watch(range, reloadAll);
onMounted(reloadAll);
</script>

<style scoped>
.range-pill {
    font-size: 11px;
    font-weight: 700;
    padding: 5px 12px;
    border-radius: 6px;
    border: 1px solid var(--color-border);
    background: transparent;
    color: var(--color-text-dim);
    cursor: pointer;
    transition: all 0.15s;
}
.range-pill--active {
    background: var(--color-scarlet);
    border-color: var(--color-scarlet);
    color: white;
}
.explorer-skeleton {
    height: 260px;
    border-radius: 8px;
    background: linear-gradient(90deg, var(--color-bg) 25%, var(--color-border-light) 50%, var(--color-bg) 75%);
    background-size: 200% 100%;
    animation: explorer-shimmer 1.4s ease-in-out infinite;
}
@keyframes explorer-shimmer {
    from { background-position: 200% 0; }
    to { background-position: -200% 0; }
}
@media (prefers-reduced-motion: reduce) {
    .explorer-skeleton { animation: none; }
}
</style>
