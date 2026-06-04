<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useScarletMetrics } from '../../composables/useScarletMetrics';
import { useWeatherProps } from '../../composables/useWeatherProps';
import ScarletWeather from '../../components/ScarletWeather.vue';
import ScarletLiveBadge from '../../components/ScarletLiveBadge.vue';
import ScarletBottomBadges from '../../components/ScarletBottomBadges.vue';
import ScarletLowerThird from '../../components/ScarletLowerThird.vue';
import ScarletCompass from '../../components/ScarletCompass.vue';

const props = defineProps({
    initialMetrics: Object,
    boatName: String,
    passageFrom: String,
    passageTo: String,
    portName: String,
    feedUrl: { type: String, default: '' },
});

const {
    boat, gps, weather, lastUpdate, staleKeys,
    clock, clockDate,
    coordText, isOffline, statusText, statusClass, lastUpdateText,
    wxTemp, wxCondition, wxIcon, wxSeaTemp, wxWindSpeed, wxWindDir, wxWaveHeight, wxWavePeriod,
    portName, passageFrom, passageTo, boatName,
} = useScarletMetrics({
    initialMetrics: props.initialMetrics,
    portName: props.portName,
    passageFrom: props.passageFrom,
    passageTo: props.passageTo,
    boatName: props.boatName,
});

const weatherProps = useWeatherProps(weather, { wxIcon, wxTemp, wxCondition, wxSeaTemp, wxWindSpeed, wxWindDir, wxWaveHeight, wxWavePeriod });

const compassHeading = computed(() => boat.value?.heading ?? boat.value?.cog ?? 0);
const compassWind = computed(() => {
    const dir = weather.value?.wind?.direction;
    return dir != null ? Number(dir) : null;
});

const liveBadgeText = computed(() => isOffline.value ? 'OFFLINE' : 'LIVE');
const liveBadgeExt = computed(() => {
    if (isOffline.value) return 'Telemetry Unavailable';
    if (lastUpdateText.value) return `Updated ${lastUpdateText.value}`;
    return 'Connecting...';
});
</script>

<template>
    <Head :title="`${boatName} — Camera`" />

    <div class="camera-overlay">
        <iframe
            v-if="props.feedUrl"
            :src="props.feedUrl"
            class="feed-iframe"
            allow="autoplay; fullscreen"
            allowfullscreen
            frameborder="0"
        ></iframe>
        <div v-else class="feed-placeholder"></div>

        <!-- TOP-LEFT: LIVE badge -->
        <div class="tl-cluster">
            <ScarletLiveBadge :text="liveBadgeText" :extension="liveBadgeExt" />
        </div>

        <!-- TOP-RIGHT: Weather -->
        <div class="wx-cluster">
            <ScarletWeather v-bind="weatherProps" />
        </div>

        <!-- BOTTOM-LEFT: Compass + coords -->
        <div class="bl-cluster">
            <ScarletCompass :heading="compassHeading" :wind-direction="compassWind" :size="110" />
            <ScarletBottomBadges :coord-text="coordText" />
        </div>

        <!-- LOWER THIRD -->
        <div class="lt-position">
            <ScarletLowerThird
                :boat-name="boatName"
                :boat="boat"
                :status-text="statusText"
                :status-class="statusClass"
                :port-name="portName"
                :passage-from="passageFrom"
                :passage-to="passageTo"
                :clock="clock"
                :clock-date="clockDate"
                :stale-keys="staleKeys"
            />
        </div>
    </div>
</template>

<style scoped>
.camera-overlay {
    position: fixed;
    inset: 0;
    font-family: 'Outfit', system-ui, sans-serif;
    font-variant-numeric: tabular-nums;
    color: oklch(0.96 0.005 70);
    overflow: hidden;
    background: oklch(0.05 0.01 40);
}

.feed-iframe {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    border: none;
    z-index: 0;
}

.feed-placeholder {
    position: absolute;
    inset: 0;
    background: oklch(0.05 0.01 40);
    z-index: 0;
}

.tl-cluster {
    position: absolute;
    top: 16px;
    left: 16px;
    z-index: 10;
}

.wx-cluster {
    position: absolute;
    top: 16px;
    right: 16px;
    z-index: 10;
}

.bl-cluster {
    position: absolute;
    bottom: 88px;
    left: 16px;
    z-index: 10;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
}

.lt-position {
    position: absolute;
    bottom: 12px;
    left: 12px;
    right: 12px;
    z-index: 10;
}

/* Scale overlay text to match the broadcast overlay (~1.5x) */
.camera-overlay :deep(.pill-lbl) { font-size: 12px; }
.camera-overlay :deep(.pill-val) { font-size: 21px; }
.camera-overlay :deep(.pill-sub) { font-size: 14px; }
.camera-overlay :deep(.pill) { padding: 10px 16px; min-width: 72px; }
.camera-overlay :deep(.pill--hero) { padding: 10px 18px; }
.camera-overlay :deep(.pill--hero .pill-val) { font-size: 24px; }
.camera-overlay :deep(.wx-icon) { font-size: 30px; }

.camera-overlay :deep(.lower-third) { min-height: 64px; border-radius: 12px; }
.camera-overlay :deep(.lt-brand) { padding: 0 30px; }
.camera-overlay :deep(.lt-brand::after) { right: -22px; width: 22px; }
.camera-overlay :deep(.lt-name) { font-size: 27px; }
.camera-overlay :deep(.lt-body) { padding: 14px 24px 14px 40px; gap: 14px; }
.camera-overlay :deep(.lt-label) { font-size: 12px; }
.camera-overlay :deep(.lt-val) { font-size: 24px; }
.camera-overlay :deep(.lt-sep) { height: 32px; }
.camera-overlay :deep(.lt-status) { font-size: 15px; padding: 6px 14px; }
.camera-overlay :deep(.lt-passage) { font-size: 21px; }
.camera-overlay :deep(.lt-port-label) { font-size: 16px; }
.camera-overlay :deep(.lt-time) { font-size: 24px; }
.camera-overlay :deep(.lt-date) { font-size: 14px; }

.camera-overlay :deep(.coord-badge) { font-size: 20px; padding: 9px 18px; }
.camera-overlay :deep(.speed-legend) { font-size: 15px; padding: 7px 16px; }
.camera-overlay :deep(.legend-gradient) { width: 72px; }
.camera-overlay :deep(.compass-heading) { font-size: 15px; padding: 2px 8px; }
</style>
