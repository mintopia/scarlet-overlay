<script setup>
import { computed } from 'vue';
import { useSpringValue } from '../../composables/useSpringValue.js';
import { pluck, MSD_CALLOUTS } from '../../lcars/contract.js';

const props = defineProps({
    metrics: { type: Object, required: true },
    boatName: { type: String, default: 'SCARLET' },
    registry: { type: String, default: '' },
});

const heel = useSpringValue(() => pluck(props.metrics, 'boat.heel') ?? 0, { tension: 60, friction: 14 });
const depth = computed(() => pluck(props.metrics, 'boat.depth'));

const callouts = computed(() => MSD_CALLOUTS.map(c => {
    const v = pluck(props.metrics, c.src);
    return { ...c, text: v == null ? '--' : `${(+v).toFixed(c.dp)}${c.unit}` };
}));

const tilt = computed(() => `rotate(${(heel.value ?? 0).toFixed(2)} 200 130)`);
</script>

<template>
    <div class="lcars-msd">
        <svg viewBox="0 0 400 240" preserveAspectRatio="xMidYMid meet">
            <!-- waterline -->
            <line x1="20" y1="150" x2="380" y2="150" stroke="var(--blue)" stroke-width="1.5" stroke-dasharray="4 4" opacity="0.5" />

            <g :transform="tilt">
                <!-- hull -->
                <path d="M120,150 Q200,176 290,150 L278,130 Q200,142 132,130 Z" fill="var(--panel-2)" stroke="var(--orange)" stroke-width="2.5" />
                <!-- keel -->
                <path d="M196,150 L198,205 L214,205 L210,150 Z" fill="var(--mauve)" opacity="0.7" />
                <!-- mast -->
                <line x1="205" y1="132" x2="205" y2="28" stroke="var(--peach)" stroke-width="2.5" />
                <!-- mainsail -->
                <path d="M205,34 L205,128 L262,128 Z" fill="var(--orange)" opacity="0.18" stroke="var(--orange)" stroke-width="1.5" />
                <!-- jib -->
                <path d="M205,40 L205,126 L150,126 Z" fill="var(--blue)" opacity="0.16" stroke="var(--blue)" stroke-width="1.5" />
            </g>

            <!-- depth under keel -->
            <g v-if="depth != null">
                <line x1="206" y1="208" x2="206" y2="232" stroke="var(--ice)" stroke-width="1" stroke-dasharray="2 3" opacity="0.6" />
                <text x="214" y="228" fill="var(--ice)" font-size="12">{{ (+depth).toFixed(1) }}m</text>
            </g>
        </svg>

        <!-- callouts -->
        <div class="callouts">
            <div v-for="c in callouts" :key="c.key" class="co">
                <span class="k">{{ c.label }}</span>
                <span class="v lcars-num">{{ c.text }}</span>
            </div>
        </div>
    </div>
</template>

<style scoped>
.lcars-msd { display: grid; grid-template-rows: auto 1fr; height: 100%; gap: 14px; }
.lcars-msd svg { width: 100%; max-height: 320px; }
.callouts { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; align-content: start; }
.callouts .co { background: var(--panel); border-radius: 0 10px 10px 0; padding: 8px 12px; display: flex; flex-direction: column; }
.callouts .co .k { color: var(--mauve); font-size: 13px; }
.callouts .co .v { color: var(--peach); font-size: 24px; font-weight: 700; line-height: 1; }
</style>
