import { onMounted, onUnmounted } from 'vue';

const SEQUENCE = [
    'ArrowUp', 'ArrowUp', 'ArrowDown', 'ArrowDown',
    'ArrowLeft', 'ArrowRight', 'ArrowLeft', 'ArrowRight',
    'b', 'a',
];

const RESET_MS = 2000;

function isEditableTarget(el) {
    if (!el) return false;
    const tag = el.tagName;
    return (
        tag === 'INPUT' ||
        tag === 'TEXTAREA' ||
        tag === 'SELECT' ||
        el.isContentEditable === true ||
        el.closest?.('input, textarea, select, [contenteditable=""], [contenteditable="true"]') != null
    );
}

/**
 * Hidden Konami-code unlock. Calls `onUnlock` when the sequence is entered.
 * Hardened: ignores editable targets, key-repeat, modifier keys, and resets on a
 * wrong key or a short inactivity timeout. Exported state machine is unit-testable.
 */
export function createKonamiMachine(onUnlock, { resetMs = RESET_MS, now = () => Date.now() } = {}) {
    let progress = 0;
    let lastAt = 0;

    function reset() { progress = 0; }

    function handle(event) {
        // Skip while typing, on auto-repeat, or with modifiers held.
        if (isEditableTarget(event.target)) return;
        if (event.repeat) return;
        if (event.ctrlKey || event.metaKey || event.altKey) return;

        const key = event.key.length === 1 ? event.key.toLowerCase() : event.key;
        const t = now();

        if (progress > 0 && t - lastAt > resetMs) progress = 0;
        lastAt = t;

        if (key === SEQUENCE[progress]) {
            progress += 1;
            if (progress === SEQUENCE.length) {
                progress = 0;
                onUnlock();
            }
        } else {
            // Allow a wrong key to be the start of a fresh sequence.
            progress = key === SEQUENCE[0] ? 1 : 0;
        }
    }

    return { handle, reset, get progress() { return progress; } };
}

export function useKonami(onUnlock) {
    const machine = createKonamiMachine(onUnlock);
    const listener = (e) => machine.handle(e);
    onMounted(() => window.addEventListener('keydown', listener));
    onUnmounted(() => window.removeEventListener('keydown', listener));
    return machine;
}
