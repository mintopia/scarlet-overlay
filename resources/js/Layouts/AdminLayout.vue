<template>
    <div class="admin-shell">
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
        </aside>

        <!-- Content area -->
        <div class="admin-content">
            <!-- Top bar -->
            <header class="topbar">
                <button @click="sidebarOpen = true" class="topbar-hamburger" aria-label="Open menu">
                    <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                </button>

                <nav class="topbar-crumbs" aria-label="Breadcrumb">
                    <template v-for="(crumb, i) in computedBreadcrumbs" :key="i">
                        <span v-if="i > 0" class="topbar-sep">/</span>
                        <Link v-if="crumb.href && i < computedBreadcrumbs.length - 1" :href="crumb.href" class="topbar-crumb topbar-crumb--link">{{ crumb.label }}</Link>
                        <span v-else class="topbar-crumb topbar-crumb--current">{{ crumb.label }}</span>
                    </template>
                </nav>

                <div class="topbar-actions">
                    <div class="theme-menu-wrap">
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

                    <div class="user-menu-wrap">
                        <button class="user-toggle" @click="userMenuOpen = !userMenuOpen">
                            <img :src="gravatarUrl" alt="" class="user-avatar" width="28" height="28" />
                            <span class="user-name">{{ $page.props.auth.user?.name }}</span>
                            <svg class="user-chevron" viewBox="0 0 24 24" width="12" height="12" stroke="currentColor" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                        </button>
                        <div v-if="userMenuOpen" class="user-menu">
                            <div class="user-menu__header">
                                <div class="text-[13px] font-semibold">{{ $page.props.auth.user?.name }}</div>
                                <div class="text-[11px] text-text-dim capitalize">{{ $page.props.auth.user?.role }}</div>
                            </div>
                            <Link href="/admin/profile" class="user-menu__item">
                                <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="5"/><path d="M20 21a8 8 0 0 0-16 0"/></svg>
                                Profile
                            </Link>
                            <Link href="/logout" method="post" as="button" class="user-menu__item">
                                <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                                Sign out
                            </Link>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main content -->
            <main class="admin-main">
                <div :class="wide ? '' : 'max-w-[820px]'">
                    <slot />
                </div>
            </main>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import NavLink from './NavLink.vue';
import { useTheme } from '../composables/useTheme.js';

const props = defineProps({
    wide: { type: Boolean, default: false },
    breadcrumbs: { type: Array, default: null },
});

const page = usePage();
const currentPage = page.component;
const sidebarOpen = ref(false);
const { theme, autoMode, setTheme, enableAuto } = useTheme();
const themeMenuOpen = ref(false);
const userMenuOpen = ref(false);

const breadcrumbMap = {
    'Admin/Dashboard': [{ label: 'Dashboard' }],
    'Admin/Settings': [{ label: 'Settings' }],
    'Admin/Journeys': [{ label: 'Journeys' }],
    'Admin/JourneyCreate': [{ label: 'Journeys', href: '/admin/journeys' }, { label: 'New Journey' }],
    'Admin/JourneyEdit': [{ label: 'Journeys', href: '/admin/journeys' }, { label: 'Edit' }],
    'Admin/JourneyImport': [{ label: 'Journeys', href: '/admin/journeys' }, { label: 'Import' }],
    'Admin/Log': [{ label: 'Log' }],
    'Admin/Tracker': [{ label: 'Tracker' }],
    'Admin/BoatMetrics': [{ label: 'Boat Metrics' }],
    'Admin/Weather': [{ label: 'Weather' }],
    'Admin/ExploreDashboard': [{ label: 'Explore' }],
    'Admin/Explore': [{ label: 'Explore', href: '/admin/explore' }],
    'Admin/StreamMonitor': [{ label: 'Stream Monitor' }],
    'Admin/Profile': [{ label: 'Profile' }],
    'Admin/Team': [{ label: 'Team' }],
};

const computedBreadcrumbs = computed(() => {
    if (props.breadcrumbs) return props.breadcrumbs;
    return breadcrumbMap[currentPage] || [{ label: currentPage?.replace('Admin/', '') || 'Admin' }];
});

const gravatarUrl = computed(() => {
    return page.props.auth.user?.gravatar_url || '';
});

function selectTheme(t) {
    setTheme(t);
    themeMenuOpen.value = false;
}

function selectAuto() {
    enableAuto();
    themeMenuOpen.value = false;
}

function closeMenus(e) {
    if (!e.target.closest('.theme-menu-wrap')) themeMenuOpen.value = false;
    if (!e.target.closest('.user-menu-wrap')) userMenuOpen.value = false;
}

const removeListener = router.on('navigate', () => {
    sidebarOpen.value = false;
    themeMenuOpen.value = false;
    userMenuOpen.value = false;
});

onMounted(() => {
    document.addEventListener('click', closeMenus);
});

onUnmounted(() => {
    removeListener();
    document.removeEventListener('click', closeMenus);
});
</script>

<style scoped>
/* Shell */
.admin-shell {
    display: flex;
    height: 100vh;
    overflow: hidden;
    background: var(--color-bg);
    font-family: var(--font-sans);
    color: var(--color-text-primary);
}

.admin-content {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    min-width: 0;
}

.admin-main {
    flex: 1;
    overflow-y: auto;
    padding: 20px 16px;
}

@media (min-width: 768px) {
    .admin-main { padding: 32px 40px; }
}

/* Sidebar */
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

/* Top bar */
.topbar {
    display: flex;
    align-items: center;
    height: 52px;
    padding: 0 16px;
    border-bottom: 1px solid var(--color-border-light);
    background: var(--color-surface);
    flex-shrink: 0;
    gap: 12px;
}

@media (min-width: 768px) {
    .topbar { padding: 0 40px; }
}

.topbar-hamburger {
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 7px;
    border: 1px solid var(--color-border);
    color: var(--color-text-secondary);
    cursor: pointer;
    background: none;
    flex-shrink: 0;
}

@media (min-width: 768px) {
    .topbar-hamburger { display: none; }
}

.topbar-crumbs {
    display: flex;
    align-items: center;
    gap: 8px;
    flex: 1;
    min-width: 0;
}

.topbar-sep {
    color: var(--color-border);
    font-size: 13px;
    flex-shrink: 0;
}

.topbar-crumb { font-size: 14px; white-space: nowrap; }

.topbar-crumb--link {
    color: var(--color-text-secondary);
    text-decoration: none;
    font-weight: 500;
}

.topbar-crumb--link:hover { color: var(--color-text-primary); }

.topbar-crumb--current {
    font-weight: 600;
    color: var(--color-text-primary);
    overflow: hidden;
    text-overflow: ellipsis;
}

.topbar-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
}

/* Theme menu */
.theme-menu-wrap { position: relative; }

.theme-toggle {
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: var(--color-bg);
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
    z-index: 50;
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

.theme-menu__item svg { flex-shrink: 0; }

/* User menu */
.user-menu-wrap { position: relative; }

.user-toggle {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    background: none;
    border: 1px solid transparent;
    padding: 4px 8px 4px 4px;
    border-radius: 8px;
    transition: background 0.12s ease-out, border-color 0.12s ease-out;
    color: var(--color-text-secondary);
}

.user-toggle:hover {
    background: var(--color-bg);
    border-color: var(--color-border);
}

.user-toggle:focus-visible {
    outline: 2px solid var(--color-scarlet);
    outline-offset: 2px;
}

.user-avatar {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    flex-shrink: 0;
}

.user-name {
    font-size: 13px;
    font-weight: 500;
    color: var(--color-text-primary);
    display: none;
}

@media (min-width: 768px) {
    .user-name { display: inline; }
}

.user-chevron {
    flex-shrink: 0;
    display: none;
}

@media (min-width: 768px) {
    .user-chevron { display: block; }
}

.user-menu {
    position: absolute;
    top: calc(100% + 6px);
    right: 0;
    min-width: 180px;
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: 8px;
    padding: 4px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
    z-index: 50;
}

.user-menu__header {
    padding: 10px 10px 8px;
    border-bottom: 1px solid var(--color-border-light);
    margin-bottom: 4px;
}

.user-menu__item {
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
    text-decoration: none;
    transition: background 0.1s ease-out, color 0.1s ease-out;
}

.user-menu__item:hover {
    background: var(--color-bg);
    color: var(--color-text-primary);
}

.user-menu__item svg { flex-shrink: 0; }

/* Reduced motion */
@media (prefers-reduced-motion: reduce) {
    .sidebar,
    .backdrop-fade-enter-active,
    .backdrop-fade-leave-active { transition: none; }
}
</style>
