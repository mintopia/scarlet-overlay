<script setup>
import { ref, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    boatName: { type: String, default: 'Scarlet' },
    registry: { type: String, default: '' },
});
const emit = defineEmits(['done']);

const leaving = ref(false);
const reduced = typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
let timers = [];
let finished = false;

function finish() {
    if (finished) return;
    finished = true;
    timers.forEach(clearTimeout);
    emit('done');
}

function skip() {
    // Animate a quick warp-out, then finish.
    if (finished) return;
    leaving.value = true;
    setTimeout(finish, reduced ? 0 : 360);
}

function onKey(e) {
    if (e.key === 'Escape' || e.key === 'Enter' || e.key === ' ') skip();
}

onMounted(() => {
    window.addEventListener('keydown', onKey);
    if (reduced) {
        timers.push(setTimeout(finish, 900));
        return;
    }
    timers.push(setTimeout(() => { leaving.value = true; }, 3200));
    timers.push(setTimeout(finish, 4200));
});
onUnmounted(() => window.removeEventListener('keydown', onKey));
</script>

<template>
    <div class="splash" :class="{ out: leaving, reduced }" @click="skip">
        <div class="seal">
            <img class="ufp" src="/images/ufp.png" alt="United Federation of Planets seal" />
            <div class="halo" aria-hidden="true"></div>
        </div>
        <div class="ufp-text">United Federation of Planets</div>
        <div class="ufp-sub">S.V. {{ (boatName || 'Scarlet').toUpperCase() }}<template v-if="registry"> · NCC-{{ registry }}</template></div>
        <button class="skip" @click.stop="skip" aria-label="Skip intro">SKIP ▸</button>
    </div>
</template>

<style scoped>
.splash {
    position: fixed; inset: 0; z-index: 100; cursor: pointer;
    background: radial-gradient(circle at 50% 42%, #060b1c 0%, #01030a 60%, #000 100%);
    display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 30px;
}
.seal { position: relative; width: min(340px, 34vh); aspect-ratio: 1; }
.ufp {
    width: 100%; height: 100%; object-fit: contain; position: relative; z-index: 2;
    opacity: 0; transform: scale(0.84);
    animation: sealIn 1.5s cubic-bezier(0.22, 1, 0.36, 1) 0.15s forwards;
    filter: drop-shadow(0 0 22px rgba(120, 150, 255, 0.35));
}
.halo {
    position: absolute; inset: -8%; border-radius: 50%; z-index: 1; opacity: 0;
    background: radial-gradient(circle, rgba(120, 150, 255, 0.35) 0%, transparent 62%);
    animation: haloIn 2s ease 0.5s forwards;
}
.ufp-text {
    font-size: clamp(22px, 3.4vh, 36px); font-weight: 600; color: #cdd6ff; text-transform: uppercase;
    letter-spacing: 0.5em; opacity: 0; text-align: center;
    animation: textIn 1.1s cubic-bezier(0.22, 1, 0.36, 1) 1.05s forwards;
}
.ufp-sub {
    font-size: 14px; color: #8893c9; letter-spacing: 0.45em; margin-top: -16px; opacity: 0; text-transform: uppercase;
    animation: textIn 1s ease 1.55s forwards;
}
.skip {
    position: fixed; bottom: 28px; right: 32px; background: transparent; border: 1px solid #3a4470;
    color: #8893c9; font-family: inherit; font-weight: 600; letter-spacing: 0.15em; font-size: 13px;
    padding: 7px 16px; border-radius: 16px; cursor: pointer; opacity: 0; text-transform: uppercase;
    animation: textIn 0.8s ease 2s forwards;
}
.skip:hover { border-color: #8893c9; color: #cdd6ff; }

@keyframes sealIn { to { opacity: 1; transform: scale(1); } }
@keyframes haloIn { 0% { opacity: 0; } 60% { opacity: 1; } 100% { opacity: 0.6; } }
@keyframes textIn { from { opacity: 0; letter-spacing: 0.55em; } to { opacity: 1; letter-spacing: 0.18em; } }

.splash.out { animation: warpOut 0.9s cubic-bezier(0.6, 0, 0.4, 1) forwards; }
@keyframes warpOut { to { opacity: 0; transform: scale(1.45); filter: brightness(2.4); } }

.splash.reduced .ufp, .splash.reduced .ufp-text, .splash.reduced .ufp-sub, .splash.reduced .skip, .splash.reduced .halo {
    animation: none; opacity: 1; transform: none; letter-spacing: 0.18em;
}
.splash.reduced.out { animation: none; opacity: 0; transition: opacity 0.3s; }
@media (prefers-reduced-motion: reduce) {
    .splash, .ufp, .ufp-text, .ufp-sub, .skip, .halo { animation: none !important; }
    .ufp, .ufp-text, .ufp-sub, .skip { opacity: 1; transform: none; letter-spacing: 0.18em; }
}
</style>
