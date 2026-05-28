<template>
    <AdminLayout>
        <Head title="Settings" />
        <h1 class="text-[22px] font-bold mb-6">Settings</h1>

        <!-- Boat Identity -->
        <form @submit.prevent="identityForm.put(route('admin.settings.identity'))" class="panel p-6 mb-6">
            <h2 class="text-[15px] font-semibold mb-4">Boat Identity</h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="field-label">Boat Name</label>
                    <input v-model="identityForm.boat_name" type="text" required class="field-input" />
                    <p v-if="identityForm.errors.boat_name" class="field-error">{{ identityForm.errors.boat_name }}</p>
                </div>
                <div>
                    <label class="field-label">MMSI (9 digits)</label>
                    <input v-model="identityForm.mmsi" type="text" maxlength="9" placeholder="e.g. 235117890" class="field-input" />
                    <p v-if="identityForm.errors.mmsi" class="field-error">{{ identityForm.errors.mmsi }}</p>
                </div>
            </div>
            <div class="flex items-center gap-3 mt-5">
                <button type="submit" :disabled="identityForm.processing" class="btn btn--primary">Save</button>
                <Transition name="saved-fade"><SavedCheck v-if="identityForm.wasSuccessful" /></Transition>
            </div>
        </form>

        <!-- Stream -->
        <form @submit.prevent="streamForm.put(route('admin.settings.stream'))" class="panel p-6 mb-6">
            <h2 class="text-[15px] font-semibold mb-4">Stream</h2>
            <div class="grid grid-cols-1 gap-4">
                <div>
                    <label class="field-label">SRT URL</label>
                    <input v-model="streamForm.srt_url" type="text" placeholder="e.g. srt://host:port?streamid=..." class="field-input" />
                    <p class="mt-1 text-[12px] text-text-secondary">The SRT ingest URL for the video feed on the broadcast overlay.</p>
                    <p v-if="streamForm.errors.srt_url" class="field-error">{{ streamForm.errors.srt_url }}</p>
                </div>
                <div>
                    <label class="field-label">SRT Stats URL</label>
                    <input v-model="streamForm.srt_stats_url" type="text" placeholder="e.g. https://stats.srt.belabox.net/..." class="field-input" />
                    <p class="mt-1 text-[12px] text-text-secondary">BELABOX SRT stats endpoint for the Stream Monitor page.</p>
                    <p v-if="streamForm.errors.srt_stats_url" class="field-error">{{ streamForm.errors.srt_stats_url }}</p>
                </div>
            </div>
            <div class="flex items-center gap-3 mt-5">
                <button type="submit" :disabled="streamForm.processing" class="btn btn--primary">Save</button>
                <Transition name="saved-fade"><SavedCheck v-if="streamForm.wasSuccessful" /></Transition>
            </div>
        </form>

        <!-- Camera -->
        <form @submit.prevent="cameraForm.put(route('admin.settings.camera'))" class="panel p-6 mb-6">
            <h2 class="text-[15px] font-semibold mb-4">Camera</h2>
            <div>
                <label class="field-label">Camera Feed URL</label>
                <input v-model="cameraForm.camera_url" type="url" placeholder="e.g. https://cam.example.com/embed" class="field-input" />
                <p class="mt-1 text-[12px] text-text-secondary">URL of the camera feed to embed in the camera overlay page.</p>
                <p v-if="cameraForm.errors.camera_url" class="field-error">{{ cameraForm.errors.camera_url }}</p>
            </div>
            <div class="flex items-center gap-3 mt-5">
                <button type="submit" :disabled="cameraForm.processing" class="btn btn--primary">Save</button>
                <Transition name="saved-fade"><SavedCheck v-if="cameraForm.wasSuccessful" /></Transition>
            </div>
        </form>

        <!-- Force Reload -->
        <div class="panel p-6 mb-6">
            <h2 class="text-[15px] font-semibold mb-1">Force Reload Clients</h2>
            <p class="text-[12px] text-text-secondary mb-4">Sends a reload signal to all connected overlay and public dashboard browser windows.</p>
            <button type="button" @click="showReloadModal = true" class="btn btn--danger">Force Reload Clients</button>
        </div>

        <!-- Force Reload Confirmation Modal -->
        <div v-if="showReloadModal" style="position: fixed; inset: 0; z-index: 100; display: flex; align-items: center; justify-content: center;">
            <div style="position: absolute; inset: 0; background: rgba(0,0,0,0.5);" @click="showReloadModal = false"></div>
            <div style="position: relative; background: white; border-radius: 12px; padding: 28px 32px; max-width: 420px; width: 100%; margin: 0 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.25);">
                <h3 style="font-size: 16px; font-weight: 700; margin: 0 0 10px;">Force Reload Clients</h3>
                <p class="text-sm text-text-secondary leading-normal mb-6">This will reload all overlay and dashboard browser windows. Continue?</p>
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" @click="showReloadModal = false" class="btn btn--secondary">Cancel</button>
                    <button type="button" @click="confirmForceReload" :disabled="reloading" class="btn btn--danger">
                        {{ reloading ? 'Sending…' : 'Reload All Clients' }}
                    </button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import SavedCheck from '@/components/SavedCheck.vue';

const props = defineProps({ settings: Object });

const identityForm = useForm({
    boat_name: props.settings?.boat_name ?? '',
    mmsi: props.settings?.mmsi ?? '',
});

const streamForm = useForm({
    srt_url: props.settings?.srt_url ?? '',
    srt_stats_url: props.settings?.srt_stats_url ?? '',
});

const cameraForm = useForm({
    camera_url: props.settings?.camera_url ?? '',
});

const showReloadModal = ref(false);
const reloading = ref(false);

function confirmForceReload() {
    reloading.value = true;
    router.post(route('admin.settings.force-reload'), {}, {
        onFinish: () => {
            reloading.value = false;
            showReloadModal.value = false;
        },
    });
}
</script>
