<template>
    <Link :href="href" class="nav-link" :class="{ 'nav-link--active': active }">
        <svg class="nav-icon" viewBox="0 0 24 24">
            <template v-if="icon === 'settings'"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></template>
            <template v-else-if="icon === 'activity'"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></template>
            <template v-else-if="icon === 'chart'"><path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/></template>
            <template v-else-if="icon === 'user'"><circle cx="12" cy="8" r="5"/><path d="M20 21a8 8 0 0 0-16 0"/></template>
            <template v-else-if="icon === 'users'"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></template>
            <template v-else-if="icon === 'radio'"><path d="M4.9 19.1C1 15.2 1 8.8 4.9 4.9"/><path d="M7.8 16.2c-2.3-2.3-2.3-6.1 0-8.4"/><path d="M16.2 7.8c2.3 2.3 2.3 6.1 0 8.4"/><path d="M19.1 4.9C23 8.8 23 15.1 19.1 19"/><circle cx="12" cy="12" r="2"/></template>
            <template v-else-if="icon === 'home'"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></template>
            <template v-else-if="icon === 'compass'"><circle cx="12" cy="12" r="10"/><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"/></template>
            <template v-else-if="icon === 'search'"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></template>
            <template v-else-if="icon === 'cloud'"><path d="M18 10h-1.26A8 8 0 1 0 9 20h9a5 5 0 0 0 0-10z"/></template>
            <template v-else-if="icon === 'clipboard'"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/></template>
            <template v-else-if="icon === 'map'"><polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/><line x1="8" y1="2" x2="8" y2="18"/><line x1="16" y1="6" x2="16" y2="22"/></template>
            <template v-else-if="icon === 'thermometer'"><path d="M14 14.76V3.5a2.5 2.5 0 0 0-5 0v11.26a4.5 4.5 0 1 0 5 0z"/></template>
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
    color: var(--color-text-secondary);
    cursor: pointer;
    transition: background 0.12s ease-out, color 0.12s ease-out;
    text-decoration: none;
}

.nav-link:hover { background: var(--color-bg); color: var(--color-text-primary); }
.nav-link:focus-visible { outline: 2px solid var(--color-scarlet); outline-offset: -2px; border-radius: 7px; }

.nav-link--active {
    background: var(--color-scarlet-light);
    color: var(--color-scarlet);
    font-weight: 600;
}

.nav-icon {
    width: 18px; height: 18px; flex-shrink: 0;
    stroke: currentColor; fill: none;
    stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
    transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.nav-link--active .nav-icon { transform: scale(1.12); }

@media (prefers-reduced-motion: reduce) {
    .nav-icon { transition: none; }
}
</style>
