<template>
    <Link :href="href" class="nav-link" :class="{ 'nav-link--active': active }">
        <svg class="nav-icon" viewBox="0 0 24 24">
            <template v-if="icon === 'settings'"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></template>
            <template v-else-if="icon === 'activity'"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></template>
            <template v-else-if="icon === 'chart'"><path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/></template>
            <template v-else-if="icon === 'user'"><circle cx="12" cy="8" r="5"/><path d="M20 21a8 8 0 0 0-16 0"/></template>
            <template v-else-if="icon === 'users'"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></template>
        </svg>
        <slot />
    </Link>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    href: String,
    icon: String,
    active: Boolean,
});
</script>

<style scoped>
.nav-link {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border-radius: 7px;
    font-size: 14px;
    font-weight: 500;
    color: oklch(0.45 0.005 40);
    cursor: pointer;
    transition: all 0.12s ease-out;
    text-decoration: none;
}

.nav-link:hover { background: oklch(0.98 0.003 70); color: oklch(0.18 0.005 40); }

.nav-link--active {
    background: oklch(0.54 0.22 27 / 0.06);
    color: oklch(0.54 0.22 27);
    font-weight: 600;
}

.nav-icon {
    width: 18px; height: 18px; flex-shrink: 0;
    stroke: currentColor; fill: none;
    stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
}
</style>
