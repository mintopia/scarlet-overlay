<template>
    <div class="flex h-screen overflow-hidden bg-bg font-sans text-text-primary">
        <!-- Mobile backdrop -->
        <Transition name="backdrop-fade">
            <div v-if="sidebarOpen" class="fixed inset-0 z-30 bg-black/30 md:hidden" @click="sidebarOpen = false"></div>
        </Transition>

        <!-- Sidebar -->
        <aside
            class="sidebar"
            :class="{ 'sidebar--open': sidebarOpen }"
        >
            <div class="px-5 pt-5 pb-4 border-b border-border-light flex items-center justify-between">
                <div>
                    <div class="text-[22px] font-bold text-scarlet tracking-wide">Scarlet</div>
                    <div class="text-[11px] font-medium text-text-dim mt-0.5">Admin</div>
                </div>
                <button class="md:hidden w-11 h-11 flex items-center justify-center rounded-lg text-text-dim" aria-label="Close menu" @click="sidebarOpen = false">
                    <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>

            <nav class="px-2.5 py-3 flex-1 flex flex-col gap-0.5 overflow-y-auto">
                <div class="nav-label first:pt-1">Boat</div>
                <NavLink href="/admin" icon="home" :active="currentPage === 'Admin/Dashboard'" @click="sidebarOpen = false">Dashboard</NavLink>
                <NavLink href="/admin/settings" icon="settings" :active="currentPage === 'Admin/Settings'" @click="sidebarOpen = false">Settings</NavLink>
                <NavLink href="/admin/journeys" icon="compass" :active="currentPage?.startsWith('Admin/Journey')" @click="sidebarOpen = false">Journeys</NavLink>
                <NavLink href="/admin/log" icon="clipboard" :active="currentPage === 'Admin/Log'" @click="sidebarOpen = false">Log</NavLink>
                <NavLink href="/admin/tracker" icon="activity" :active="currentPage === 'Admin/Tracker'" @click="sidebarOpen = false">Tracker</NavLink>
                <NavLink href="/admin/metrics" icon="chart" :active="currentPage === 'Admin/BoatMetrics'" @click="sidebarOpen = false">Boat Metrics</NavLink>
                <NavLink href="/admin/weather" icon="cloud" :active="currentPage === 'Admin/Weather'" @click="sidebarOpen = false">Weather</NavLink>
                <NavLink href="/admin/explore?metric=battery_voltage" icon="search" :active="currentPage === 'Admin/Explore'" @click="sidebarOpen = false">Explore</NavLink>
                <NavLink href="/admin/stream" icon="radio" :active="currentPage === 'Admin/StreamMonitor'" @click="sidebarOpen = false">Stream Monitor</NavLink>

                <div class="nav-label">Links</div>
                <a href="/overlay" target="_blank" class="nav-link">
                    <svg class="nav-icon" viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                    Broadcast Overlay
                </a>
                <a href="/dashboard" target="_blank" class="nav-link">
                    <svg class="nav-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/></svg>
                    Public Dashboard
                </a>

                <div class="nav-label">Account</div>
                <NavLink href="/admin/profile" icon="user" :active="currentPage === 'Admin/Profile'" @click="sidebarOpen = false">Profile</NavLink>
                <NavLink href="/admin/team" icon="users" :active="currentPage === 'Admin/Team'" @click="sidebarOpen = false">Team</NavLink>
            </nav>

            <div class="px-2.5 py-3.5 border-t border-border-light">
                <div class="flex items-center gap-2.5 px-3 mb-2">
                    <div class="w-7 h-7 rounded-full bg-scarlet-light text-scarlet flex items-center justify-center text-xs font-bold shrink-0">
                        {{ $page.props.auth.user?.initials }}
                    </div>
                    <div>
                        <div class="text-[13px] font-semibold">{{ $page.props.auth.user?.name }}</div>
                        <div class="text-[11px] text-text-dim capitalize">{{ $page.props.auth.user?.role }}</div>
                    </div>
                </div>
                <Link href="/logout" method="post" as="button" class="nav-link text-[13px] text-text-dim w-full">
                    <svg class="nav-icon" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                    Sign out
                </Link>
            </div>
        </aside>

        <!-- Main content -->
        <main class="flex-1 overflow-y-auto px-4 py-5 md:px-10 md:py-8">
            <!-- Mobile header -->
            <div class="flex items-center gap-3 mb-4 md:hidden">
                <button @click="sidebarOpen = true" class="w-11 h-11 flex items-center justify-center rounded-lg border border-border text-text-secondary" aria-label="Open menu">
                    <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                </button>
                <span class="text-[18px] font-bold text-scarlet tracking-wide">Scarlet</span>
            </div>
            <div class="max-w-[820px]">
                <slot />
            </div>
        </main>
    </div>
</template>

<script setup>
import { ref, onUnmounted } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import NavLink from './NavLink.vue';

const currentPage = usePage().component;
const sidebarOpen = ref(false);

const removeListener = router.on('navigate', () => { sidebarOpen.value = false; });

onUnmounted(() => {
    removeListener();
});
</script>

<style scoped>
.sidebar {
    position: fixed;
    inset-block: 0;
    left: 0;
    z-index: 40;
    width: 220px;
    background: var(--color-surface);
    border-right: 1px solid var(--color-border);
    display: flex;
    flex-direction: column;
    flex-shrink: 0;
    transform: translateX(-100%);
    transition: transform 0.2s ease-out;
}

.sidebar--open { transform: translateX(0); }

@media (min-width: 768px) {
    .sidebar {
        position: relative;
        transform: none;
        z-index: auto;
    }
}

.backdrop-fade-enter-active,
.backdrop-fade-leave-active {
    transition: opacity 0.2s ease-out;
}
.backdrop-fade-enter-from,
.backdrop-fade-leave-to {
    opacity: 0;
}

.nav-label {
    font-size: 10px;
    font-weight: 700;
    color: oklch(0.60 0.005 40);
    letter-spacing: 0.04em;
    text-transform: uppercase;
    padding: 16px 12px 6px;
}

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
    transition: background 0.12s ease-out, color 0.12s ease-out;
    text-decoration: none;
}

.nav-link:hover { background: oklch(0.98 0.003 70); color: oklch(0.18 0.005 40); }
.nav-link:focus-visible { outline: 2px solid var(--color-scarlet); outline-offset: -2px; border-radius: 7px; }

.nav-icon {
    width: 18px;
    height: 18px;
    flex-shrink: 0;
    stroke: currentColor;
    fill: none;
    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;
}

@media (prefers-reduced-motion: reduce) {
    .sidebar,
    .backdrop-fade-enter-active,
    .backdrop-fade-leave-active { transition: none; }
}
</style>
