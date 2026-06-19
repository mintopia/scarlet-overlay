<template>
    <AdminLayout>
        <Head title="Journeys" />

        <div class="flex items-baseline justify-between mb-6">
            <h1 class="text-[22px] font-bold">Journeys</h1>
            <div class="flex gap-2">
                <Link href="/admin/journeys/create" class="btn btn--primary">Plan Journey</Link>
                <Link href="/admin/journeys/import" class="btn btn--ghost">Import from History</Link>
            </div>
        </div>

        <div v-if="journeys.length === 0" class="panel text-center py-14 px-6">
            <svg class="mx-auto mb-4" viewBox="0 0 80 48" width="64" height="38" fill="none" aria-hidden="true">
                <path d="M8 40 Q24 20 40 24 Q56 28 72 8" stroke="var(--color-border)" stroke-width="1.5" stroke-dasharray="5 4" stroke-linecap="round" />
                <circle cx="8" cy="40" r="3.5" stroke="var(--color-text-dim)" stroke-width="1.5" />
                <circle cx="72" cy="8" r="3.5" stroke="var(--color-teal)" stroke-width="1.5" />
                <circle cx="72" cy="8" r="1.5" fill="var(--color-teal)" />
            </svg>
            <p class="text-text-primary text-[15px] font-medium mb-1">No journeys yet</p>
            <p class="text-text-secondary text-[13px] max-w-xs mx-auto">Plan your first passage or import tracks from your sailing history.</p>
        </div>

        <div v-else class="panel scroll-affordance overflow-x-auto">
            <table class="w-full text-[13px] min-w-[540px]">
                <thead>
                    <tr class="border-b border-border-light text-left text-text-dim text-[11px] uppercase tracking-wide">
                        <th class="px-4 py-3 font-semibold">Journey</th>
                        <th class="px-4 py-3 font-semibold">Date</th>
                        <th class="px-4 py-3 font-semibold">Duration</th>
                        <th class="px-4 py-3 font-semibold">Distance</th>
                        <th class="px-4 py-3 font-semibold">Status</th>
                        <th class="px-4 py-3 font-semibold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="j in journeys" :key="j.id" class="border-b border-border-light last:border-0 hover:bg-bg/50">
                        <td class="px-4 py-3 font-medium">{{ j.title }}</td>
                        <td class="px-4 py-3 tabular-nums text-text-secondary">{{ j.started_at ? fmtDate(j.started_at) : '—' }}</td>
                        <td class="px-4 py-3 tabular-nums text-text-secondary">{{ j.duration != null ? fmtDuration(j.duration) : '—' }}</td>
                        <td class="px-4 py-3 tabular-nums text-text-secondary">{{ j.distance != null ? j.distance + ' nm' : '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="status-badge" :class="`status-badge--${j.status}`">{{ j.status }}</span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex gap-2 justify-end">
                                <button v-if="j.status === 'planned'" @click="confirmStart(j)" class="text-[12px] text-green font-medium hover:underline">Start</button>
                                <button v-if="j.status === 'active'" @click="confirmEnd(j)" class="text-[12px] text-error font-medium hover:underline">End</button>
                                <Link v-if="j.status !== 'planned'" :href="`/admin/journeys/${j.id}`" class="text-[12px] text-scarlet font-medium hover:underline">View</Link>
                                <Link :href="`/admin/journeys/${j.id}/edit`" class="text-[12px] text-text-dim font-medium hover:underline">Edit</Link>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <!-- Start Journey Confirm Modal -->
        <Transition name="modal">
        <div v-if="startingJourney" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="start-journey-title" @click.self="startingJourney = null" @keydown.escape="startingJourney = null">
            <div ref="startModalRef" tabindex="-1" class="modal-card" @keydown.tab="trapFocus($event, startModalRef)">
                <h3 id="start-journey-title" class="text-[16px] font-semibold mb-2">Start recording?</h3>
                <p class="text-[13px] text-text-secondary mb-5">This will begin track recording for <strong>{{ startingJourney.title }}</strong>. GPS position and boat data will be logged from now.</p>
                <div class="flex items-center justify-end gap-3">
                    <button @click="startingJourney = null" class="btn btn--ghost">Cancel</button>
                    <button @click="startJourney" class="btn btn--primary">Start Recording</button>
                </div>
            </div>
        </div>
        </Transition>

        <!-- End Journey Confirm Modal -->
        <Transition name="modal">
        <div v-if="endingJourney" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="end-journey-title" @click.self="endingJourney = null" @keydown.escape="endingJourney = null">
            <div ref="endModalRef" tabindex="-1" class="modal-card" @keydown.tab="trapFocus($event, endModalRef)">
                <h3 id="end-journey-title" class="text-[16px] font-semibold mb-2">End this journey?</h3>
                <p class="text-[13px] text-text-secondary mb-5">This will mark <strong>{{ endingJourney.title }}</strong> as completed. You can still edit it afterwards.</p>
                <div class="flex items-center justify-end gap-3">
                    <button @click="endingJourney = null" class="btn btn--ghost">Cancel</button>
                    <button @click="endJourney" class="btn btn--danger">End Journey</button>
                </div>
            </div>
        </div>
        </Transition>
    </AdminLayout>
</template>

<script setup>
import { ref, watch, nextTick } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { fmtDuration } from '@/composables/useFormatters.js';
import { useToast } from '@/composables/useToast.js';

const toast = useToast();

defineProps({ journeys: Array });

const startingJourney = ref(null);
const endingJourney = ref(null);
const startModalRef = ref(null);
const endModalRef = ref(null);

watch(startingJourney, (val) => {
    if (val) {
        nextTick(() => startModalRef.value?.focus());
    }
});
watch(endingJourney, (val) => {
    if (val) {
        nextTick(() => endModalRef.value?.focus());
    }
});

function trapFocus(event, containerRef) {
    const modal = containerRef;
    if (!modal) {
        return;
    }
    const focusable = modal.querySelectorAll('input, button, textarea, select, [tabindex]:not([tabindex="-1"])');
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last?.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first?.focus();
    }
}

function confirmStart(journey) {
    startingJourney.value = journey;
}

function startJourney() {
    const title = startingJourney.value.title;
    router.post(`/admin/journeys/${startingJourney.value.id}/start`, {}, {
        onSuccess: () => { toast.success(`Recording started for ${title}`); },
        onFinish: () => { startingJourney.value = null; },
    });
}

function confirmEnd(journey) {
    endingJourney.value = journey;
}

function endJourney() {
    const title = endingJourney.value.title;
    router.post(`/admin/journeys/${endingJourney.value.id}/end`, {}, {
        onSuccess: () => { toast.success(`${title} completed`); },
        onFinish: () => { endingJourney.value = null; },
    });
}

function fmtDate(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
}
</script>

<style scoped>
.status-badge { font-size: 10px; font-weight: 600; letter-spacing: 0.03em; padding: 3px 8px; border-radius: 4px; text-transform: capitalize; }
.status-badge--planned { color: var(--color-amber); background: var(--color-amber-bg); }
.status-badge--active { color: var(--color-green); background: var(--color-green-bg); }
.status-badge--completed { color: var(--color-blue); background: var(--color-blue-bg); }
.status-badge--abandoned { color: var(--color-text-dim); background: var(--color-bg); }

.modal-overlay {
    position: fixed; inset: 0;
    background: rgba(0, 0, 0, 0.45);
    display: flex; align-items: center; justify-content: center;
    z-index: 100; padding: 24px;
}
.modal-card {
    background: var(--color-surface);
    border-radius: 12px;
    padding: 28px 28px 24px;
    width: 100%; max-width: 400px;
    box-shadow: 0 8px 40px rgba(0, 0, 0, 0.14);
}

/* Mobile-only right-edge fade hinting that the table scrolls horizontally. */
@media (max-width: 767px) {
    .scroll-affordance {
        -webkit-mask-image: linear-gradient(to right, #000 calc(100% - 28px), transparent 100%);
        mask-image: linear-gradient(to right, #000 calc(100% - 28px), transparent 100%);
    }
}
</style>
