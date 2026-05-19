<template>
    <AdminLayout>
        <Head title="Team" />

        <!-- Members -->
        <div class="bg-surface border border-border rounded-[10px] mb-6">
            <div class="flex items-center justify-between px-6 py-4 border-b border-border">
                <h2 class="text-[15px] font-semibold">Members</h2>
                <button
                    v-if="isOwner"
                    @click="showInviteModal = true"
                    class="px-3.5 h-8 bg-scarlet text-white text-[13px] font-semibold rounded-[7px] hover:bg-scarlet-hover"
                >
                    Invite member
                </button>
            </div>

            <table class="w-full">
                <thead>
                    <tr class="border-b border-border">
                        <th class="px-6 py-3 text-left text-[12px] font-semibold text-text-secondary uppercase tracking-wide">User</th>
                        <th class="px-6 py-3 text-left text-[12px] font-semibold text-text-secondary uppercase tracking-wide">Role</th>
                        <th class="px-6 py-3 text-left text-[12px] font-semibold text-text-secondary uppercase tracking-wide">Last Active</th>
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
                                <div class="member-avatar" :class="member.role === 'owner' ? 'member-avatar--owner' : 'member-avatar--crew'">
                                    {{ member.initials }}
                                </div>
                                <div>
                                    <div class="text-[14px] font-medium">{{ member.name }}</div>
                                    <div class="text-[13px] text-text-secondary">{{ member.email }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="role-badge" :class="member.role === 'owner' ? 'role-badge--owner' : 'role-badge--crew'">
                                {{ member.role === 'owner' ? 'Owner' : 'Crew' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-[13px] text-text-secondary">
                            {{ member.last_active }}
                        </td>
                        <td class="px-6 py-4 text-right">
                            <button
                                v-if="isOwner && member.id !== currentUserId"
                                @click="confirmRemove(member)"
                                class="text-[13px] text-red-500 hover:text-red-700 font-medium"
                            >
                                Remove
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pending Invites -->
        <div v-if="isOwner && invites.length > 0" class="bg-surface border border-border rounded-[10px]">
            <div class="px-6 py-4 border-b border-border">
                <h2 class="text-[15px] font-semibold">Pending Invites</h2>
            </div>

            <table class="w-full">
                <thead>
                    <tr class="border-b border-border">
                        <th class="px-6 py-3 text-left text-[12px] font-semibold text-text-secondary uppercase tracking-wide">Email</th>
                        <th class="px-6 py-3 text-left text-[12px] font-semibold text-text-secondary uppercase tracking-wide">Sent</th>
                        <th class="px-6 py-3"></th>
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
                                    class="text-[13px] text-text-secondary hover:text-red-500 font-medium"
                                >
                                    Cancel
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Invite Modal -->
        <div v-if="showInviteModal" class="modal-overlay" @click.self="showInviteModal = false">
            <div class="modal-card">
                <h3 class="text-[16px] font-semibold mb-4">Invite a crew member</h3>
                <form @submit.prevent="sendInvite">
                    <div class="mb-4">
                        <label class="block text-[13px] font-medium text-text-secondary mb-1.5">Email address</label>
                        <input
                            v-model="inviteForm.email"
                            type="email"
                            required
                            placeholder="crew@example.com"
                            class="w-full h-[42px] px-3.5 text-sm bg-bg border border-border rounded-[7px] font-sans focus:border-scarlet focus:ring-1 focus:ring-scarlet/20 outline-none"
                            autofocus
                        />
                        <p v-if="inviteForm.errors.email" class="mt-1 text-xs text-red-500">{{ inviteForm.errors.email }}</p>
                    </div>
                    <div class="flex items-center justify-end gap-3">
                        <button type="button" @click="showInviteModal = false" class="px-4 h-9 text-[13px] font-medium text-text-secondary hover:text-primary">
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="inviteForm.processing"
                            class="px-4 h-9 bg-scarlet text-white text-[13px] font-semibold rounded-[7px] hover:bg-scarlet-hover disabled:opacity-50"
                        >
                            Send invite
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Remove Confirm Modal -->
        <div v-if="removingMember" class="modal-overlay" @click.self="removingMember = null">
            <div class="modal-card">
                <h3 class="text-[16px] font-semibold mb-2">Remove {{ removingMember.name }}?</h3>
                <p class="text-[13px] text-text-secondary mb-5">This will permanently remove their access to the dashboard.</p>
                <div class="flex items-center justify-end gap-3">
                    <button @click="removingMember = null" class="px-4 h-9 text-[13px] font-medium text-text-secondary hover:text-primary">
                        Cancel
                    </button>
                    <button
                        @click="removeMember"
                        class="px-4 h-9 bg-red-500 text-white text-[13px] font-semibold rounded-[7px] hover:bg-red-600"
                    >
                        Remove
                    </button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    members: Array,
    invites: Array,
});

const page = usePage();
const currentUserId = page.props.auth?.user?.id;
const isOwner = page.props.auth?.user?.role === 'owner';

const showInviteModal = ref(false);
const removingMember = ref(null);

const inviteForm = useForm({ email: '' });

function sendInvite() {
    inviteForm.post('/admin/team/invite', {
        onSuccess: () => {
            showInviteModal.value = false;
            inviteForm.reset();
        },
    });
}

function resendInvite(invite) {
    router.post(`/admin/team/invite/${invite.id}/resend`);
}

function cancelInvite(invite) {
    router.delete(`/admin/team/invite/${invite.id}`);
}

function confirmRemove(member) {
    removingMember.value = member;
}

function removeMember() {
    router.delete(`/admin/team/${removingMember.value.id}`, {
        onSuccess: () => { removingMember.value = null; },
    });
}

function formatDate(dateStr) {
    return new Date(dateStr).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
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
.member-avatar--owner {
    background: oklch(0.54 0.22 27 / 0.12);
    color: oklch(0.54 0.22 27);
}
.member-avatar--crew {
    background: oklch(0.88 0.005 70);
    color: oklch(0.45 0.005 40);
}

.role-badge {
    display: inline-flex; align-items: center;
    padding: 2px 9px;
    border-radius: 99px;
    font-size: 12px; font-weight: 600;
}
.role-badge--owner {
    background: oklch(0.54 0.22 27 / 0.10);
    color: oklch(0.54 0.22 27);
}
.role-badge--crew {
    background: oklch(0.92 0.003 70);
    color: oklch(0.45 0.005 40);
}

.modal-overlay {
    position: fixed; inset: 0;
    background: oklch(0 0 0 / 0.35);
    display: flex; align-items: center; justify-content: center;
    z-index: 50; padding: 24px;
}
.modal-card {
    background: oklch(1 0 0);
    border-radius: 12px;
    padding: 28px 28px 24px;
    width: 100%; max-width: 400px;
    box-shadow: 0 8px 40px oklch(0 0 0 / 0.14);
}
</style>
