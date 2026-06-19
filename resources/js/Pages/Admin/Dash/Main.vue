<template>
    <AdminLayout :breadcrumbs="[{ label: 'Main' }]">
        <Head title="Main Dashboard" />
        <div><!-- 8.2 implements map-led Main dashboard --></div>
    </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    contracts: Object,
    housePowerHistory: Array,
    fuelHistory: Array,
    waterHistory: Array,
    depthHistory: Array,
    speedHistory: Array,
    gpsTrack: Object,
    reverb: Object,
    reverbKey: String,
});
</script>
