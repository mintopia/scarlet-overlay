<template>
    <Head :title="journey.title" />
    <div class="journey">
        <p>Journey replay page — to be built in Task 14.</p>
    </div>
</template>

<script setup>
import { Head } from '@inertiajs/vue3';
defineProps({ journey: Object, routeWaypoints: Array, gpsTrack: Array, trackPoints: Array });
</script>
