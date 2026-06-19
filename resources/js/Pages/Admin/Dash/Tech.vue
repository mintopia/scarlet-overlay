<template>
    <div>
        <!-- Tech dashboard — implemented in Task 5.3 -->
    </div>
</template>

<script setup>
defineProps({
    contracts: Object,
    bitrateHistory: Array,
    droppedHistory: Array,
    housePowerHistory: Array,
    ecoflowPowerHistory: Array,
    pullEnabled: Boolean,
    reverb: Object,
    reverbKey: String,
});
</script>
