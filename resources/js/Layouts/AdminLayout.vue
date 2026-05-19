<template>
    <div class="flex h-screen overflow-hidden bg-bg font-sans text-text-primary">
        <!-- Sidebar -->
        <aside class="w-[220px] bg-surface border-r border-border flex flex-col shrink-0">
            <div class="px-5 pt-5 pb-4 border-b border-border-light">
                <div class="text-[22px] font-bold text-scarlet tracking-wide">Scarlet</div>
                <div class="text-[11px] font-medium text-text-dim mt-0.5">Admin</div>
            </div>

            <nav class="px-2.5 py-3 flex-1 flex flex-col gap-0.5">
                <div class="nav-label first:pt-1">Boat</div>
                <NavLink href="/admin/settings" icon="settings" :active="currentPage === 'Admin/Settings'">Settings</NavLink>
                <NavLink href="/admin/tracker" icon="activity" :active="currentPage === 'Admin/Tracker'">Tracker</NavLink>
                <NavLink href="/admin/metrics" icon="chart" :active="currentPage === 'Admin/BoatMetrics'">Boat Metrics</NavLink>

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
                <NavLink href="/admin/profile" icon="user" :active="currentPage === 'Admin/Profile'">Profile</NavLink>
                <NavLink href="/admin/team" icon="users" :active="currentPage === 'Admin/Team'">Team</NavLink>
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
        <main class="flex-1 overflow-y-auto px-10 py-8">
            <div class="max-w-[820px]">
                <slot />
            </div>
        </main>
    </div>
</template>

<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import NavLink from './NavLink.vue';

const currentPage = usePage().component;
</script>

<style scoped>
.nav-label {
    font-size: 10px;
    font-weight: 700;
    color: oklch(0.60 0.005 40);
    letter-spacing: 0.04em;
    text-transform: uppercase;
    padding: 16px 12px 6px;
}

.nav-link, :deep(.nav-link) {
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
</style>
