<template>
    <div class="panel">
        <!-- Group header -->
        <div class="group-header">
            <span class="group-swatch" :style="{ background: swatchColor }"></span>
            <span v-if="!editing" class="group-name" @dblclick="startEditing">{{ group.name }}</span>
            <input
                v-else
                ref="nameInput"
                v-model="editName"
                class="group-name-input"
                @keydown.enter="saveName"
                @blur="saveName"
            />
            <span class="group-count">{{ group.routes.length }}</span>
            <button
                v-if="group.routes.length"
                class="group-toggle"
                :class="{ 'group-toggle--off': !allVisible }"
                @click="$emit('toggleGroup', group)"
                :title="allVisible ? 'Hide all routes' : 'Show all routes'"
            ></button>
            <button v-if="!readonly" class="group-action" @click="startEditing" title="Edit group">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M17 3a2.83 2.83 0 114 4L7.5 20.5 2 22l1.5-5.5z"/></svg>
            </button>
            <button v-if="!readonly" class="group-action group-action--danger" @click="$emit('delete', group)" title="Delete group">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
            </button>
        </div>

        <!-- Route rows -->
        <PlanRouteRow
            v-for="route in orderedRoutes"
            :key="route.id"
            :route="route"
            :group-color-index="group.color_index"
            :readonly="readonly"
            :class="{ 'route-drop-above': dragOverId === route.id }"
            @toggle="$emit('toggleRoute', $event)"
            @remove="$emit('removeRoute', $event)"
            @focus-waypoint="$emit('focusWaypoint', $event)"
            @dragstart="onRouteDragStart"
            @dragover="onRouteDragOver(route, $event)"
            @drop="onRouteDrop(route)"
        />

        <!-- Dropzone -->
        <div
            v-if="!readonly"
            class="group-dropzone"
            :class="{ 'group-dropzone--active': dragging }"
            @dragover.prevent="dragging = true"
            @dragleave="dragging = false"
            @drop.prevent="handleDrop"
            @click="fileInput?.click()"
        >
            <input ref="fileInput" type="file" accept=".gpx" multiple hidden @change="handleFileSelect" />
            <div v-if="uploading" class="dropzone-uploading">
                <span class="dropzone-spinner"></span> Uploading...
            </div>
            <template v-else>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="dropzone-icon"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                <span class="dropzone-text">Drop GPX files or click to browse</span>
            </template>
        </div>

        <!-- Upload errors -->
        <div v-if="uploadErrors.length" class="group-errors">
            <div v-for="(err, i) in uploadErrors" :key="i" class="group-error">
                {{ err }}
                <button @click="uploadErrors.splice(i, 1)" class="group-error-dismiss">&times;</button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, nextTick, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import PlanRouteRow from './PlanRouteRow.vue';
import { groupColor } from '@/helpers/planColors.js';

const props = defineProps({
    group: { type: Object, required: true },
    readonly: { type: Boolean, default: false },
});

defineEmits(['delete', 'toggleGroup', 'toggleRoute', 'removeRoute', 'focusWaypoint']);

const swatchColor = groupColor(props.group.color_index);
const allVisible = computed(() => props.group.routes.length > 0 && props.group.routes.every(r => r.is_enabled));
const editing = ref(false);
const editName = ref(props.group.name);
const nameInput = ref(null);
const fileInput = ref(null);
const dragging = ref(false);
const uploading = ref(false);
const uploadErrors = ref([]);

const localOrder = ref(props.group.routes.map(r => r.id));
watch(() => props.group.routes, (routes) => {
    localOrder.value = routes.map(r => r.id);
}, { deep: true });

const orderedRoutes = computed(() => {
    const byId = Object.fromEntries(props.group.routes.map(r => [r.id, r]));
    return localOrder.value.map(id => byId[id]).filter(Boolean);
});

let dragSourceRoute = null;
const dragOverId = ref(null);

function onRouteDragStart(route) {
    dragSourceRoute = route;
}

function onRouteDragOver(route) {
    if (!dragSourceRoute || dragSourceRoute.id === route.id) return;
    dragOverId.value = route.id;
}

function onRouteDrop(targetRoute) {
    dragOverId.value = null;
    if (!dragSourceRoute || dragSourceRoute.id === targetRoute.id) return;

    const order = [...localOrder.value];
    const fromIdx = order.indexOf(dragSourceRoute.id);
    const toIdx = order.indexOf(targetRoute.id);
    if (fromIdx === -1 || toIdx === -1) return;

    order.splice(fromIdx, 1);
    order.splice(toIdx, 0, dragSourceRoute.id);
    localOrder.value = order;
    dragSourceRoute = null;

    router.put(`/admin/planner/groups/${props.group.id}/reorder`, { route_ids: order }, { preserveScroll: true });
}

function startEditing() {
    if (props.readonly) return;
    editName.value = props.group.name;
    editing.value = true;
    nextTick(() => nameInput.value?.select());
}

function saveName() {
    editing.value = false;
    if (editName.value.trim() && editName.value !== props.group.name) {
        router.put(`/admin/planner/groups/${props.group.id}`, { name: editName.value.trim() }, { preserveScroll: true });
    }
}

function uploadFiles(files) {
    const gpxFiles = Array.from(files).filter(f => f.name.toLowerCase().endsWith('.gpx'));
    if (!gpxFiles.length) return;

    uploading.value = true;
    uploadErrors.value = [];

    const formData = new FormData();
    gpxFiles.forEach(f => formData.append('gpx_files[]', f));

    router.post(`/admin/planner/groups/${props.group.id}/routes`, formData, {
        preserveScroll: true,
        onSuccess: () => { uploading.value = false; },
        onError: (errors) => {
            uploading.value = false;
            if (errors.gpx_files) {
                uploadErrors.value = Array.isArray(errors.gpx_files) ? errors.gpx_files : [errors.gpx_files];
            }
        },
    });
}

function handleDrop(e) {
    dragging.value = false;
    if (!e.dataTransfer.files.length) return;
    uploadFiles(e.dataTransfer.files);
}

function handleFileSelect(e) {
    uploadFiles(e.target.files);
    e.target.value = '';
}
</script>

<style scoped>
.group-header {
    display: flex; align-items: center; gap: 10px;
    padding: 14px 16px; border-bottom: 1px solid var(--color-border-light);
}
.group-swatch { width: 16px; height: 16px; border-radius: 5px; flex-shrink: 0; }
.group-name { font-size: 15px; font-weight: 700; color: var(--color-text-primary); flex: 1; cursor: default; }
.group-name-input {
    font-size: 15px; font-weight: 700; color: var(--color-text-primary); flex: 1;
    background: var(--color-bg); border: 1px solid var(--color-scarlet);
    border-radius: 5px; padding: 2px 8px; outline: none;
    box-shadow: 0 0 0 2px var(--color-scarlet-light);
}
.group-count {
    font-size: 12px; color: var(--color-text-dim); font-family: var(--font-body);
    background: var(--color-bg); padding: 2px 10px; border-radius: 10px;
}
.group-toggle {
    width: 34px; height: 18px; border-radius: 9px;
    background: var(--color-green); position: relative; flex-shrink: 0;
    cursor: pointer; border: none;
}
.group-toggle::after {
    content: ''; position: absolute; top: 2px; right: 2px;
    width: 14px; height: 14px; border-radius: 50%; background: #fff;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    transition: right 0.12s, left 0.12s;
}
.group-toggle--off { background: var(--color-border); }
.group-toggle--off::after { right: auto; left: 2px; }

.group-action {
    width: 28px; height: 28px; border-radius: 6px;
    display: flex; align-items: center; justify-content: center;
    background: none; border: 1px solid transparent; cursor: pointer; color: var(--color-text-dim);
}
.group-action:hover { background: var(--color-bg); border-color: var(--color-border); color: var(--color-text-secondary); }
.group-action--danger:hover { background: var(--color-error-bg); color: var(--color-error); border-color: transparent; }

.group-dropzone {
    display: flex; align-items: center; justify-content: center; gap: 8px;
    margin: 10px 12px 12px; padding: 14px;
    border: 2px dashed var(--color-border);
    border-radius: 12px; cursor: pointer;
    transition: border-color 0.15s, background 0.15s;
    color: var(--color-text-dim);
}
.group-dropzone:hover, .group-dropzone--active {
    border-color: var(--color-teal); background: var(--color-teal-bg);
}
.dropzone-icon { color: var(--color-teal); flex-shrink: 0; }
.dropzone-text { font-size: 13px; font-family: var(--font-body); font-weight: 600; }
.dropzone-uploading { font-size: 13px; font-family: var(--font-body); color: var(--color-teal); display: flex; align-items: center; gap: 8px; }
.dropzone-spinner {
    width: 16px; height: 16px; border: 2px solid var(--color-border);
    border-top-color: var(--color-teal); border-radius: 50%;
    animation: spin 0.6s linear infinite;
}

.route-drop-above { box-shadow: inset 0 2px 0 0 var(--color-teal); }

.group-errors { padding: 0 12px 12px; }
.group-error {
    display: flex; align-items: center; justify-content: space-between;
    padding: 6px 10px; border-radius: 6px;
    background: var(--color-error-bg); color: var(--color-error);
    font-size: 12px; font-family: var(--font-body); margin-top: 4px;
}
.group-error-dismiss { background: none; border: none; color: var(--color-error); font-size: 16px; cursor: pointer; padding: 0 4px; }

@keyframes spin { to { transform: rotate(360deg); } }

@media (prefers-reduced-motion: reduce) {
    .dropzone-spinner { animation: none; }
}
</style>
