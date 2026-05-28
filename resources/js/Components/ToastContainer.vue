<template>
    <Teleport to="body">
        <div class="toast-stack" aria-live="polite" aria-atomic="false">
            <TransitionGroup name="toast-slide">
                <div v-for="t in toasts" :key="t.id" :class="['toast', `toast--${t.type}`]" role="status">
                    <svg v-if="t.type === 'success'" class="toast-icon" viewBox="0 0 16 16" aria-hidden="true">
                        <polyline points="3 8.5 6.5 12 13 4" />
                    </svg>
                    <svg v-else-if="t.type === 'error'" class="toast-icon" viewBox="0 0 16 16" aria-hidden="true">
                        <line x1="4" y1="4" x2="12" y2="12" /><line x1="12" y1="4" x2="4" y2="12" />
                    </svg>
                    <svg v-else class="toast-icon" viewBox="0 0 16 16" aria-hidden="true">
                        <circle cx="8" cy="8" r="6" /><line x1="8" y1="7" x2="8" y2="11" /><circle cx="8" cy="5" r="0.7" fill="currentColor" stroke="none" />
                    </svg>
                    <span class="toast-text">{{ t.message }}</span>
                    <button class="toast-dismiss" @click="dismiss(t.id)" aria-label="Dismiss">
                        <svg viewBox="0 0 10 10" width="10" height="10" aria-hidden="true"><line x1="2" y1="2" x2="8" y2="8" /><line x1="8" y1="2" x2="2" y2="8" /></svg>
                    </button>
                </div>
            </TransitionGroup>
        </div>
    </Teleport>
</template>

<script setup>
import { useToast } from '@/composables/useToast.js';

const { toasts, dismiss } = useToast();
</script>

<style scoped>
.toast-stack {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 200;
    display: flex;
    flex-direction: column-reverse;
    gap: 8px;
    pointer-events: none;
}

.toast {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: 8px;
    box-shadow: var(--shadow-sm), 0 4px 20px oklch(0.0 0.0 0 / 0.14);
    font-size: 13px;
    font-weight: 500;
    font-family: var(--font-body);
    color: var(--color-text-primary);
    pointer-events: auto;
    max-width: 360px;
}

.toast-icon {
    width: 16px;
    height: 16px;
    flex-shrink: 0;
    fill: none;
    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.toast--success .toast-icon { stroke: var(--color-green); color: var(--color-green); }
.toast--error .toast-icon { stroke: var(--color-error); color: var(--color-error); }
.toast--info .toast-icon { stroke: var(--color-teal); color: var(--color-teal); }

.toast-text {
    flex: 1;
    line-height: 1.4;
}

.toast-dismiss {
    flex-shrink: 0;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
    background: none;
    border: none;
    color: var(--color-text-dim);
    cursor: pointer;
    transition: color 0.12s;
    padding: 0;
}

.toast-dismiss:hover { color: var(--color-text-primary); }

.toast-dismiss svg {
    stroke: currentColor;
    stroke-width: 1.5;
    stroke-linecap: round;
}

.toast-slide-enter-active {
    transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1),
                opacity 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

.toast-slide-leave-active {
    transition: transform 0.2s ease-in, opacity 0.2s ease-in;
    position: absolute;
    right: 0;
}

.toast-slide-enter-from {
    transform: translateX(calc(100% + 20px));
    opacity: 0;
}

.toast-slide-leave-to {
    transform: translateX(30%);
    opacity: 0;
}

.toast-slide-move {
    transition: transform 0.25s ease-out;
}

@media (prefers-reduced-motion: reduce) {
    .toast-slide-enter-active,
    .toast-slide-leave-active,
    .toast-slide-move {
        transition: none;
    }
}
</style>
