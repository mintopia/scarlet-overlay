<template>
    <AdminLayout :wide="true">
        <Head title="Ship's Log" />
        <div class="flex items-baseline justify-between mb-6">
            <h1 class="text-[22px] font-bold">Ship's Log</h1>
            <div class="flex items-center gap-3">
                <select v-model="selectedTz" class="field-input text-[13px] py-1.5 px-3" style="width: auto; height: auto">
                    <option value="local">Local ({{ localTzLabel }})</option>
                    <option value="UTC">UTC</option>
                    <option v-if="positionTimezone && positionTimezone !== 'UTC'" :value="positionTimezone">Position ({{ positionTimezone }})</option>
                </select>
                <select v-model="selectedPeriod" @change="changePeriod" class="field-input text-[13px] py-1.5 px-3" style="width: auto; height: auto">
                    <option v-if="hasActiveJourney" value="journey">{{ journeyTitle || 'Current Journey' }}</option>
                    <option value="6h">Last 6 hours</option>
                    <option value="12h">Last 12 hours</option>
                    <option value="24h">Last 24 hours</option>
                    <option value="48h">Last 48 hours</option>
                    <option value="168h">Last 7 days</option>
                </select>
            </div>
        </div>

        <LogTable :rows="rows" :timezone="activeTz" />
    </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import LogTable from '@/components/LogTable.vue';

const props = defineProps({
    rows: Array,
    period: String,
    hasActiveJourney: Boolean,
    journeyTitle: String,
    positionTimezone: String,
});

const localTzLabel = Intl.DateTimeFormat().resolvedOptions().timeZone.replace(/_/g, ' ');
const selectedPeriod = ref(props.period);
const selectedTz = ref(localStorage.getItem('scarlet_log_tz') || 'local');

const activeTz = computed(() => {
    const tz = selectedTz.value;
    if (tz === 'local') return undefined;
    localStorage.setItem('scarlet_log_tz', tz);
    return tz;
});

import { watch } from 'vue';
watch(selectedTz, (val) => {
    localStorage.setItem('scarlet_log_tz', val);
});

function changePeriod() {
    router.get('/admin/log', { period: selectedPeriod.value }, { preserveState: true });
}
</script>
