<template>
    <AdminLayout>
        <Head title="Planner" />

        <div class="flex items-baseline justify-between mb-5">
            <h1 class="font-sans text-2xl font-extrabold tracking-tight">Planner</h1>
            <button class="btn btn--primary" @click="showCreate = true">+ New Plan</button>
        </div>

        <!-- Empty state -->
        <div v-if="!plans.length" class="panel p-12 flex flex-col items-center text-center">
            <div class="text-[15px] font-sans font-semibold text-text-primary mb-2">No plans yet</div>
            <div class="text-[13px] font-body text-text-dim mb-4">Create a plan to start comparing GPX routes.</div>
            <button class="btn btn--primary" @click="showCreate = true">+ Create your first plan</button>
        </div>

        <!-- Plan grid -->
        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <Link
                v-for="plan in plans"
                :key="plan.id"
                :href="`/admin/planner/${plan.slug}`"
                class="panel p-5 hover:border-text-dim transition-colors cursor-pointer"
            >
                <div class="text-[16px] font-sans font-bold text-text-primary mb-1">{{ plan.title }}</div>
                <div class="text-[12px] font-body text-text-dim mb-3">
                    {{ plan.groups_count }} {{ plan.groups_count === 1 ? 'group' : 'groups' }}
                    &middot;
                    {{ plan.routes_count }} {{ plan.routes_count === 1 ? 'route' : 'routes' }}
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-body text-text-dim">{{ formatDate(plan.created_at) }}</span>
                    <span v-if="plan.is_shared" class="inline-flex items-center gap-1 text-[10px] font-body font-bold uppercase tracking-wide text-teal">
                        <span class="inline-block w-1.5 h-1.5 rounded-full bg-teal" aria-hidden="true"></span>
                        Shared
                    </span>
                </div>
            </Link>
        </div>

        <!-- Create modal -->
        <Transition name="modal">
            <div
                v-if="showCreate"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/30"
                role="dialog"
                aria-modal="true"
                aria-labelledby="create-plan-title"
                @click.self="showCreate = false"
                @keydown.escape="showCreate = false"
            >
                <div
                    ref="modalRef"
                    tabindex="-1"
                    class="modal-card bg-surface border border-border rounded-2xl shadow-lg p-6 w-full max-w-sm"
                    @keydown.tab="trapFocus($event, modalRef)"
                >
                    <div id="create-plan-title" class="text-[16px] font-sans font-bold text-text-primary mb-4">New Plan</div>
                    <form @submit.prevent="createPlan">
                        <label for="plan-name" class="field-label">Plan Name</label>
                        <input
                            id="plan-name"
                            ref="titleInput"
                            v-model="form.title"
                            type="text"
                            class="field-input mb-1"
                            placeholder="e.g. Summer Crossing"
                            required
                        />
                        <div v-if="form.errors.title" class="field-error">{{ form.errors.title }}</div>
                        <div class="flex justify-end gap-2 mt-5">
                            <button type="button" class="btn btn--ghost" @click="showCreate = false">Cancel</button>
                            <button type="submit" class="btn btn--primary" :disabled="form.processing">Create</button>
                        </div>
                    </form>
                </div>
            </div>
        </Transition>
    </AdminLayout>
</template>

<script setup>
import { ref, nextTick, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { formatDate } from '@/lib/datetime';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineProps({
    plans: { type: Array, default: () => [] },
});

const showCreate = ref(false);
const titleInput = ref(null);
const modalRef = ref(null);
const form = useForm({ title: '' });

watch(showCreate, (val) => {
    if (val) nextTick(() => (titleInput.value || modalRef.value)?.focus());
});

function trapFocus(event, containerRef) {
    const modal = containerRef;
    if (!modal) {
        return;
    }
    const focusable = modal.querySelectorAll('input, button, textarea, select, [tabindex]:not([tabindex="-1"])');
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last?.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first?.focus();
    }
}

function createPlan() {
    form.post('/admin/planner', {
        onSuccess: () => {
            showCreate.value = false;
            form.reset();
        },
    });
}
</script>
