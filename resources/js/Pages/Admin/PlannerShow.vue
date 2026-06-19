<template>
    <AdminLayout :breadcrumbs="[{ label: plan.title }]">
        <Head :title="`Planner — ${plan.title}`" />

        <!-- Page header -->
        <div class="flex items-baseline justify-between mb-1">
            <div class="flex items-baseline gap-3">
                <h1
                    v-if="!editingTitle"
                    class="font-sans text-2xl font-extrabold tracking-tight cursor-pointer"
                    role="button"
                    tabindex="0"
                    aria-label="Edit plan title"
                    @click="startEditTitle"
                    @keydown.enter="startEditTitle"
                    @keydown.space.prevent="startEditTitle"
                >{{ plan.title }}</h1>
                <input
                    v-else
                    ref="titleInput"
                    v-model="titleDraft"
                    class="font-sans text-2xl font-extrabold tracking-tight bg-bg border border-scarlet rounded-lg px-2 py-0 outline-none"
                    style="box-shadow: 0 0 0 2px var(--color-scarlet-light)"
                    @keydown.enter="saveTitle"
                    @blur="saveTitle"
                />
                <span v-if="plan.share_token" class="inline-flex items-center gap-1 text-[10px] font-body font-bold uppercase tracking-wide text-teal">
                    <span class="inline-block w-1.5 h-1.5 rounded-full bg-teal" aria-hidden="true"></span>
                    Shared
                </span>
            </div>
            <div class="flex items-center gap-2">
                <button v-if="plan.share_token" class="btn btn--ghost text-[12px]" @click="copyShareUrl">Copy Link</button>
                <button class="btn btn--ghost" @click="toggleShare">
                    {{ plan.share_token ? 'Unshare' : 'Share' }}
                </button>
                <button class="btn btn--primary" @click="createGroup">+ New Group</button>
            </div>
        </div>
        <div class="text-[13px] font-body text-text-dim mb-4">
            {{ plan.groups.length }} {{ plan.groups.length === 1 ? 'group' : 'groups' }}
            &middot;
            {{ totalRoutes }} {{ totalRoutes === 1 ? 'route' : 'routes' }}
            &middot;
            Created {{ formatDate(plan.created_at) }}
        </div>

        <!-- Map -->
        <div class="map-section" ref="mapEl">
            <MapLayerControl
                v-model:base="base"
                v-model:seamark="seamark"
                v-model:contours="contours"
            />
        </div>

        <!-- Empty state -->
        <div v-if="!plan.groups.length" class="panel p-8 text-center mt-5">
            <div class="text-[15px] font-sans font-semibold text-text-primary mb-2">Add a group to get started</div>
            <div class="text-[13px] font-body text-text-dim mb-4">Groups let you organise routes by person or category.</div>
            <button class="btn btn--primary" @click="createGroup">+ New Group</button>
        </div>

        <!-- Groups grid -->
        <div v-else class="groups-grid mt-5">
            <PlanGroupCard
                v-for="group in plan.groups"
                :key="group.id"
                :group="group"
                @delete="confirmDeleteGroup"
                @toggle-group="toggleGroup"
                @toggle-route="toggleRoute"
                @remove-route="confirmDeleteRoute"
                @focus-waypoint="panToWaypoint"
            />
        </div>

        <!-- Delete confirmation modal -->
        <Transition name="modal">
            <div
                v-if="deleteModal"
                class="fixed inset-0 z-[10000] flex items-center justify-center bg-black/30"
                role="dialog"
                aria-modal="true"
                aria-labelledby="delete-modal-title"
                @click.self="deleteModal = null"
                @keydown.escape="deleteModal = null"
            >
                <div
                    ref="deleteModalRef"
                    tabindex="-1"
                    class="modal-card bg-surface border border-border rounded-2xl shadow-lg p-6 w-full max-w-sm"
                    @keydown.tab="trapFocus($event, deleteModalRef)"
                >
                    <div id="delete-modal-title" class="text-[16px] font-sans font-bold text-text-primary mb-2">{{ deleteModal.title }}</div>
                    <div class="text-[13px] font-body text-text-secondary mb-5">{{ deleteModal.message }}</div>
                    <div class="flex justify-end gap-2">
                        <button class="btn btn--ghost" @click="deleteModal = null">Cancel</button>
                        <button class="btn btn--danger" @click="deleteModal.action()">Delete</button>
                    </div>
                </div>
            </div>
        </Transition>
    </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PlanGroupCard from '@/components/Admin/PlanGroupCard.vue';
import MapLayerControl from '@/components/MapLayerControl.vue';
import { routeColor, routeColorDim } from '@/helpers/planColors.js';
import { useMapLayers } from '@/composables/useMapLayers.js';
import { useToast } from '@/composables/useToast.js';

const props = defineProps({
    plan: { type: Object, required: true },
    defaultCenter: { type: Array, default: () => [37.1028, -8.6740] },
});

const toast = useToast();
const mapEl = ref(null);
const editingTitle = ref(false);
const titleDraft = ref(props.plan.title);
const titleInput = ref(null);
const deleteModal = ref(null);
const deleteModalRef = ref(null);
const { base, seamark, contours, attach } = useMapLayers();

let map = null;
let routeLayers = {};

const totalRoutes = computed(() => props.plan.groups.reduce((sum, g) => sum + g.routes.length, 0));

function buildRouteLayers() {
    Object.values(routeLayers).forEach(layers => {
        layers.forEach(l => map.removeLayer(l));
    });
    routeLayers = {};

    const allPoints = [];

    for (const group of props.plan.groups) {
        for (const route of group.routes) {
            const color = routeColor(group.color_index, route.color_index);
            const dimColor = routeColorDim(group.color_index, route.color_index);
            const layers = [];

            const points = route.track_points?.length ? route.track_points : (route.waypoints || []).map(w => [w.lat, w.lng]);
            if (!points.length) continue;

            if (route.is_enabled) {
                allPoints.push(...points);
            }

            const polyline = L.polyline(points, {
                color: route.is_enabled ? color : dimColor,
                weight: route.is_enabled ? 3 : 2,
                opacity: route.is_enabled ? 0.85 : 0.3,
                dashArray: route.is_enabled ? null : '6 4',
            }).addTo(map);
            layers.push(polyline);

            if (route.is_enabled && points.length >= 2) {
                const startPin = L.circleMarker(points[0], {
                    radius: 7, color: '#fff', fillColor: color, fillOpacity: 1, weight: 2.5,
                }).addTo(map);
                layers.push(startPin);

                const endPin = L.circleMarker(points[points.length - 1], {
                    radius: 6, color: '#fff', fillColor: color, fillOpacity: 1, weight: 2,
                }).addTo(map);
                layers.push(endPin);

                if (route.waypoints?.length) {
                    const startWp = route.waypoints[0];
                    const endWp = route.waypoints[route.waypoints.length - 1];
                    if (startWp.name) {
                        startPin.bindTooltip(startWp.name, { direction: 'top', offset: [0, -8], className: 'plan-tooltip' });
                    }
                    if (endWp.name) {
                        endPin.bindTooltip(endWp.name, { direction: 'top', offset: [0, -8], className: 'plan-tooltip' });
                    }

                    route.waypoints.slice(1, -1).forEach(wp => {
                        if (!wp.name) return;
                        const marker = L.circleMarker([wp.lat, wp.lng], {
                            radius: 4, color: color, fillColor: color, fillOpacity: 0.3, weight: 1.5,
                        }).bindTooltip(wp.name, { direction: 'top', offset: [0, -6] }).addTo(map);
                        layers.push(marker);
                    });
                }
            }

            routeLayers[route.id] = layers;
        }
    }

    if (allPoints.length) {
        map.fitBounds(L.latLngBounds(allPoints), { padding: [40, 40] });
    }
}

function startEditTitle() {
    titleDraft.value = props.plan.title;
    editingTitle.value = true;
    nextTick(() => titleInput.value?.select());
}

function saveTitle() {
    editingTitle.value = false;
    if (titleDraft.value.trim() && titleDraft.value !== props.plan.title) {
        router.put(`/admin/planner/${props.plan.slug}`, { title: titleDraft.value.trim() }, { preserveScroll: true });
    }
}

function toggleShare() {
    if (props.plan.share_token) {
        router.delete(`/admin/planner/${props.plan.slug}/share`, { preserveScroll: true });
    } else {
        router.post(`/admin/planner/${props.plan.slug}/share`, {}, { preserveScroll: true });
    }
}

async function copyShareUrl() {
    const url = `${window.location.origin}/planner/${props.plan.slug}?token=${props.plan.share_token}`;
    try {
        await navigator.clipboard.writeText(url);
        toast.success('Share link copied to clipboard');
    } catch {
        toast.error(`Couldn't copy automatically. Copy this link: ${url}`);
    }
}

function createGroup() {
    router.post(`/admin/planner/${props.plan.slug}/groups`, { name: 'New Group' }, {
        preserveScroll: true,
    });
}

function toggleGroup(group) {
    const allVisible = group.routes.every(r => r.is_enabled);
    const newState = !allVisible;
    group.routes.forEach(route => {
        if (route.is_enabled !== newState) {
            router.put(`/admin/planner/routes/${route.id}`, { is_enabled: newState }, { preserveScroll: true });
        }
    });
}

function toggleRoute(route) {
    router.put(`/admin/planner/routes/${route.id}`, { is_enabled: !route.is_enabled }, { preserveScroll: true });
}

function confirmDeleteGroup(group) {
    deleteModal.value = {
        title: `Delete "${group.name}"?`,
        message: `This will remove the group and all ${group.routes.length} route${group.routes.length === 1 ? '' : 's'} in it.`,
        action: () => {
            router.delete(`/admin/planner/groups/${group.id}`, { preserveScroll: true });
            deleteModal.value = null;
        },
    };
}

function confirmDeleteRoute(route) {
    deleteModal.value = {
        title: `Remove "${route.name}"?`,
        message: 'This route will be permanently deleted.',
        action: () => {
            router.delete(`/admin/planner/routes/${route.id}`, { preserveScroll: true });
            deleteModal.value = null;
        },
    };
}

function panToWaypoint(wp) {
    if (map && wp.lat && wp.lng) {
        map.setView([wp.lat, wp.lng], Math.max(map.getZoom(), 12));
    }
}

function formatDate(iso) {
    return new Date(iso).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
}

onMounted(() => {
    if (!mapEl.value) return;
    map = L.map(mapEl.value, { zoomControl: true, attributionControl: false }).setView(props.defaultCenter, 8);
    attach(map);
    buildRouteLayers();
});

onUnmounted(() => {
    map?.remove();
    map = null;
});

watch(() => props.plan, () => {
    nextTick(buildRouteLayers);
}, { deep: true });

watch(deleteModal, (val) => {
    if (val) {
        nextTick(() => deleteModalRef.value?.focus());
    }
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
</script>

<style scoped>
.map-section {
    border-radius: 16px; overflow: hidden;
    border: 1px solid var(--color-border); box-shadow: var(--shadow-sm);
    position: relative; height: 60vh; min-height: 400px;
}

.groups-grid {
    display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px;
}
@media (max-width: 768px) {
    .groups-grid { grid-template-columns: 1fr; }
}
</style>

<style>
.plan-tooltip {
    background: var(--color-surface); color: var(--color-text-primary);
    border: 1px solid var(--color-border); border-radius: 4px;
    font-size: 11px; font-weight: 700; font-family: var(--font-body);
    padding: 2px 8px; box-shadow: 0 1px 4px rgba(0,0,0,0.12);
}
.plan-tooltip::before { border-top-color: var(--color-border) !important; }
</style>
