<template>
    <AdminLayout>
        <Head title="Edit Journey" />
        <h1 class="text-[22px] font-bold mb-6">Edit Journey</h1>

        <form @submit.prevent="form.put(`/admin/journeys/${journey.id}`)" class="panel p-6 mb-6">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="field-label">Title</label>
                    <input v-model="form.title" type="text" required class="field-input" />
                    <p v-if="form.errors.title" class="field-error">{{ form.errors.title }}</p>
                </div>
                <div>
                    <label class="field-label">Slug</label>
                    <input v-model="form.slug" type="text" required class="field-input" />
                    <p v-if="form.errors.slug" class="field-error">{{ form.errors.slug }}</p>
                </div>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 mt-4">
                <div>
                    <label class="field-label">From</label>
                    <input v-model="form.from_port" type="text" required class="field-input" />
                </div>
                <div>
                    <label class="field-label">To</label>
                    <input v-model="form.to_port" type="text" required class="field-input" />
                </div>
            </div>
            <div class="mt-4">
                <label class="field-label">Notes</label>
                <textarea v-model="form.notes" rows="3" class="field-input"></textarea>
            </div>
            <div class="mt-4 flex items-center gap-3">
                <label class="flex items-center gap-2 text-[13px] cursor-pointer">
                    <input v-model="form.is_public" type="checkbox" class="w-4 h-4 rounded border-border accent-scarlet" />
                    <span>Publicly visible</span>
                </label>
            </div>
            <div class="flex items-center gap-3 mt-5">
                <button type="submit" :disabled="form.processing" class="btn btn--primary">Save Changes</button>
                <Link href="/admin/journeys" class="btn btn--ghost">Cancel</Link>
                <Transition name="saved-fade"><span v-if="form.wasSuccessful" class="text-[13px] text-green">Saved.</span></Transition>
            </div>
        </form>

        <!-- GPX Upload -->
        <form @submit.prevent="gpxForm.post(`/admin/journeys/${journey.id}/gpx`)" class="panel p-6 mb-6">
            <h2 class="text-[15px] font-semibold mb-4">GPX Route</h2>
            <p v-if="journey.gpx_route_path" class="text-[13px] text-green mb-3">Route file uploaded.</p>
            <div>
                <label class="field-label">{{ journey.gpx_route_path ? 'Replace GPX file' : 'Upload GPX file' }}</label>
                <input type="file" accept=".gpx" @change="gpxForm.gpx_file = $event.target.files[0]" class="field-input text-[13px]" />
            </div>
            <div class="flex items-center gap-3 mt-4">
                <button type="submit" :disabled="gpxForm.processing || !gpxForm.gpx_file" class="btn btn--primary">Upload</button>
                <Transition name="saved-fade"><span v-if="gpxForm.wasSuccessful" class="text-[13px] text-green">Uploaded.</span></Transition>
            </div>
        </form>

        <!-- Reimport Track Data -->
        <div v-if="journey.started_at && journey.ended_at" class="panel p-6 mb-6">
            <h2 class="text-[15px] font-semibold mb-2">Track Data</h2>
            <p class="text-[13px] text-text-secondary mb-3">{{ journey.track_point_count }} track points recorded.</p>
            <p v-if="reimportForm.errors.reimport" class="text-[12px] text-red-600 mb-3">{{ reimportForm.errors.reimport }}</p>
            <div class="flex items-center gap-3">
                <button @click="reimport" :disabled="reimportForm.processing" class="btn btn--ghost">Re-import from Prometheus</button>
                <Transition name="saved-fade"><span v-if="reimportForm.wasSuccessful" class="text-[13px] text-green">Import started.</span></Transition>
            </div>
        </div>

        <!-- Danger Zone -->
        <div class="panel p-6 border-red-200">
            <h2 class="text-[15px] font-semibold mb-3 text-red-600">Danger Zone</h2>
            <div class="flex items-center justify-between">
                <p class="text-[13px] text-text-secondary">Permanently delete this journey and all its track data.</p>
                <Link :href="`/admin/journeys/${journey.id}`" method="delete" as="button" class="btn btn--danger" @click="(e) => { if (!confirm('Delete this journey?')) e.preventDefault(); }">Delete Journey</Link>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({ journey: Object });

const form = useForm({
    title: props.journey.title,
    slug: props.journey.slug,
    from_port: props.journey.from_port,
    to_port: props.journey.to_port,
    is_public: props.journey.is_public,
    notes: props.journey.notes ?? '',
});

const gpxForm = useForm({
    gpx_file: null,
});

const reimportForm = useForm({});
function reimport() {
    if (!confirm('This will delete existing track points and re-import from Prometheus. Continue?')) return;
    reimportForm.post(`/admin/journeys/${props.journey.id}/reimport`);
}
</script>

