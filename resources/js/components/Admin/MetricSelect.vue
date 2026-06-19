<template>
    <div class="ms-wrap" @keydown.escape="close">
        <input
            :id="inputId"
            v-model="query"
            type="text"
            class="field-input"
            :placeholder="placeholder"
            :aria-label="placeholder"
            autocomplete="off"
            role="combobox"
            aria-autocomplete="list"
            :aria-expanded="open && filtered.length > 0"
            :aria-controls="listId"
            :aria-activedescendant="open && filtered.length ? `${listId}-opt-${highlight}` : undefined"
            @focus="open = true"
            @blur="close"
            @input="open = true; highlight = 0"
            @keydown.down.prevent="move(1)"
            @keydown.up.prevent="move(-1)"
            @keydown.enter.prevent="choose(filtered[highlight])"
        />
        <ul v-if="open && filtered.length" :id="listId" class="ms-list" role="listbox" :aria-label="placeholder">
            <li
                v-for="(m, i) in filtered"
                :id="`${listId}-opt-${i}`"
                :key="m.key"
                class="ms-item"
                :class="{ 'ms-item--active': i === highlight }"
                role="option"
                :aria-selected="i === highlight"
                @mousedown.prevent="choose(m)"
                @mouseenter="highlight = i"
            >
                <span class="ms-item__label">{{ m.label }}</span>
                <span class="ms-item__key">{{ m.key }}</span>
            </li>
        </ul>
    </div>
</template>

<script setup>
import { ref, computed, useId } from 'vue';

const props = defineProps({
    options: { type: Array, default: () => [] }, // [{key, label, group, display_unit}]
    placeholder: { type: String, default: 'Search metrics…' },
});
const emit = defineEmits(['select']);

const uid = useId();
const inputId = `ms-input-${uid}`;
const listId = `ms-list-${uid}`;

const query = ref('');
const open = ref(false);
const highlight = ref(0);
const filtered = computed(() => {
    const q = query.value.trim().toLowerCase();
    const list = q
        ? props.options.filter((m) => m.label.toLowerCase().includes(q) || m.key.toLowerCase().includes(q))
        : props.options;
    return list.slice(0, 50);
});

function move(dir) {
    if (!filtered.value.length) { return; }
    highlight.value = (highlight.value + dir + filtered.value.length) % filtered.value.length;
}

function choose(metric) {
    if (!metric) { return; }
    emit('select', metric);
    query.value = '';
    open.value = false;
}

function close() {
    open.value = false;
}
</script>

<style scoped>
.ms-wrap { position: relative; }
.ms-list {
    position: absolute;
    z-index: 30;
    top: calc(100% + 4px);
    left: 0;
    right: 0;
    max-height: 280px;
    overflow-y: auto;
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: 8px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
    padding: 4px;
}
.ms-item {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    gap: 10px;
    padding: 7px 10px;
    border-radius: 5px;
    cursor: pointer;
}
.ms-item--active { background: var(--color-bg); }
.ms-item__label { font-size: 13px; font-weight: 500; color: var(--color-text-primary); }
.ms-item__key { font-size: 11px; font-family: var(--font-mono, monospace); color: var(--color-text-dim); }
</style>
