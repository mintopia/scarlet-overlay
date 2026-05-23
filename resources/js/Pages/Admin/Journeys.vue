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

        <div v-if="journeys.length === 0" class="panel text-center py-12 px-6">
            <p class="text-text-primary text-[15px] font-medium mb-1">No journeys recorded</p>
            <p class="text-text-secondary text-[13px]">Start your first voyage or import from track history.</p>
        </div>

        <div v-else class="panel overflow-x-auto">
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
                                <Link :href="`/admin/journeys/${j.id}/edit`" class="text-[12px] text-scarlet font-medium hover:underline">Edit</Link>
                                <a v-if="j.status !== 'planned'" :href="`/journey/${j.slug}`" target="_blank" class="text-[12px] text-text-dim font-medium hover:underline">View</a>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <!-- Start Journey Confirm Modal -->
        <Transition name="modal">
        <div v-if="startingJourney" class="modal-overlay" @click.self="startingJourney = null">
            <div class="modal-card" role="dialog" aria-modal="true" aria-label="Confirm start journey">
                <h3 class="text-[16px] font-semibold mb-2">Start recording?</h3>
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
        <div v-if="endingJourney" class="modal-overlay" @click.self="endingJourney = null">
            <div class="modal-card" role="dialog" aria-modal="true" aria-label="Confirm end journey">
                <h3 class="text-[16px] font-semibold mb-2">End this journey?</h3>
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
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { fmtDuration } from '@/composables/useFormatters.js';

defineProps({ journeys: Array });

const startingJourney = ref(null);
const endingJourney = ref(null);

function confirmStart(journey) {
    startingJourney.value = journey;
}

function startJourney() {
    router.post(`/admin/journeys/${startingJourney.value.id}/start`, {}, {
        onFinish: () => { startingJourney.value = null; },
    });
}

function confirmEnd(journey) {
    endingJourney.value = journey;
}

function endJourney() {
    router.post(`/admin/journeys/${endingJourney.value.id}/end`, {}, {
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
.status-badge--planned { color: oklch(0.55 0.12 80); background: oklch(0.55 0.12 80 / 0.10); }
.status-badge--active { color: var(--color-green); background: var(--color-green-bg); }
.status-badge--completed { color: oklch(0.55 0.15 240); background: oklch(0.55 0.15 240 / 0.10); }
.status-badge--abandoned { color: var(--color-text-dim); background: oklch(0.60 0.005 40 / 0.08); }

.modal-overlay {
    position: fixed; inset: 0;
    background: oklch(0.05 0.008 40 / 0.45);
    display: flex; align-items: center; justify-content: center;
    z-index: 50; padding: 24px;
}
.modal-card {
    background: var(--color-surface);
    border-radius: 12px;
    padding: 28px 28px 24px;
    width: 100%; max-width: 400px;
    box-shadow: 0 8px 40px oklch(0.05 0.008 40 / 0.14);
}
</style>
