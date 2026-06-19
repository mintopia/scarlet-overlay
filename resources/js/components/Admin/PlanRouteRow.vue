<template>
    <div>
        <div
            class="route-row"
            :class="{ 'route-row--dim': !route.is_enabled, 'route-row--dragging': isDragging }"
            :draggable="!readonly"
            role="button"
            tabindex="0"
            :aria-expanded="expanded"
            @dragstart="onDragStart"
            @dragend="onDragEnd"
            @dragover.prevent="$emit('dragover', $event)"
            @drop.prevent="$emit('drop', route)"
            @click="expanded = !expanded"
            @keydown.enter="expanded = !expanded"
            @keydown.space.prevent="expanded = !expanded"
        >
            <span v-if="!readonly" class="route-grip" @mousedown.stop>
                <svg width="10" height="14" viewBox="0 0 10 14" fill="currentColor"><circle cx="3" cy="2" r="1.2"/><circle cx="7" cy="2" r="1.2"/><circle cx="3" cy="7" r="1.2"/><circle cx="7" cy="7" r="1.2"/><circle cx="3" cy="12" r="1.2"/><circle cx="7" cy="12" r="1.2"/></svg>
            </span>
            <span class="route-dot" :style="{ background: color }"></span>
            <svg class="route-chevron" :class="{ 'route-chevron--open': expanded }" viewBox="0 0 24 24" width="14" height="14"><polyline points="6 9 12 15 18 9" stroke="currentColor" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            <span class="route-name">{{ route.name }}</span>
            <span class="route-stat"><span class="route-stat-val">{{ route.distance_nm }}</span> nm</span>
            <span class="route-stat"><span class="route-stat-val">{{ route.waypoints?.length ?? 0 }}</span> wpt</span>
            <button
                class="route-toggle"
                :class="{ 'route-toggle--off': !route.is_enabled }"
                @click.stop="$emit('toggle', route)"
                @keydown.enter.stop
                @keydown.space.stop
                :title="route.is_enabled ? 'Hide route' : 'Show route'"
                :aria-label="route.is_enabled ? 'Hide route' : 'Show route'"
                :aria-pressed="route.is_enabled"
            ></button>
            <button v-if="!readonly" class="route-remove" @click.stop="$emit('remove', route)" @keydown.enter.stop @keydown.space.stop title="Remove route" aria-label="Remove route">&times;</button>
        </div>

        <!-- Expanded waypoints -->
        <div v-if="expanded && route.waypoints?.length" class="waypoints-panel">
            <div class="waypoints-list">
                <div
                    v-for="(wp, i) in route.waypoints"
                    :key="i"
                    class="waypoint-item"
                    role="button"
                    tabindex="0"
                    @click.stop="$emit('focusWaypoint', wp)"
                    @keydown.enter.stop="$emit('focusWaypoint', wp)"
                    @keydown.space.stop.prevent="$emit('focusWaypoint', wp)"
                >
                    <span class="waypoint-dot" :class="{ 'waypoint-dot--endpoint': i === 0 || i === route.waypoints.length - 1 }"></span>
                    <span class="waypoint-badge" :class="badgeClass(i)">{{ badgeLabel(i) }}</span>
                    <span class="waypoint-name">{{ wp.name || `Point ${i + 1}` }}</span>
                    <span class="waypoint-coords">{{ wp.lat.toFixed(4) }}, {{ wp.lng.toFixed(4) }}</span>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { routeColor } from '@/helpers/planColors.js';

const props = defineProps({
    route: { type: Object, required: true },
    groupColorIndex: { type: Number, default: 0 },
    readonly: { type: Boolean, default: false },
});

const emit = defineEmits(['toggle', 'remove', 'focusWaypoint', 'dragstart', 'dragover', 'drop']);

const expanded = ref(false);
const isDragging = ref(false);

function onDragStart(e) {
    isDragging.value = true;
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', String(props.route.id));
    emit('dragstart', props.route);
}

function onDragEnd() {
    isDragging.value = false;
}
const color = routeColor(props.groupColorIndex, props.route.color_index);

function badgeLabel(i) {
    if (i === 0) return 'Start';
    if (i === props.route.waypoints.length - 1) return 'End';
    return 'WPT';
}

function badgeClass(i) {
    if (i === 0) return 'waypoint-badge--start';
    if (i === props.route.waypoints.length - 1) return 'waypoint-badge--end';
    return 'waypoint-badge--wp';
}
</script>

<style scoped>
.route-row {
    display: flex; align-items: center; gap: 8px;
    padding: 9px 16px;
    border-bottom: 1px solid var(--color-border-light);
    cursor: pointer; transition: background 0.1s;
}
.route-row:hover { background: var(--color-bg); }
.route-row--dim { opacity: 0.45; }

.route-row--dragging { opacity: 0.4; }

.route-grip {
    flex-shrink: 0; cursor: grab; color: var(--color-text-dim); opacity: 0.4;
    display: flex; align-items: center; padding: 2px 0;
    transition: opacity 0.1s;
}
.route-row:hover .route-grip { opacity: 0.8; }

.route-dot { width: 9px; height: 9px; border-radius: 50%; flex-shrink: 0; }

.route-chevron {
    color: var(--color-text-dim); flex-shrink: 0;
    transition: transform 0.15s ease-out;
}
.route-chevron--open { transform: rotate(180deg); }

.route-name { font-size: 13px; font-weight: 600; color: var(--color-text-primary); flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.route-stat { font-size: 11px; font-family: var(--font-body); color: var(--color-text-dim); flex-shrink: 0; }
.route-stat-val { color: var(--color-text-secondary); font-weight: 600; }

.route-toggle {
    width: 30px; height: 17px; border-radius: 9px;
    background: var(--color-green); position: relative; flex-shrink: 0;
    cursor: pointer; border: none;
}
.route-toggle::after {
    content: ''; position: absolute; top: 2px; right: 2px;
    width: 13px; height: 13px; border-radius: 50%; background: var(--color-surface);
    box-shadow: var(--shadow-sm);
    transition: right 0.12s, left 0.12s;
}
.route-toggle--off { background: var(--color-border); }
.route-toggle--off::after { right: auto; left: 2px; }

.route-remove {
    width: 26px; height: 26px; border-radius: 6px;
    display: flex; align-items: center; justify-content: center;
    background: none; border: none; cursor: pointer;
    color: var(--color-text-dim); font-size: 16px; flex-shrink: 0;
}
.route-remove:hover { background: var(--color-error-bg); color: var(--color-error); }

.waypoints-panel { padding: 0 16px 10px 36px; border-bottom: 1px solid var(--color-border-light); }
.waypoints-list { display: flex; flex-direction: column; position: relative; padding-left: 16px; }
.waypoints-list::before {
    content: ''; position: absolute; left: 4px; top: 8px; bottom: 8px;
    width: 2px; background: var(--color-border); border-radius: 1px;
}

.waypoint-item {
    display: flex; align-items: center; gap: 8px;
    padding: 5px 0; font-size: 12px; position: relative;
    cursor: pointer;
}
.waypoint-item:hover .waypoint-name { color: var(--color-teal); }

.waypoint-dot {
    width: 10px; height: 10px; border-radius: 50%;
    border: 2px solid var(--color-border); background: var(--color-surface);
    flex-shrink: 0; z-index: 1; margin-left: -20px;
}
.waypoint-dot--endpoint { border-color: var(--color-teal); background: var(--color-teal-bg); }

.waypoint-badge {
    font-size: 9px; font-weight: 700; font-family: var(--font-body);
    text-transform: uppercase; letter-spacing: 0.5px;
    padding: 1px 5px; border-radius: 3px; flex-shrink: 0;
}
.waypoint-badge--start { background: var(--color-teal-bg); color: var(--color-teal); }
.waypoint-badge--end { background: var(--color-blue-bg); color: var(--color-blue); }
.waypoint-badge--wp { background: var(--color-bg); color: var(--color-text-dim); }

.waypoint-name { color: var(--color-text-primary); font-weight: 500; }
.waypoint-coords { color: var(--color-text-dim); font-size: 10px; font-family: var(--font-body); margin-left: auto; }

@media (prefers-reduced-motion: reduce) {
    .route-chevron { transition: none; }
    .route-toggle::after { transition: none; }
}
</style>
