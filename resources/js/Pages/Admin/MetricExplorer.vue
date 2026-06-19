<template>
    <AdminLayout>
        <Head :title="`Data · ${metricLabel}`" />

        <div class="flex items-center justify-between mb-5">
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

        <div class="panel p-4">
            <div v-if="loading" class="explorer-skeleton"></div>
            <pre v-else class="text-[11px] text-text-dim overflow-x-auto">{{ debugSummary }}</pre>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    metricKey: { type: String, required: true },
    metricLabel: { type: String, default: '' },
    metricUnit: { type: String, default: '' },
    catalog: { type: Array, default: () => [] },
});

const ranges = ['6h', '24h', '7d', '30d'];
const range = ref('24h');
const loading = ref(false);
const seriesData = ref({});

async function fetchSeries() {
    loading.value = true;
    try {
        const url = route('admin.data.series', { metrics: props.metricKey, range: range.value });
        const response = await fetch(url, { headers: { Accept: 'application/json' } });
        seriesData.value = response.ok ? await response.json() : {};
    } catch {
        seriesData.value = {};
    } finally {
        loading.value = false;
    }
}

const debugSummary = computed(() => {
    const s = seriesData.value[props.metricKey];
    if (!s) { return 'No data'; }
    return `${s.label} (${s.unit}) — ${s.data.length} points, current ${s.current ?? '—'}`;
});

watch(range, fetchSeries);
onMounted(fetchSeries);
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
