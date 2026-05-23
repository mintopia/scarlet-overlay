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
                <Transition name="saved-fade"><SavedCheck v-if="form.wasSuccessful" /></Transition>
            </div>
        </form>

        <!-- Start Recording (planned journeys) -->
        <div v-if="journey.status === 'planned'" class="panel p-6 mb-6">
            <h2 class="text-[15px] font-semibold mb-2">Start Recording</h2>
            <p class="text-[13px] text-text-secondary mb-4">This journey is planned but not yet recording. Start to begin logging GPS and boat data.</p>
            <button @click="showStartModal = true" class="btn btn--primary">Start Recording</button>
        </div>

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
                <Transition name="saved-fade"><SavedCheck v-if="gpxForm.wasSuccessful" label="Uploaded" /></Transition>
            </div>
        </form>

        <!-- Reimport Track Data -->
        <div v-if="journey.started_at && journey.ended_at" class="panel p-6 mb-6">
            <h2 class="text-[15px] font-semibold mb-2">Track Data</h2>
            <p class="text-[13px] text-text-secondary mb-3">{{ journey.track_point_count }} track points recorded.</p>
            <p v-if="reimportForm.errors.reimport" class="text-[12px] text-red-600 mb-3">{{ reimportForm.errors.reimport }}</p>
            <div class="flex items-center gap-3">
                <button @click="showReimportModal = true" :disabled="reimportForm.processing" class="btn btn--ghost">Re-import from Prometheus</button>
                <Transition name="saved-fade"><SavedCheck v-if="reimportForm.wasSuccessful" label="Import started" /></Transition>
            </div>
        </div>

        <!-- Danger Zone -->
        <div class="panel p-6 border-error bg-[oklch(0.58_0.20_27_/_0.08)]">
            <h2 class="text-[15px] font-semibold mb-3 text-red-600">Danger Zone</h2>
            <div class="flex items-center justify-between">
                <p class="text-[13px] text-text-secondary">Permanently delete this journey and all its track data.</p>
                <button @click="showDeleteModal = true" class="btn btn--danger">Delete Journey</button>
            </div>
        </div>

        <!-- Start Confirmation Modal -->
        <Transition name="modal">
        <div v-if="showStartModal" class="modal-overlay" @click.self="showStartModal = false">
            <div class="modal-card" role="dialog" aria-modal="true" aria-label="Confirm start recording">
                <h3 class="text-[16px] font-semibold mb-2">Start recording?</h3>
                <p class="text-[13px] text-text-secondary mb-5">This will begin track recording for <strong>{{ journey.title }}</strong>. GPS position and boat data will be logged from now.</p>
                <div class="flex items-center justify-end gap-3">
                    <button @click="showStartModal = false" class="btn btn--ghost">Cancel</button>
                    <button @click="startRecording" class="btn btn--primary">Start Recording</button>
                </div>
            </div>
        </div>
        </Transition>

        <!-- Reimport Confirmation Modal -->
        <Transition name="modal">
        <div v-if="showReimportModal" class="modal-overlay" @click.self="showReimportModal = false">
            <div class="modal-card" role="dialog" aria-modal="true" aria-label="Confirm re-import">
                <h3 class="text-[16px] font-semibold mb-2">Re-import track data?</h3>
                <p class="text-[13px] text-text-secondary mb-5">This will delete all existing track points and re-import from Prometheus. The import runs in the background.</p>
                <div class="flex items-center justify-end gap-3">
                    <button @click="showReimportModal = false" class="btn btn--ghost">Cancel</button>
                    <button @click="reimport" :disabled="reimportForm.processing" class="btn btn--primary">Re-import</button>
                </div>
            </div>
        </div>
        </Transition>

        <!-- Delete Confirmation Modal -->
        <Transition name="modal">
        <div v-if="showDeleteModal" class="modal-overlay" @click.self="showDeleteModal = false">
            <div class="modal-card" role="dialog" aria-modal="true" aria-label="Confirm delete journey">
                <h3 class="text-[16px] font-semibold mb-2">Delete this journey?</h3>
                <p class="text-[13px] text-text-secondary mb-5">This will permanently delete the journey and all its track points. This cannot be undone.</p>
                <div class="flex items-center justify-end gap-3">
                    <button @click="showDeleteModal = false" class="btn btn--ghost">Cancel</button>
                    <button @click="deleteJourney" class="btn btn--danger">Delete</button>
                </div>
            </div>
        </div>
        </Transition>
    </AdminLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import SavedCheck from '@/components/SavedCheck.vue';

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

const showStartModal = ref(false);
function startRecording() {
    router.post(`/admin/journeys/${props.journey.id}/start`);
}

const reimportForm = useForm({});
const showReimportModal = ref(false);
function reimport() {
    showReimportModal.value = false;
    reimportForm.post(`/admin/journeys/${props.journey.id}/reimport`);
}

const showDeleteModal = ref(false);
function deleteJourney() {
    router.delete(`/admin/journeys/${props.journey.id}`);
}
</script>

<style scoped>
.modal-overlay {
    position: fixed; inset: 0;
    background: oklch(0.05 0.008 40 / 0.45);
    display: flex; align-items: center; justify-content: center;
    z-index: 100; padding: 24px;
}
.modal-card {
    background: var(--color-surface);
    border-radius: 12px;
    padding: 28px 28px 24px;
    width: 100%; max-width: 400px;
    box-shadow: 0 8px 40px oklch(0.05 0.008 40 / 0.14);
}
</style>

