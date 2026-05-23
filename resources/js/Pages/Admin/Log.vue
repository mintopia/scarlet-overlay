<template>
    <AdminLayout>
        <Head title="Ship's Log" />
        <div class="flex items-baseline justify-between mb-6">
            <h1 class="text-[22px] font-bold">Ship's Log</h1>
            <select v-model="selectedPeriod" @change="changePeriod" class="field-input text-[13px] py-1.5 px-3" style="width: auto; height: auto">
                <option value="6h">Last 6 hours</option>
                <option value="12h">Last 12 hours</option>
                <option value="24h">Last 24 hours</option>
                <option value="48h">Last 48 hours</option>
                <option value="168h">Last 7 days</option>
            </select>
        </div>

        <LogTable :rows="rows" />
    </AdminLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import LogTable from '@/components/LogTable.vue';

const props = defineProps({
    rows: Array,
    period: String,
});

const selectedPeriod = ref(props.period);

function changePeriod() {
    router.get('/admin/log', { period: selectedPeriod.value }, { preserveState: true });
}
</script>
