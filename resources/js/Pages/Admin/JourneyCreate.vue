<template>
    <AdminLayout>
        <Head title="Plan Journey" />
        <h1 class="text-[22px] font-bold mb-6">Plan Journey</h1>

        <div v-if="hasActiveOrPlanned" class="panel mb-6 border-border bg-amber-bg p-4">
            <p class="text-[13px] text-amber">A journey is already planned or active. End or delete it first.</p>
        </div>

        <form @submit.prevent="form.post('/admin/journeys')" class="panel p-6">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="journey-from" class="field-label">From</label>
                    <input id="journey-from" v-model="form.from_port" type="text" required placeholder="e.g. Lymington" class="field-input" />
                    <p v-if="form.errors.from_port" class="field-error">{{ form.errors.from_port }}</p>
                </div>
                <div>
                    <label for="journey-to" class="field-label">To</label>
                    <input id="journey-to" v-model="form.to_port" type="text" required placeholder="e.g. Yarmouth" class="field-input" />
                    <p v-if="form.errors.to_port" class="field-error">{{ form.errors.to_port }}</p>
                </div>
            </div>
            <div class="mt-4">
                <label for="journey-gpx" class="field-label">GPX Route (optional)</label>
                <input id="journey-gpx" type="file" accept=".gpx" @change="form.gpx_file = $event.target.files[0]" class="field-input text-[13px]" />
                <p v-if="form.errors.gpx_file" class="field-error">{{ form.errors.gpx_file }}</p>
            </div>
            <div class="mt-4">
                <label for="journey-notes" class="field-label">Notes (optional)</label>
                <textarea id="journey-notes" v-model="form.notes" rows="3" class="field-input" placeholder="Any notes about this passage..."></textarea>
            </div>
            <div class="flex items-center gap-3 mt-5">
                <button type="submit" :disabled="form.processing || hasActiveOrPlanned" class="btn btn--primary">Plan Journey</button>
                <Link href="/admin/journeys" class="btn btn--ghost">Cancel</Link>
            </div>
        </form>
    </AdminLayout>
</template>

<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineProps({ hasActiveOrPlanned: Boolean });

const form = useForm({
    from_port: '',
    to_port: '',
    gpx_file: null,
    notes: '',
});
</script>

