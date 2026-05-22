<template>
    <AdminLayout>
        <Head title="Journeys" />

        <div class="flex items-baseline justify-between mb-6">
            <h1 class="text-[22px] font-bold">Journeys</h1>
            <div class="flex gap-2">
                <Link href="/admin/journeys/create" class="btn btn--primary">Start Journey</Link>
                <Link href="/admin/journeys/import" class="btn btn--ghost">Import from History</Link>
            </div>
        </div>

        <div v-if="journeys.length === 0" class="panel text-center py-12">
            <p class="text-text-secondary text-[14px]">No journeys yet.</p>
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
                        <td class="px-4 py-3 tabular-nums text-text-secondary">{{ fmtDate(j.started_at) }}</td>
                        <td class="px-4 py-3 tabular-nums text-text-secondary">{{ fmtDuration(j.duration) }}</td>
                        <td class="px-4 py-3 tabular-nums text-text-secondary">{{ j.distance }} nm</td>
                        <td class="px-4 py-3">
                            <span class="status-badge" :class="`status-badge--${j.status}`">{{ j.status }}</span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex gap-2 justify-end">
                                <Link v-if="j.status === 'active'" :href="`/admin/journeys/${j.id}/end`" method="post" as="button" class="text-[12px] text-red-500 font-medium hover:underline">End</Link>
                                <Link :href="`/admin/journeys/${j.id}/edit`" class="text-[12px] text-scarlet font-medium hover:underline">Edit</Link>
                                <a :href="`/journey/${j.slug}`" target="_blank" class="text-[12px] text-text-dim font-medium hover:underline">View</a>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineProps({ journeys: Array });

function fmtDuration(seconds) {
    if (seconds == null) return '—';
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    return h > 0 ? `${h}h ${m}m` : `${m}m`;
}

function fmtDate(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
}
</script>

<style scoped>
.status-badge { font-size: 10px; font-weight: 600; letter-spacing: 0.03em; padding: 3px 8px; border-radius: 4px; text-transform: capitalize; }
.status-badge--active { color: var(--color-green); background: var(--color-green-bg); }
.status-badge--completed { color: oklch(0.55 0.15 240); background: oklch(0.55 0.15 240 / 0.10); }
.status-badge--abandoned { color: var(--color-text-dim); background: oklch(0.60 0.005 40 / 0.08); }
</style>
