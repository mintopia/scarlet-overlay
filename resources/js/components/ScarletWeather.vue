<script setup>
defineProps({
    wxIcon: String,
    wxTemp: String,
    wxCondition: String,
    wxSeaTemp: String,
    wxWindSpeed: String,
    wxWindDir: String,
    wxWaveHeight: String,
    wxWavePeriod: String,
});
</script>

<template>
    <div class="wx-strip">
        <div class="pill pill--hero">
            <span class="wx-icon">{{ wxIcon }}</span>
            <div>
                <div class="pill-val">{{ wxTemp }}</div>
                <div class="pill-sub">{{ wxCondition }}</div>
            </div>
        </div>
        <div class="pill">
            <div class="pill-lbl">SEA</div>
            <div class="pill-val">{{ wxSeaTemp }}</div>
        </div>
        <div class="pill">
            <div class="pill-lbl">WIND</div>
            <div class="pill-val">{{ wxWindSpeed }}</div>
            <div class="pill-sub">{{ wxWindDir }}</div>
        </div>
        <div class="pill">
            <div class="pill-lbl">WAVES</div>
            <div class="pill-val">{{ wxWaveHeight }}</div>
            <div class="pill-sub">{{ wxWavePeriod }}</div>
        </div>
    </div>
</template>

<style scoped>
.wx-strip {
    display: flex;
    gap: 5px;
    align-items: stretch;
}

.pill {
    background: oklch(0.08 0.008 40 / 0.72);
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    border-radius: 7px;
    padding: 7px 12px;
    text-align: center;
    border: 1px solid oklch(0.32 0.01 40 / 0.18);
    min-width: 52px;
}

.pill-lbl {
    font-size: 8px;
    font-weight: 600;
    letter-spacing: 0.04em;
    color: oklch(0.62 0.008 70);
    margin-bottom: 2px;
}

.pill-val {
    font-size: 14px;
    font-weight: 500;
    color: oklch(0.96 0.005 70);
    line-height: 1.1;
}

.pill-sub {
    font-size: 9px;
    color: oklch(0.62 0.008 70);
    margin-top: 2px;
}

.pill--hero {
    display: flex;
    gap: 8px;
    align-items: center;
    text-align: left;
    padding: 7px 14px;
}

.wx-icon {
    font-size: 20px;
    line-height: 1;
    flex-shrink: 0;
}

.pill--hero .pill-val {
    font-size: 16px;
    font-weight: 700;
}
</style>
