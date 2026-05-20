<template>
    <AdminLayout>
        <Head title="Settings" />
        <h1 class="text-[22px] font-bold mb-6">Settings</h1>

        <!-- Boat Identity -->
        <form @submit.prevent="identityForm.put(route('admin.settings.identity'))" class="bg-surface border border-border rounded-[10px] p-6 mb-6">
            <h2 class="text-[15px] font-semibold mb-4">Boat Identity</h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-[13px] font-medium text-text-secondary mb-1.5">Boat Name</label>
                    <input
                        v-model="identityForm.boat_name"
                        type="text"
                        required
                        class="w-full h-[42px] px-3.5 text-sm bg-bg border border-border rounded-[7px] font-sans focus:border-scarlet focus:ring-1 focus:ring-scarlet/20 outline-none"
                    />
                    <p v-if="identityForm.errors.boat_name" class="mt-1 text-xs text-red-500">{{ identityForm.errors.boat_name }}</p>
                </div>
                <div>
                    <label class="block text-[13px] font-medium text-text-secondary mb-1.5">MMSI (9 digits)</label>
                    <input
                        v-model="identityForm.mmsi"
                        type="text"
                        maxlength="9"
                        placeholder="e.g. 235117890"
                        class="w-full h-[42px] px-3.5 text-sm bg-bg border border-border rounded-[7px] font-sans focus:border-scarlet focus:ring-1 focus:ring-scarlet/20 outline-none"
                    />
                    <p v-if="identityForm.errors.mmsi" class="mt-1 text-xs text-red-500">{{ identityForm.errors.mmsi }}</p>
                </div>
            </div>
            <div class="flex items-center gap-3 mt-5">
                <button
                    type="submit"
                    :disabled="identityForm.processing"
                    class="px-4 h-9 bg-scarlet text-white text-[13px] font-semibold rounded-[7px] hover:bg-scarlet-hover disabled:opacity-50"
                >
                    Save
                </button>
                <span v-if="identityForm.wasSuccessful" class="text-[13px] text-green-600">Saved.</span>
            </div>
        </form>

        <!-- Current Passage -->
        <form @submit.prevent="passageForm.put(route('admin.settings.passage'))" class="bg-surface border border-border rounded-[10px] p-6 mb-6">
            <h2 class="text-[15px] font-semibold mb-4">Current Passage</h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-[13px] font-medium text-text-secondary mb-1.5">From</label>
                    <input
                        v-model="passageForm.passage_from"
                        type="text"
                        placeholder="e.g. La Rochelle"
                        class="w-full h-[42px] px-3.5 text-sm bg-bg border border-border rounded-[7px] font-sans focus:border-scarlet focus:ring-1 focus:ring-scarlet/20 outline-none"
                    />
                    <p v-if="passageForm.errors.passage_from" class="mt-1 text-xs text-red-500">{{ passageForm.errors.passage_from }}</p>
                </div>
                <div>
                    <label class="block text-[13px] font-medium text-text-secondary mb-1.5">To</label>
                    <input
                        v-model="passageForm.passage_to"
                        type="text"
                        placeholder="e.g. Hendaye"
                        class="w-full h-[42px] px-3.5 text-sm bg-bg border border-border rounded-[7px] font-sans focus:border-scarlet focus:ring-1 focus:ring-scarlet/20 outline-none"
                    />
                    <p v-if="passageForm.errors.passage_to" class="mt-1 text-xs text-red-500">{{ passageForm.errors.passage_to }}</p>
                </div>
            </div>
            <div class="flex items-center gap-3 mt-5">
                <button
                    type="submit"
                    :disabled="passageForm.processing"
                    class="px-4 h-9 bg-scarlet text-white text-[13px] font-semibold rounded-[7px] hover:bg-scarlet-hover disabled:opacity-50"
                >
                    Save
                </button>
                <span v-if="passageForm.wasSuccessful" class="text-[13px] text-green-600">Saved.</span>
            </div>
        </form>

        <!-- Port Settings -->
        <form @submit.prevent="portForm.put(route('admin.settings.port'))" class="bg-surface border border-border rounded-[10px] p-6 mb-6">
            <h2 class="text-[15px] font-semibold mb-4">Port Settings</h2>
            <div>
                <label class="block text-[13px] font-medium text-text-secondary mb-1.5">Port Name</label>
                <input
                    v-model="portForm.port_name"
                    type="text"
                    placeholder="e.g. Lymington Marina"
                    class="w-full h-[42px] px-3.5 text-sm bg-bg border border-border rounded-[7px] font-sans focus:border-scarlet focus:ring-1 focus:ring-scarlet/20 outline-none sm:max-w-sm"
                />
                <p v-if="portForm.errors.port_name" class="mt-1 text-xs text-red-500">{{ portForm.errors.port_name }}</p>
            </div>
            <div class="flex items-center gap-3 mt-5">
                <button
                    type="submit"
                    :disabled="portForm.processing"
                    class="px-4 h-9 bg-scarlet text-white text-[13px] font-semibold rounded-[7px] hover:bg-scarlet-hover disabled:opacity-50"
                >
                    Save
                </button>
                <span v-if="portForm.wasSuccessful" class="text-[13px] text-green-600">Saved.</span>
            </div>
        </form>
        <!-- Stream -->
        <form @submit.prevent="streamForm.put(route('admin.settings.stream'))" class="bg-surface border border-border rounded-[10px] p-6 mb-6">
            <h2 class="text-[15px] font-semibold mb-4">Stream</h2>
            <div class="grid grid-cols-1 gap-4">
                <div>
                    <label class="block text-[13px] font-medium text-text-secondary mb-1.5">SRT URL</label>
                    <input
                        v-model="streamForm.srt_url"
                        type="text"
                        placeholder="e.g. srt://host:port?streamid=..."
                        class="w-full h-[42px] px-3.5 text-sm bg-bg border border-border rounded-[7px] font-sans focus:border-scarlet focus:ring-1 focus:ring-scarlet/20 outline-none"
                    />
                    <p class="mt-1 text-[12px] text-text-secondary">The SRT ingest URL for the video feed on the broadcast overlay.</p>
                    <p v-if="streamForm.errors.srt_url" class="mt-1 text-xs text-red-500">{{ streamForm.errors.srt_url }}</p>
                </div>
                <div>
                    <label class="block text-[13px] font-medium text-text-secondary mb-1.5">SRT Stats URL</label>
                    <input
                        v-model="streamForm.srt_stats_url"
                        type="text"
                        placeholder="e.g. https://stats.srt.belabox.net/..."
                        class="w-full h-[42px] px-3.5 text-sm bg-bg border border-border rounded-[7px] font-sans focus:border-scarlet focus:ring-1 focus:ring-scarlet/20 outline-none"
                    />
                    <p class="mt-1 text-[12px] text-text-secondary">BELABOX SRT stats endpoint for the Stream Monitor page.</p>
                    <p v-if="streamForm.errors.srt_stats_url" class="mt-1 text-xs text-red-500">{{ streamForm.errors.srt_stats_url }}</p>
                </div>
            </div>
            <div class="flex items-center gap-3 mt-5">
                <button
                    type="submit"
                    :disabled="streamForm.processing"
                    class="px-4 h-9 bg-scarlet text-white text-[13px] font-semibold rounded-[7px] hover:bg-scarlet-hover disabled:opacity-50"
                >
                    Save
                </button>
                <span v-if="streamForm.wasSuccessful" class="text-[13px] text-green-600">Saved.</span>
            </div>
        </form>
    </AdminLayout>
</template>

<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({ settings: Object });

const identityForm = useForm({
    boat_name: props.settings?.boat_name ?? '',
    mmsi: props.settings?.mmsi ?? '',
});

const passageForm = useForm({
    passage_from: props.settings?.passage_from ?? '',
    passage_to: props.settings?.passage_to ?? '',
});

const portForm = useForm({
    port_name: props.settings?.port_name ?? '',
});

const streamForm = useForm({
    srt_url: props.settings?.srt_url ?? '',
    srt_stats_url: props.settings?.srt_stats_url ?? '',
});
</script>
