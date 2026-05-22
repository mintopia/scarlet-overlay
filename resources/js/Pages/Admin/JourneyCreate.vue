<template>
    <AdminLayout>
        <Head title="Start Journey" />
        <h1 class="text-[22px] font-bold mb-6">Start Journey</h1>

        <div v-if="hasActive" class="panel mb-6 border-amber-200 bg-amber-50">
            <p class="text-[13px] text-amber-800">A journey is already active. End it before starting a new one.</p>
        </div>

        <form @submit.prevent="form.post('/admin/journeys')" class="panel p-6">
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
            <div class="mt-4">
                <label class="field-label">GPX Route (optional)</label>
                <input type="file" accept=".gpx" @change="form.gpx_file = $event.target.files[0]" class="field-input text-[13px]" />
                <p v-if="form.errors.gpx_file" class="field-error">{{ form.errors.gpx_file }}</p>
            </div>
            <div class="mt-4">
                <label class="field-label">Notes (optional)</label>
                <textarea v-model="form.notes" rows="3" class="field-input" placeholder="Any notes about this passage..."></textarea>
            </div>
            <div class="flex items-center gap-3 mt-5">
                <button type="submit" :disabled="form.processing || hasActive" class="btn btn--primary">Start Journey</button>
                <Link href="/admin/journeys" class="btn btn--ghost">Cancel</Link>
            </div>
        </form>
    </AdminLayout>
</template>

<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineProps({ hasActive: Boolean });

const form = useForm({
    from_port: '',
    to_port: '',
    gpx_file: null,
    notes: '',
});
</script>

