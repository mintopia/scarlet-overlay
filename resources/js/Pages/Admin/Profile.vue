<template>
    <AdminLayout>
        <Head title="Profile" />
        <h1 class="text-[22px] font-bold mb-6">Profile</h1>

        <!-- Your Details -->
        <form @submit.prevent="profileForm.put(route('admin.profile.update'))" class="bg-surface border border-border rounded-[10px] p-6 mb-6">
            <h2 class="text-[15px] font-semibold mb-4">Your Details</h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-[13px] font-medium text-text-secondary mb-1.5">Name</label>
                    <input
                        v-model="profileForm.name"
                        type="text"
                        required
                        class="w-full h-[42px] px-3.5 text-sm bg-bg border border-border rounded-[7px] font-sans focus:border-scarlet focus:ring-1 focus:ring-scarlet/20 outline-none"
                    />
                    <p v-if="profileForm.errors.name" class="mt-1 text-xs text-error">{{ profileForm.errors.name }}</p>
                </div>
                <div>
                    <label class="block text-[13px] font-medium text-text-secondary mb-1.5">Email</label>
                    <input
                        v-model="profileForm.email"
                        type="email"
                        required
                        class="w-full h-[42px] px-3.5 text-sm bg-bg border border-border rounded-[7px] font-sans focus:border-scarlet focus:ring-1 focus:ring-scarlet/20 outline-none"
                    />
                    <p v-if="profileForm.errors.email" class="mt-1 text-xs text-error">{{ profileForm.errors.email }}</p>
                </div>
            </div>
            <div class="flex items-center gap-3 mt-5">
                <button
                    type="submit"
                    :disabled="profileForm.processing"
                    class="px-4 h-9 bg-scarlet text-white text-[13px] font-semibold rounded-[7px] hover:bg-scarlet-hover disabled:opacity-50"
                >
                    Save
                </button>
                <Transition name="saved-fade"><span v-if="profileForm.wasSuccessful" class="text-[13px] text-green">Saved.</span></Transition>
            </div>
        </form>

        <!-- Change Password -->
        <form @submit.prevent="passwordForm.put(route('admin.profile.password'))" class="bg-surface border border-border rounded-[10px] p-6 mb-6">
            <h2 class="text-[15px] font-semibold mb-4">Change Password</h2>
            <div class="grid grid-cols-1 gap-4">
                <div>
                    <label class="block text-[13px] font-medium text-text-secondary mb-1.5">Current Password</label>
                    <input
                        v-model="passwordForm.current_password"
                        type="password"
                        required
                        autocomplete="current-password"
                        class="w-full h-[42px] px-3.5 text-sm bg-bg border border-border rounded-[7px] font-sans focus:border-scarlet focus:ring-1 focus:ring-scarlet/20 outline-none sm:max-w-sm"
                    />
                    <p v-if="passwordForm.errors.current_password" class="mt-1 text-xs text-error">{{ passwordForm.errors.current_password }}</p>
                </div>
                <div>
                    <label class="block text-[13px] font-medium text-text-secondary mb-1.5">New Password</label>
                    <input
                        v-model="passwordForm.password"
                        type="password"
                        required
                        autocomplete="new-password"
                        class="w-full h-[42px] px-3.5 text-sm bg-bg border border-border rounded-[7px] font-sans focus:border-scarlet focus:ring-1 focus:ring-scarlet/20 outline-none sm:max-w-sm"
                    />
                    <p v-if="passwordForm.errors.password" class="mt-1 text-xs text-error">{{ passwordForm.errors.password }}</p>
                </div>
                <div>
                    <label class="block text-[13px] font-medium text-text-secondary mb-1.5">Confirm New Password</label>
                    <input
                        v-model="passwordForm.password_confirmation"
                        type="password"
                        required
                        autocomplete="new-password"
                        class="w-full h-[42px] px-3.5 text-sm bg-bg border border-border rounded-[7px] font-sans focus:border-scarlet focus:ring-1 focus:ring-scarlet/20 outline-none sm:max-w-sm"
                    />
                </div>
            </div>
            <div class="flex items-center gap-3 mt-5">
                <button
                    type="submit"
                    :disabled="passwordForm.processing"
                    class="px-4 h-9 bg-scarlet text-white text-[13px] font-semibold rounded-[7px] hover:bg-scarlet-hover disabled:opacity-50"
                >
                    Update password
                </button>
                <Transition name="saved-fade"><span v-if="passwordForm.wasSuccessful" class="text-[13px] text-green">Password updated.</span></Transition>
            </div>
        </form>

        <!-- Passkeys -->
        <div class="bg-surface border border-border rounded-[10px] p-6 mb-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-[15px] font-semibold">Passkeys</h2>
                <button
                    type="button"
                    @click="registerPasskey"
                    :disabled="passkeyRegistering"
                    class="px-4 h-9 bg-scarlet text-white text-[13px] font-semibold rounded-[7px] hover:bg-scarlet-hover disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    {{ passkeyRegistering ? 'Registering…' : 'Register new' }}
                </button>
            </div>
            <p v-if="passkeyError" class="mb-3 text-[13px] text-error">{{ passkeyError }}</p>
            <p v-if="passkeySuccess" class="mb-3 text-[13px] text-green">{{ passkeySuccess }}</p>
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-border">
                        <th class="text-left text-[13px] font-medium text-text-secondary pb-2">Name</th>
                        <th class="text-left text-[13px] font-medium text-text-secondary pb-2">Registered</th>
                        <th class="text-left text-[13px] font-medium text-text-secondary pb-2">Last Used</th>
                        <th class="text-left text-[13px] font-medium text-text-secondary pb-2">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="passkeys.length === 0">
                        <td colspan="4" class="py-6 text-center text-[13px] text-text-secondary">No passkeys registered.</td>
                    </tr>
                    <tr v-for="pk in passkeys" :key="pk.id" class="border-b border-border last:border-0">
                        <td class="py-3 text-[13px]">{{ pk.alias ?? 'Passkey' }}</td>
                        <td class="py-3 text-[13px] text-text-secondary">{{ formatDate(pk.created_at) }}</td>
                        <td class="py-3 text-[13px] text-text-secondary">{{ pk.updated_at ? formatDate(pk.updated_at) : '—' }}</td>
                        <td class="py-3">
                            <button
                                type="button"
                                @click="removePasskey(pk.id)"
                                class="text-[13px] text-error hover:text-scarlet-hover font-medium"
                            >
                                Remove
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({ user: Object });

const profileForm = useForm({
    name: props.user?.name ?? '',
    email: props.user?.email ?? '',
});

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

// --- Passkey management ---
const passkeys = ref([]);
const passkeyRegistering = ref(false);
const passkeyError = ref('');
const passkeySuccess = ref('');

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

function formatDate(dateStr) {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}

function arrayBufferToBase64(buffer) {
    return btoa(String.fromCharCode(...new Uint8Array(buffer)));
}

function base64UrlDecode(input) {
    input = input.replace(/-/g, '+').replace(/_/g, '/');
    const pad = input.length % 4;
    if (pad) input += '='.repeat(4 - pad);
    const binary = atob(input);
    return Uint8Array.from(binary, c => c.charCodeAt(0));
}

function parsePublicKeyOptions(options) {
    options.challenge = base64UrlDecode(options.challenge);
    if (options.user?.id) {
        options.user.id = base64UrlDecode(options.user.id);
    }
    if (options.excludeCredentials) {
        options.excludeCredentials = options.excludeCredentials.map(c => ({
            ...c,
            id: base64UrlDecode(c.id),
        }));
    }
    return options;
}

function serializeCredential(credential) {
    const response = {};
    const keys = ['clientDataJSON', 'attestationObject', 'authenticatorData', 'signature', 'userHandle'];
    keys.forEach(key => {
        if (credential.response[key]) {
            response[key] = arrayBufferToBase64(credential.response[key]);
        }
    });
    return {
        id: credential.id,
        type: credential.type,
        rawId: arrayBufferToBase64(credential.rawId),
        authenticatorAttachment: credential.authenticatorAttachment,
        clientExtensionResults: credential.getClientExtensionResults(),
        response,
    };
}

async function fetchPasskeys() {
    try {
        const res = await fetch('/passkey/list', {
            headers: { 'Accept': 'application/json' },
        });
        if (res.ok) {
            passkeys.value = await res.json();
        }
    } catch (e) {
        // silently fail
    }
}

async function registerPasskey() {
    if (!window.PublicKeyCredential) {
        passkeyError.value = 'This browser does not support passkeys.';
        return;
    }

    passkeyRegistering.value = true;
    passkeyError.value = '';
    passkeySuccess.value = '';

    const headers = {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': getCsrfToken(),
    };

    try {
        const optionsResponse = await fetch('/passkey/register/options', {
            method: 'POST',
            headers,
            body: JSON.stringify({}),
        });

        if (!optionsResponse.ok) {
            throw new Error('Failed to get registration options.');
        }

        const optionsJson = await optionsResponse.json();
        const publicKey = parsePublicKeyOptions(optionsJson);

        const credential = await navigator.credentials.create({ publicKey });

        if (!credential) {
            throw new Error('No credential returned from device.');
        }

        const serialized = serializeCredential(credential);

        const registerResponse = await fetch('/passkey/register', {
            method: 'POST',
            headers,
            body: JSON.stringify(serialized),
        });

        if (!registerResponse.ok) {
            throw new Error('Failed to save passkey on server.');
        }

        passkeySuccess.value = 'Passkey registered successfully.';
        await fetchPasskeys();
    } catch (e) {
        if (e.name === 'NotAllowedError') {
            passkeyError.value = 'Passkey registration was cancelled or timed out.';
        } else {
            passkeyError.value = e.message ?? 'Passkey registration failed.';
        }
    } finally {
        passkeyRegistering.value = false;
    }
}

async function removePasskey(id) {
    passkeyError.value = '';
    passkeySuccess.value = '';

    try {
        const res = await fetch(`/passkey/${id}`, {
            method: 'DELETE',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
        });

        if (res.ok) {
            passkeySuccess.value = 'Passkey removed.';
            passkeys.value = passkeys.value.filter(pk => pk.id !== id);
        } else {
            throw new Error('Failed to remove passkey.');
        }
    } catch (e) {
        passkeyError.value = e.message ?? 'Could not remove passkey.';
    }
}

onMounted(() => {
    fetchPasskeys();
});
</script>

<style scoped>
.saved-fade-enter-active {
    transition: opacity 0.3s cubic-bezier(0.16, 1, 0.3, 1),
                transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}
.saved-fade-enter-from {
    opacity: 0;
    transform: translateX(-4px);
}
@media (prefers-reduced-motion: reduce) {
    .saved-fade-enter-active { transition: none; }
}
</style>
