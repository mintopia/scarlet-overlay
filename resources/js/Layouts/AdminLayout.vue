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
                <NavLink href="/admin/explore" icon="search" :active="currentPage?.startsWith('Admin/Explore')" @click="sidebarOpen = false">Explore</NavLink>
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
            <!-- Theme menu (desktop) -->
            <div class="theme-menu-wrap hidden md:block">
                <button class="theme-toggle" @click="themeMenuOpen = !themeMenuOpen" :title="autoMode ? `Auto: ${theme}` : theme.charAt(0).toUpperCase() + theme.slice(1) + ' mode'">
                    <svg v-if="theme === 'light'" viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                    <svg v-else-if="theme === 'dark'" viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                    <svg v-else viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    <span v-if="autoMode" class="theme-toggle__dot"></span>
                </button>
                <div v-if="themeMenuOpen" class="theme-menu">
                    <button class="theme-menu__item" :class="{ 'theme-menu__item--active': autoMode }" @click="selectAuto">
                        <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        Auto
                    </button>
                    <button class="theme-menu__item" :class="{ 'theme-menu__item--active': !autoMode && theme === 'light' }" @click="selectTheme('light')">
                        <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                        Light
                    </button>
                    <button class="theme-menu__item" :class="{ 'theme-menu__item--active': !autoMode && theme === 'dark' }" @click="selectTheme('dark')">
                        <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                        Dark
                    </button>
                    <button class="theme-menu__item" :class="{ 'theme-menu__item--active': !autoMode && theme === 'night' }" @click="selectTheme('night')">
                        <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        Night Watch
                    </button>
                </div>
            </div>
            <!-- Mobile header -->
            <div class="flex items-center gap-3 mb-4 md:hidden">
                <button @click="sidebarOpen = true" class="w-11 h-11 flex items-center justify-center rounded-lg border border-border text-text-secondary" aria-label="Open menu">
                    <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                </button>
                <span class="text-[18px] font-bold text-scarlet tracking-wide">Scarlet</span>
                <div class="theme-menu-wrap ml-auto md:hidden">
                    <button class="theme-toggle" @click="themeMenuOpen = !themeMenuOpen">
                        <svg v-if="theme === 'light'" viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                        <svg v-else-if="theme === 'dark'" viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                        <svg v-else viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        <span v-if="autoMode" class="theme-toggle__dot"></span>
                    </button>
                    <div v-if="themeMenuOpen" class="theme-menu">
                        <button class="theme-menu__item" :class="{ 'theme-menu__item--active': autoMode }" @click="selectAuto">
                            <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            Auto
                        </button>
                        <button class="theme-menu__item" :class="{ 'theme-menu__item--active': !autoMode && theme === 'light' }" @click="selectTheme('light')">
                            <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                            Light
                        </button>
                        <button class="theme-menu__item" :class="{ 'theme-menu__item--active': !autoMode && theme === 'dark' }" @click="selectTheme('dark')">
                            <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                            Dark
                        </button>
                        <button class="theme-menu__item" :class="{ 'theme-menu__item--active': !autoMode && theme === 'night' }" @click="selectTheme('night')">
                            <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            Night Watch
                        </button>
                    </div>
                </div>
            </div>
            <div :class="wide ? '' : 'max-w-[820px]'">
                <slot />
            </div>
        </main>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import NavLink from './NavLink.vue';
import { useTheme } from '../composables/useTheme.js';

defineProps({
    wide: { type: Boolean, default: false },
});

const currentPage = usePage().component;
const sidebarOpen = ref(false);
const { theme, autoMode, setTheme, enableAuto } = useTheme();
const themeMenuOpen = ref(false);

function selectTheme(t) {
    setTheme(t);
    themeMenuOpen.value = false;
}

function selectAuto() {
    enableAuto();
    themeMenuOpen.value = false;
}

function closeThemeMenu(e) {
    if (!e.target.closest('.theme-menu-wrap')) {
        themeMenuOpen.value = false;
    }
}

const removeListener = router.on('navigate', () => { sidebarOpen.value = false; });

onMounted(() => {
    document.addEventListener('click', closeThemeMenu);
});

onUnmounted(() => {
    removeListener();
    document.removeEventListener('click', closeThemeMenu);
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
    color: var(--color-text-dim);
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
    color: var(--color-text-secondary);
    cursor: pointer;
    transition: background 0.12s ease-out, color 0.12s ease-out;
    text-decoration: none;
}

.nav-link:hover { background: var(--color-bg); color: var(--color-text-primary); }
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

.theme-menu-wrap {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 20;
}

.theme-toggle {
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    color: var(--color-text-secondary);
    cursor: pointer;
    transition: border-color 0.12s ease-out, color 0.12s ease-out;
    -webkit-user-select: none;
    user-select: none;
    position: relative;
}

.theme-toggle:hover {
    border-color: var(--color-text-dim);
    color: var(--color-text-primary);
}

.theme-toggle:focus-visible {
    outline: 2px solid var(--color-scarlet);
    outline-offset: 2px;
}

.theme-toggle__dot {
    position: absolute;
    top: 2px;
    right: 2px;
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: var(--color-green);
}

.theme-menu {
    position: absolute;
    top: calc(100% + 6px);
    right: 0;
    min-width: 150px;
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: 8px;
    padding: 4px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
}

.theme-menu__item {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
    padding: 8px 10px;
    border-radius: 5px;
    font-size: 13px;
    font-weight: 500;
    color: var(--color-text-secondary);
    cursor: pointer;
    border: none;
    background: none;
    transition: background 0.1s ease-out, color 0.1s ease-out;
}

.theme-menu__item:hover {
    background: var(--color-bg);
    color: var(--color-text-primary);
}

.theme-menu__item--active {
    color: var(--color-scarlet);
    font-weight: 600;
}

.theme-menu__item svg {
    flex-shrink: 0;
}

@media (max-width: 767px) {
    .theme-menu-wrap {
        position: relative;
        top: auto;
        right: auto;
    }

    .theme-menu {
        right: auto;
        left: 0;
    }
}
</style>
