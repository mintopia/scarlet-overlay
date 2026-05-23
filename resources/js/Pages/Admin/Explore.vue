<template>
    <AdminLayout>
        <Head title="Explore" />
        <div>
            <h1 class="text-[22px] font-bold">{{ metric.label }}</h1>
            <p class="text-text-dim text-[13px]">Explore placeholder</p>
        </div>
    </AdminLayout>
</template>

<script setup>
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineProps({
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
</script>
