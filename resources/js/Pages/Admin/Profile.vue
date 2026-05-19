<template>
    <AdminLayout>
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
                    <p v-if="profileForm.errors.name" class="mt-1 text-xs text-red-500">{{ profileForm.errors.name }}</p>
                </div>
                <div>
                    <label class="block text-[13px] font-medium text-text-secondary mb-1.5">Email</label>
                    <input
                        v-model="profileForm.email"
                        type="email"
                        required
                        class="w-full h-[42px] px-3.5 text-sm bg-bg border border-border rounded-[7px] font-sans focus:border-scarlet focus:ring-1 focus:ring-scarlet/20 outline-none"
                    />
                    <p v-if="profileForm.errors.email" class="mt-1 text-xs text-red-500">{{ profileForm.errors.email }}</p>
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
                <span v-if="profileForm.wasSuccessful" class="text-[13px] text-green-600">Saved.</span>
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
                    <p v-if="passwordForm.errors.current_password" class="mt-1 text-xs text-red-500">{{ passwordForm.errors.current_password }}</p>
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
                    <p v-if="passwordForm.errors.password" class="mt-1 text-xs text-red-500">{{ passwordForm.errors.password }}</p>
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
                <span v-if="passwordForm.wasSuccessful" class="text-[13px] text-green-600">Password updated.</span>
            </div>
        </form>

        <!-- Passkeys -->
        <div class="bg-surface border border-border rounded-[10px] p-6 mb-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-[15px] font-semibold">Passkeys</h2>
                <button
                    type="button"
                    disabled
                    class="px-4 h-9 bg-scarlet text-white text-[13px] font-semibold rounded-[7px] disabled:opacity-50 cursor-not-allowed"
                >
                    Register new
                </button>
            </div>
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
                    <tr>
                        <td colspan="4" class="py-6 text-center text-[13px] text-text-secondary">No passkeys registered.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3';
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
</script>
