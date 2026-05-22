<template>
    <AdminLayout>
        <Head title="Import Journey" />
        <h1 class="text-[22px] font-bold mb-6">Import from History</h1>

        <form @submit.prevent="form.post('/admin/journeys/import')" class="panel p-6">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="field-label">From</label>
                    <input v-model="form.from_port" type="text" required placeholder="e.g. Lymington" class="field-input" />
                    <p v-if="form.errors.from_port" class="field-error">{{ form.errors.from_port }}</p>
                </div>
                <div>
                    <label class="field-label">To</label>
                    <input v-model="form.to_port" type="text" required placeholder="e.g. Yarmouth" class="field-input" />
                    <p v-if="form.errors.to_port" class="field-error">{{ form.errors.to_port }}</p>
                </div>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 mt-4">
                <div>
                    <label class="field-label">Start Time</label>
                    <input v-model="form.started_at" type="datetime-local" required class="field-input tabular-nums" />
                    <p v-if="form.errors.started_at" class="field-error">{{ form.errors.started_at }}</p>
                </div>
                <div>
                    <label class="field-label">End Time</label>
                    <input v-model="form.ended_at" type="datetime-local" required class="field-input tabular-nums" />
                    <p v-if="form.errors.ended_at" class="field-error">{{ form.errors.ended_at }}</p>
                </div>
            </div>
            <div class="mt-4">
                <label class="field-label">GPX Route (optional)</label>
                <input type="file" accept=".gpx" @change="form.gpx_file = $event.target.files[0]" class="field-input text-[13px]" />
            </div>
            <div class="mt-4">
                <label class="field-label">Notes (optional)</label>
                <textarea v-model="form.notes" rows="3" class="field-input" placeholder="Any notes about this passage..."></textarea>
            </div>
            <p class="mt-3 text-[12px] text-text-dim">Track data will be imported from Prometheus. This is limited by Prometheus retention (typically 15-30 days).</p>
            <div class="flex items-center gap-3 mt-5">
                <button type="submit" :disabled="form.processing" class="btn btn--primary">Import Journey</button>
                <Link href="/admin/journeys" class="btn btn--ghost">Cancel</Link>
            </div>
        </form>
    </AdminLayout>
</template>

<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const form = useForm({
    from_port: '',
    to_port: '',
    started_at: '',
    ended_at: '',
    gpx_file: null,
    notes: '',
});
</script>

