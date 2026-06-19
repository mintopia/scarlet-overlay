<template>
    <AdminLayout>
        <Head title="Stream" />
        <div class="stream-stage">
            <video
                ref="videoRef"
                class="stream-video"
                :class="{ 'stream-video--live': videoActive }"
                muted
                playsinline
                autoplay
            ></video>
            <div v-if="!videoActive" class="stream-overlay">
                <div class="stream-pulse" aria-hidden="true"></div>
                <p class="stream-overlay__text">
                    {{ videoChecked ? 'Waiting for camera feed…' : 'Connecting to camera…' }}
                </p>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useVideoFeed } from '@/composables/useVideoFeed.js';

const videoRef = ref(null);
const { videoActive, videoChecked, connect } = useVideoFeed(videoRef);

onMounted(() => {
    connect();
});
</script>

<style scoped>
.stream-stage {
    position: relative;
    width: 100%;
    aspect-ratio: 16 / 9;
    background: #000;
    border-radius: 14px;
    overflow: hidden;
    border: 1px solid var(--color-border);
}

.stream-video {
    width: 100%;
    height: 100%;
    object-fit: contain;
    display: block;
    opacity: 0;
    transition: opacity 0.3s ease-out;
}

.stream-video--live { opacity: 1; }

.stream-overlay {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 14px;
    color: var(--color-text-secondary);
}

.stream-overlay__text {
    font-size: 13px;
    font-weight: 600;
    letter-spacing: 0.04em;
}

.stream-pulse {
    width: 14px;
    height: 14px;
    border-radius: 50%;
    background: var(--color-scarlet);
    animation: stream-pulse 1.4s ease-in-out infinite;
}

@keyframes stream-pulse {
    0%, 100% { opacity: 0.3; transform: scale(0.85); }
    50% { opacity: 1; transform: scale(1.1); }
}

@media (prefers-reduced-motion: reduce) {
    .stream-pulse { animation: none; }
    .stream-video { transition: none; }
}
</style>
