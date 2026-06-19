<template>
    <AdminLayout>
        <Head title="Team" />

        <!-- Members -->
        <div class="relative bg-surface border border-border rounded-[10px] mb-6">
            <div class="flex items-center justify-between px-4 md:px-6 py-4 border-b border-border">
                <h2 class="text-[15px] font-semibold">Members</h2>
                <button
                    @click="showInviteModal = true"
                    class="btn btn--primary"
                >
                    Invite member
                </button>
            </div>

            <div class="scroll-affordance overflow-x-auto rounded-b-[10px]">
            <table class="w-full min-w-[480px]">
                <thead>
                    <tr class="border-b border-border">
                        <th class="px-4 md:px-6 py-3 text-left text-[12px] font-semibold text-text-secondary uppercase tracking-wide">User</th>
                        <th class="px-4 md:px-6 py-3 text-left text-[12px] font-semibold text-text-secondary uppercase tracking-wide">Last Active</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="member in members"
                        :key="member.id"
                        class="border-b border-border last:border-0"
                    >
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="member-avatar">
                                    {{ member.initials }}
                                </div>
                                <div>
                                    <div class="text-[15px] font-medium">{{ member.name }}</div>
                                    <div class="text-[13px] text-text-secondary">{{ member.email }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-[13px] text-text-secondary">
                            {{ member.last_active }}
                        </td>
                        <td class="px-6 py-4 text-right">
                            <button
                                v-if="member.id !== currentUserId"
                                @click="confirmRemove(member)"
                                class="text-[13px] text-error hover:text-scarlet-hover font-medium"
                            >
                                Remove
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
            </div>
        </div>

        <!-- Pending Invites -->
        <div v-if="invites.length > 0" class="bg-surface border border-border rounded-[10px]">
            <div class="px-4 md:px-6 py-4 border-b border-border">
                <h2 class="text-[15px] font-semibold">Pending Invites</h2>
            </div>

            <div class="scroll-affordance overflow-x-auto rounded-b-[10px]">
            <table class="w-full min-w-[400px]">
                <thead>
                    <tr class="border-b border-border">
                        <th class="px-4 md:px-6 py-3 text-left text-[12px] font-semibold text-text-secondary uppercase tracking-wide">Email</th>
                        <th class="px-4 md:px-6 py-3 text-left text-[12px] font-semibold text-text-secondary uppercase tracking-wide">Sent</th>
                        <th class="px-4 md:px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="invite in invites"
                        :key="invite.id"
                        class="border-b border-border last:border-0"
                    >
                        <td class="px-6 py-4 text-[14px]">{{ invite.email }}</td>
                        <td class="px-6 py-4 text-[13px] text-text-secondary">{{ formatDate(invite.created_at) }}</td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-3">
                                <button
                                    @click="resendInvite(invite)"
                                    class="text-[13px] text-scarlet hover:underline font-medium"
                                >
                                    Resend
                                </button>
                                <button
                                    @click="cancelInvite(invite)"
                                    class="text-[13px] text-text-secondary hover:text-error font-medium"
                                >
                                    Cancel
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
            </div>
        </div>

        <!-- Invite Modal -->
        <Transition name="modal">
        <div v-if="showInviteModal" class="modal-overlay" @click.self="showInviteModal = false" @keydown.escape="showInviteModal = false">
            <div ref="inviteCard" tabindex="-1" class="modal-card" role="dialog" aria-modal="true" aria-labelledby="invite-modal-title" @keydown.tab="trapFocus">
                <h3 id="invite-modal-title" class="text-[16px] font-semibold mb-4">Invite a crew member</h3>
                <form @submit.prevent="sendInvite">
                    <div class="mb-4">
                        <label for="invite-email" class="block text-[13px] font-medium text-text-secondary mb-1.5">Email address</label>
                        <input
                            id="invite-email"
                            v-model="inviteForm.email"
                            type="email"
                            required
                            placeholder="crew@example.com"
                            class="w-full h-[42px] px-3.5 text-sm bg-bg border border-border rounded-[7px] font-sans focus:border-scarlet focus:ring-1 focus:ring-scarlet/20 outline-none"
                            autofocus
                        />
                        <p v-if="inviteForm.errors.email" class="mt-1 text-xs text-error">{{ inviteForm.errors.email }}</p>
                    </div>
                    <div class="flex items-center justify-end gap-3">
                        <button type="button" @click="showInviteModal = false" class="btn btn--ghost">
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="inviteForm.processing"
                            class="btn btn--primary"
                        >
                            Send invite
                        </button>
                    </div>
                </form>
            </div>
        </div>
        </Transition>

        <!-- Remove Confirm Modal -->
        <Transition name="modal">
        <div v-if="removingMember" class="modal-overlay" @click.self="removingMember = null" @keydown.escape="removingMember = null">
            <div ref="removeCard" tabindex="-1" class="modal-card" role="dialog" aria-modal="true" aria-labelledby="remove-modal-title" @keydown.tab="trapFocus">
                <h3 id="remove-modal-title" class="text-[16px] font-semibold mb-2">Remove {{ removingMember.name }}?</h3>
                <p class="text-[13px] text-text-secondary mb-5">This will permanently remove their access to the dashboard.</p>
                <div class="flex items-center justify-end gap-3">
                    <button @click="removingMember = null" class="btn btn--ghost">
                        Cancel
                    </button>
                    <button
                        @click="removeMember"
                        class="btn btn--danger"
                    >
                        Remove
                    </button>
                </div>
            </div>
        </div>
        </Transition>
    </AdminLayout>
</template>

<script setup>
import { ref, nextTick, watch } from 'vue';
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    members: Array,
    invites: Array,
});

const page = usePage();
const currentUserId = page.props.auth?.user?.id;

const showInviteModal = ref(false);
const removingMember = ref(null);
const inviteCard = ref(null);
const removeCard = ref(null);

watch(removingMember, (member) => {
    if (member) {
        nextTick(() => removeCard.value?.focus());
    }
});

const inviteForm = useForm({ email: '' });

function sendInvite() {
    inviteForm.post(route('admin.team.invite'), {
        onSuccess: () => {
            showInviteModal.value = false;
            inviteForm.reset();
        },
    });
}

function resendInvite(invite) {
    router.post(route('admin.team.resend', invite.id));
}

function cancelInvite(invite) {
    router.delete(route('admin.team.destroy-invite', invite.id));
}

function confirmRemove(member) {
    removingMember.value = member;
}

function removeMember() {
    router.delete(route('admin.team.destroy', removingMember.value.id), {
        onSuccess: () => { removingMember.value = null; },
    });
}

function formatDate(dateStr) {
    return new Date(dateStr).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
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
</script>

<style scoped>
.member-avatar {
    width: 36px; height: 36px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 13px; font-weight: 700; letter-spacing: 0.02em;
    flex-shrink: 0;
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

/* Mobile-only right-edge fade hinting that the table scrolls horizontally. */
@media (max-width: 767px) {
    .scroll-affordance {
        -webkit-mask-image: linear-gradient(to right, #000 calc(100% - 28px), transparent 100%);
        mask-image: linear-gradient(to right, #000 calc(100% - 28px), transparent 100%);
    }
}
</style>
