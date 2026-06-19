<template>
    <AdminLayout>
        <Head title="Profile" />
        <h1 class="text-[22px] font-bold mb-6">Profile</h1>

        <!-- Your Details -->
        <form @submit.prevent="profileForm.put(route('admin.profile.update'))" class="bg-surface border border-border rounded-[10px] p-6 mb-6">
            <h2 class="text-[15px] font-semibold mb-4">Your Details</h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="profile-name" class="field-label">Name</label>
                    <input
                        id="profile-name"
                        v-model="profileForm.name"
                        type="text"
                        required
                        class="field-input"
                    />
                    <p v-if="profileForm.errors.name" class="field-error">{{ profileForm.errors.name }}</p>
                </div>
                <div>
                    <label for="profile-email" class="field-label">Email</label>
                    <input
                        id="profile-email"
                        v-model="profileForm.email"
                        type="email"
                        required
                        class="field-input"
                    />
                    <p v-if="profileForm.errors.email" class="field-error">{{ profileForm.errors.email }}</p>
                </div>
            </div>
            <div class="flex items-center gap-3 mt-5">
                <button
                    type="submit"
                    :disabled="profileForm.processing"
                    class="btn btn--primary"
                >
                    Save
                </button>
                <Transition name="saved-fade"><SavedCheck v-if="profileForm.wasSuccessful" /></Transition>
            </div>
        </form>

        <!-- Change Password -->
        <form @submit.prevent="passwordForm.put(route('admin.profile.password'))" class="bg-surface border border-border rounded-[10px] p-6 mb-6">
            <h2 class="text-[15px] font-semibold mb-4">Change Password</h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="current-password" class="field-label">Current Password</label>
                    <input
                        id="current-password"
                        v-model="passwordForm.current_password"
                        type="password"
                        required
                        autocomplete="current-password"
                        class="field-input"
                    />
                    <p v-if="passwordForm.errors.current_password" class="field-error">{{ passwordForm.errors.current_password }}</p>
                </div>
                <div>
                    <label for="new-password" class="field-label">New Password</label>
                    <input
                        id="new-password"
                        v-model="passwordForm.password"
                        type="password"
                        required
                        autocomplete="new-password"
                        class="field-input"
                    />
                    <p v-if="passwordForm.errors.password" class="field-error">{{ passwordForm.errors.password }}</p>
                </div>
                <div>
                    <label for="confirm-new-password" class="field-label">Confirm New Password</label>
                    <input
                        id="confirm-new-password"
                        v-model="passwordForm.password_confirmation"
                        type="password"
                        required
                        autocomplete="new-password"
                        class="field-input"
                    />
                </div>
            </div>
            <div class="flex items-center gap-3 mt-5">
                <button
                    type="submit"
                    :disabled="passwordForm.processing"
                    class="btn btn--primary"
                >
                    Update password
                </button>
                <Transition name="saved-fade"><SavedCheck v-if="passwordForm.wasSuccessful" label="Password updated" /></Transition>
            </div>
        </form>

        <!-- Passkeys -->
        <div class="bg-surface border border-border rounded-[10px] p-4 md:p-6 mb-6 overflow-x-auto">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-[15px] font-semibold">Passkeys</h2>
                <button
                    type="button"
                    @click="registerPasskey"
                    :disabled="passkeyRegistering"
                    class="btn btn--primary disabled:cursor-not-allowed"
                >
                    {{ passkeyRegistering ? 'Registering…' : 'Register new' }}
                </button>
            </div>
            <p v-if="passkeyError" role="alert" aria-live="assertive" class="mb-3 text-[13px] text-error">{{ passkeyError }}</p>
            <p v-if="passkeySuccess" role="status" aria-live="polite" class="mb-3 text-[13px] text-green">{{ passkeySuccess }}</p>
            <table class="w-full text-sm min-w-[440px]">
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
                        <td class="py-3 text-[13px] text-text-secondary">{{ lastUsedLabel(pk) }}</td>
                        <td class="py-3">
                            <div class="flex items-center gap-3">
                                <button
                                    type="button"
                                    @click="openRenameModal(pk)"
                                    class="text-[13px] text-scarlet hover:underline font-medium"
                                >
                                    Rename
                                </button>
                                <button
                                    type="button"
                                    @click="confirmRemovePasskey(pk)"
                                    class="text-[13px] text-error hover:text-scarlet-hover font-medium"
                                >
                                    Remove
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Name Passkey Modal -->
        <Transition name="modal">
        <div v-if="namingPasskey" class="modal-overlay" @click.self="cancelNaming" @keydown.escape="cancelNaming">
            <div tabindex="-1" class="modal-card" role="dialog" aria-modal="true" aria-labelledby="name-passkey-title" @keydown.tab="trapFocus">
                <h3 id="name-passkey-title" class="text-[16px] font-semibold mb-2">{{ namingPasskey.isNew ? 'Name your passkey' : 'Rename passkey' }}</h3>
                <p v-if="namingPasskey.isNew" class="text-[13px] text-text-secondary mb-4">
                    Give this passkey a name so you can identify it later.
                </p>
                <form @submit.prevent="savePasskeyName">
                    <div class="mb-4">
                        <label for="passkey-alias" class="block text-[13px] font-medium text-text-secondary mb-1.5">Name</label>
                        <input
                            id="passkey-alias"
                            ref="nameInput"
                            v-model="passkeyNameValue"
                            type="text"
                            required
                            maxlength="255"
                            placeholder="e.g. MacBook Pro, iPhone"
                            class="w-full h-[42px] px-3.5 text-sm bg-bg border border-border rounded-[7px] font-sans focus:border-scarlet focus:ring-1 focus:ring-scarlet/20 outline-none"
                        />
                        <p v-if="nameError" class="mt-1 text-xs text-error">{{ nameError }}</p>
                    </div>
                    <div class="flex items-center justify-end gap-3">
                        <button type="button" @click="cancelNaming" class="btn btn--ghost">
                            {{ namingPasskey.isNew ? 'Skip' : 'Cancel' }}
                        </button>
                        <button
                            type="submit"
                            :disabled="namingSaving"
                            class="btn btn--primary"
                        >
                            {{ namingSaving ? 'Saving…' : 'Save' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
        </Transition>

        <!-- Remove Passkey Modal -->
        <Transition name="modal">
        <div v-if="removingPasskey" class="modal-overlay" @click.self="cancelRemovePasskey" @keydown.escape="cancelRemovePasskey">
            <div tabindex="-1" class="modal-card" role="dialog" aria-modal="true" aria-labelledby="remove-passkey-title" @keydown.tab="trapFocus">
                <h3 id="remove-passkey-title" class="text-[16px] font-semibold mb-2">Remove passkey?</h3>
                <p class="text-[13px] text-text-secondary mb-4">
                    This will permanently remove <strong>{{ removingPasskey.alias ?? 'this passkey' }}</strong>. You will no longer be able to sign in with it.
                </p>
                <form @submit.prevent="removePasskey">
                    <div class="mb-4">
                        <label for="remove-passkey-password" class="block text-[13px] font-medium text-text-secondary mb-1.5">Confirm your password</label>
                        <input
                            id="remove-passkey-password"
                            ref="removePasswordInput"
                            v-model="removePassword"
                            type="password"
                            required
                            autocomplete="current-password"
                            placeholder="Enter your password"
                            class="w-full h-[42px] px-3.5 text-sm bg-bg border border-border rounded-[7px] font-sans focus:border-scarlet focus:ring-1 focus:ring-scarlet/20 outline-none"
                        />
                        <p v-if="removePasswordError" class="mt-1 text-xs text-error">{{ removePasswordError }}</p>
                    </div>
                    <div class="flex items-center justify-end gap-3">
                        <button type="button" @click="cancelRemovePasskey" class="btn btn--ghost">
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="removingInProgress"
                            class="btn btn--danger"
                        >
                            {{ removingInProgress ? 'Removing…' : 'Remove passkey' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
        </Transition>
    </AdminLayout>
</template>

<script setup>
import { ref, nextTick, onMounted } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import SavedCheck from '@/components/SavedCheck.vue';

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
const removingPasskey = ref(null);
const removePassword = ref('');
const removePasswordError = ref('');
const removingInProgress = ref(false);
const removePasswordInput = ref(null);
const namingPasskey = ref(null);
const passkeyNameValue = ref('');
const nameError = ref('');
const namingSaving = ref(false);
const nameInput = ref(null);

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

function formatDate(dateStr) {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}

function lastUsedLabel(pk) {
    if (!pk.updated_at || pk.updated_at === pk.created_at) return 'Never';
    return formatDate(pk.updated_at);
}

function arrayBufferToBase64Url(buffer) {
    return btoa(String.fromCharCode(...new Uint8Array(buffer)))
        .replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
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
            response[key] = arrayBufferToBase64Url(credential.response[key]);
        }
    });
    return {
        id: credential.id,
        type: credential.type,
        rawId: arrayBufferToBase64Url(credential.rawId),
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

        const regData = await registerResponse.json();
        await fetchPasskeys();
        openNameModal(regData.id, true);
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

function openNameModal(id, isNew) {
    const pk = passkeys.value.find(p => p.id === id);
    namingPasskey.value = { id, isNew, alias: pk?.alias ?? '' };
    passkeyNameValue.value = pk?.alias ?? '';
    nameError.value = '';
    passkeyError.value = '';
    passkeySuccess.value = '';
    nextTick(() => nameInput.value?.focus());
}

function openRenameModal(pk) {
    openNameModal(pk.id, false);
}

function cancelNaming() {
    if (namingPasskey.value?.isNew) {
        passkeySuccess.value = 'Passkey registered successfully.';
    }
    namingPasskey.value = null;
    passkeyNameValue.value = '';
    nameError.value = '';
}

async function savePasskeyName() {
    nameError.value = '';
    namingSaving.value = true;

    try {
        const res = await fetch(`/passkey/${namingPasskey.value.id}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify({ alias: passkeyNameValue.value }),
        });

        if (res.ok) {
            const pk = passkeys.value.find(p => p.id === namingPasskey.value.id);
            if (pk) pk.alias = passkeyNameValue.value;
            const wasNew = namingPasskey.value.isNew;
            namingPasskey.value = null;
            passkeyNameValue.value = '';
            passkeySuccess.value = wasNew ? 'Passkey registered successfully.' : 'Passkey renamed.';
        } else {
            const data = await res.json().catch(() => ({}));
            nameError.value = data.errors?.alias?.[0] ?? 'Failed to save name.';
        }
    } catch (e) {
        nameError.value = 'Could not save name.';
    } finally {
        namingSaving.value = false;
    }
}

function confirmRemovePasskey(pk) {
    removingPasskey.value = pk;
    removePassword.value = '';
    removePasswordError.value = '';
    passkeyError.value = '';
    passkeySuccess.value = '';
    nextTick(() => removePasswordInput.value?.focus());
}

function cancelRemovePasskey() {
    removingPasskey.value = null;
    removePassword.value = '';
    removePasswordError.value = '';
}

async function removePasskey() {
    removePasswordError.value = '';
    removingInProgress.value = true;

    try {
        const res = await fetch(`/passkey/${removingPasskey.value.id}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify({ password: removePassword.value }),
        });

        if (res.ok) {
            const removedId = removingPasskey.value.id;
            removingPasskey.value = null;
            removePassword.value = '';
            passkeySuccess.value = 'Passkey removed.';
            passkeys.value = passkeys.value.filter(pk => pk.id !== removedId);
        } else {
            const data = await res.json().catch(() => ({}));
            removePasswordError.value = data.errors?.password?.[0] ?? 'Incorrect password.';
        }
    } catch (e) {
        removePasswordError.value = 'Could not remove passkey.';
    } finally {
        removingInProgress.value = false;
    }
}

function trapFocus(event) {
    const modal = event.currentTarget;
    const focusable = modal.querySelectorAll('input, button, [tabindex]:not([tabindex="-1"])');
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
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

.modal-overlay {
    position: fixed; inset: 0;
    background: rgba(0, 0, 0, 0.45);
    display: flex; align-items: center; justify-content: center;
    z-index: 100; padding: 24px;
}
.modal-card {
    background: var(--color-surface);
    border-radius: 12px;
    padding: 28px 28px 24px;
    width: 100%; max-width: 400px;
    box-shadow: 0 8px 40px rgba(0, 0, 0, 0.14);
}
.modal-enter-active, .modal-leave-active {
    transition: opacity 0.15s ease;
}
.modal-enter-active .modal-card, .modal-leave-active .modal-card {
    transition: transform 0.15s ease;
}
.modal-enter-from, .modal-leave-to {
    opacity: 0;
}
.modal-enter-from .modal-card {
    transform: scale(0.96);
}
</style>
