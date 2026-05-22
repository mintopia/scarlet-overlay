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

<style scoped>
.panel { background: var(--color-surface); border: 1px solid var(--color-border); border-radius: 10px; }
.field-label { display: block; font-size: 13px; font-weight: 500; color: var(--color-text-secondary); margin-bottom: 6px; }
.field-input { width: 100%; height: 42px; padding: 0 14px; font-size: 14px; background: var(--color-bg); border: 1px solid var(--color-border); border-radius: 7px; font-family: inherit; outline: none; }
.field-input:focus { border-color: oklch(0.54 0.22 27); box-shadow: 0 0 0 2px oklch(0.54 0.22 27 / 0.1); }
textarea.field-input { height: auto; padding: 10px 14px; resize: vertical; }
.field-error { margin-top: 4px; font-size: 12px; color: var(--color-error); }
.btn { display: inline-flex; align-items: center; height: 36px; padding: 0 14px; border-radius: 7px; font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.12s ease-out; }
.btn--primary { background: oklch(0.54 0.22 27); color: white; }
.btn--primary:hover { background: oklch(0.48 0.22 27); }
.btn--primary:disabled { opacity: 0.5; cursor: not-allowed; }
.btn--ghost { background: var(--color-bg); border: 1px solid var(--color-border); color: var(--color-text-secondary); }
.btn--ghost:hover { border-color: var(--color-text-dim); color: var(--color-text-primary); }
</style>
