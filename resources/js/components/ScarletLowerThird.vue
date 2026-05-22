<script setup>
import { useSpringValue, useAngleSpring } from '../composables/useSpringValue';

const props = defineProps({
    boatName: String,
    boat: Object,
    statusText: String,
    statusClass: String,
    portName: String,
    passageFrom: String,
    passageTo: String,
    clock: String,
    clockDate: String,
});

const animSpeed = useSpringValue(() => props.boat?.speed_sog);
const animHeading = useAngleSpring(() => props.boat?.heading ?? props.boat?.cog);
const animDepth = useSpringValue(() => props.boat?.depth);

function fmtSpring(anim, raw, decimals = 1) {
    if (raw == null) return '--';
    return Number(anim).toFixed(decimals);
}

function fmtHeading(anim, raw) {
    if (raw == null) return '--';
    return Math.round(((anim % 360) + 360) % 360);
}
</script>

<template>
    <div class="lower-third">
        <div class="lt-brand">
            <span class="lt-name">{{ boatName }}</span>
        </div>
        <div class="lt-body">
            <div class="lt-metric">
                <div class="lt-label">SPEED</div>
                <div class="lt-val">{{ fmtSpring(animSpeed, boat?.speed_sog) }} kn</div>
            </div>
            <div class="lt-sep"></div>
            <div class="lt-metric">
                <div class="lt-label">HEADING</div>
                <div class="lt-val">{{ fmtHeading(animHeading, boat?.heading ?? boat?.cog) }}°</div>
            </div>
            <div class="lt-sep"></div>
            <div class="lt-metric">
                <div class="lt-label">DEPTH</div>
                <div class="lt-val">{{ fmtSpring(animDepth, boat?.depth) }} m</div>
            </div>
            <div class="lt-sep"></div>
            <span class="lt-status" :class="statusClass">{{ statusText }}</span>
            <template v-if="statusText === 'In Port' && portName">
                <div class="lt-sep lt-passage-sep"></div>
                <div class="lt-passage-wrap">
                    <div class="lt-passage">
                        <span class="lt-port-label">Currently at</span> <strong>{{ portName }}</strong>
                    </div>
                </div>
            </template>
            <template v-else-if="passageFrom || passageTo">
                <div class="lt-sep lt-passage-sep"></div>
                <div class="lt-passage-wrap">
                    <div class="lt-passage">
                        <strong>{{ passageFrom }}</strong>
                        <span v-if="passageFrom && passageTo"> &rarr; </span>
                        <strong>{{ passageTo }}</strong>
                    </div>
                </div>
            </template>
            <div class="lt-clock">
                <div class="lt-time">{{ clock }}</div>
                <div class="lt-date">{{ clockDate }}</div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.lower-third {
    display: flex;
    align-items: stretch;
    overflow: hidden;
    min-height: 48px;
    border-radius: 10px;
}

.lt-brand {
    background: oklch(0.54 0.22 27);
    padding: 0 14px;
    display: flex;
    align-items: center;
    position: relative;
    flex-shrink: 0;
    z-index: 1;
}

.lt-brand::after {
    content: '';
    position: absolute;
    right: -12px;
    top: -0.5px;
    width: 14px;
    height: calc(100% + 1px);
    background: oklch(0.54 0.22 27);
    clip-path: polygon(0 0, 0 100%, 100% 100%);
}

@media (min-width: 640px) {
    .lt-brand { padding: 0 22px; }
    .lt-brand::after { right: -16.5px; width: 18px; }
}

.lt-name {
    font-size: 14px;
    font-weight: 700;
    letter-spacing: 0.03em;
    color: oklch(0.96 0.005 70);
    white-space: nowrap;
}

@media (min-width: 640px) {
    .lt-name { font-size: 18px; }
}

.lt-body {
    flex: 1;
    display: flex;
    align-items: center;
    background: oklch(0.08 0.008 40 / 0.72);
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    padding: 8px 12px 8px 22px;
    margin-left: -8px;
    gap: 8px;
    overflow: hidden;
}

@media (min-width: 640px) {
    .lt-body { padding: 10px 18px 10px 30px; margin-left: -12px; gap: 10px; }
}

.lt-metric {
    padding: 0 2px;
    flex-shrink: 0;
}

.lt-label {
    font-size: 7px;
    font-weight: 600;
    letter-spacing: 0.06em;
    color: oklch(0.62 0.008 70);
    line-height: 1;
    white-space: nowrap;
}

@media (min-width: 640px) {
    .lt-label { font-size: 8px; }
}

.lt-val {
    font-size: 13px;
    font-weight: 600;
    color: oklch(0.96 0.005 70);
    line-height: 1.2;
    white-space: nowrap;
}

@media (min-width: 640px) {
    .lt-val { font-size: 16px; }
}

.lt-sep {
    width: 1px;
    height: 20px;
    background: oklch(0.32 0.01 40 / 0.18);
    flex-shrink: 0;
}

@media (min-width: 640px) {
    .lt-sep { height: 24px; }
}

.lt-status {
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 0.03em;
    padding: 4px 10px;
    border-radius: 4px;
    white-space: nowrap;
    flex-shrink: 0;
}

.status-sail {
    color: oklch(0.78 0.12 155);
    background: oklch(0.78 0.12 155 / 0.12);
}

.status-power {
    color: oklch(0.75 0.14 70);
    background: oklch(0.75 0.14 70 / 0.12);
}

.status-port {
    color: oklch(0.70 0.12 240);
    background: oklch(0.70 0.12 240 / 0.12);
}

.status-offline {
    color: oklch(0.65 0.06 55);
    background: oklch(0.65 0.06 55 / 0.12);
}

.lt-passage-wrap {
    display: flex;
    justify-content: center;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 40vw;
}

@media (min-width: 640px) {
    .lt-passage-wrap { max-width: none; flex: 1; }
}

.lt-passage {
    font-size: 14px;
    font-weight: 500;
    color: oklch(0.75 0.008 70);
    white-space: nowrap;
}

.lt-passage strong {
    color: oklch(0.96 0.005 70);
    font-weight: 600;
}

.lt-passage-sep {
    display: block;
}

.lt-port-label {
    font-size: 11px;
    font-weight: 500;
    color: oklch(0.62 0.008 70);
    margin-right: 4px;
}

.lt-clock {
    text-align: right;
    flex-shrink: 0;
    padding: 0 2px;
}

.lt-time {
    font-size: 13px;
    font-weight: 600;
    color: oklch(0.96 0.005 70);
    line-height: 1;
}

@media (min-width: 640px) {
    .lt-time { font-size: 16px; }
}

.lt-date {
    font-size: 9px;
    font-weight: 500;
    color: oklch(0.62 0.008 70);
    margin-top: 2px;
}
</style>
