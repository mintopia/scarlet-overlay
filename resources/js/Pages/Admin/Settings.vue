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

        <!-- Port Settings -->
        <form @submit.prevent="portForm.put(route('admin.settings.port'))" class="panel p-6 mb-6">
            <h2 class="text-[15px] font-semibold mb-4">Port Settings</h2>
            <div>
                <label class="field-label">Port Name</label>
                <input v-model="portForm.port_name" type="text" placeholder="e.g. Lymington Marina" class="field-input sm:max-w-sm" />
                <p v-if="portForm.errors.port_name" class="field-error">{{ portForm.errors.port_name }}</p>
            </div>
            <div class="mt-4">
                <label class="field-label">Trip Offset (nm)</label>
                <input v-model.number="portForm.trip_offset" type="number" step="0.1" min="0" placeholder="0" class="field-input tabular-nums sm:max-w-[200px]" />
                <p class="mt-1 text-[12px] text-text-secondary">Subtracted from the trip log when displayed on the dashboard and stream.</p>
            </div>
            <div class="flex items-center gap-3 mt-5">
                <button type="submit" :disabled="portForm.processing" class="btn btn--primary">Save</button>
                <Transition name="saved-fade"><SavedCheck v-if="portForm.wasSuccessful" /></Transition>
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
                <p style="font-size: 14px; color: #6b7280; margin: 0 0 24px; line-height: 1.5;">This will reload all overlay and dashboard browser windows. Continue?</p>
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

const portForm = useForm({
    port_name: props.settings?.port_name ?? '',
    trip_offset: props.settings?.trip_offset ?? 0,
});

const streamForm = useForm({
    srt_url: props.settings?.srt_url ?? '',
    srt_stats_url: props.settings?.srt_stats_url ?? '',
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
