import { ref } from 'vue';

const toasts = ref([]);
let nextId = 0;

export function useToast() {
    function add(message, type = 'success', duration = 4000) {
        const id = nextId++;
        toasts.value.push({ id, message, type });
        if (duration > 0) {
            setTimeout(() => dismiss(id), duration);
        }
    }

    function dismiss(id) {
        const idx = toasts.value.findIndex(t => t.id === id);
        if (idx !== -1) toasts.value.splice(idx, 1);
    }

    return {
        toasts,
        dismiss,
        success: (msg, dur) => add(msg, 'success', dur),
        error: (msg, dur) => add(msg, 'error', dur),
        info: (msg, dur) => add(msg, 'info', dur),
    };
}
