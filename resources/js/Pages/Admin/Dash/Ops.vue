<template>
    <AdminLayout :breadcrumbs="[{ label: 'Ops' }]">
        <Head title="Ops Dashboard" />
        <div>Ops Dashboard — coming soon</div>
    </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    contracts: Object,
    housePowerHistory: Array,
    ecoflowPowerHistory: Array,
    fuelHistory: Array,
    waterHistory: Array,
    gpsTrack: Array,
    reverb: Object,
    reverbKey: String,
});
</script>
