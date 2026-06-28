<template>
  <div class="app-shell">
    <!-- Mobile drawer backdrop -->
    <div v-if="mobileNavOpen" class="sidebar-backdrop" @click="mobileNavOpen = false" />

    <!-- Sidebar -->
    <aside class="sidebar" :class="{ 'mobile-open': mobileNavOpen }">

      <!-- Workspace header — the close × tucks into this row on mobile (matches
           design); there is no separate close row. -->
      <div class="workspace-header" @click="showWorkspaceSwitcher = true; mobileNavOpen = false">
        <div class="ws-logo" :style="{ background: workspace?.color ?? 'var(--accent)' }">
          {{ workspaceLetter }}
        </div>
        <span class="ws-name">{{ workspace?.name ?? 'My Workspace' }}</span>
        <ChevronIcon class="ws-chev" />
        <button class="drawer-close" type="button" aria-label="Close menu" @click.stop="mobileNavOpen = false">
          <CloseIcon style="width:16px;height:16px" />
        </button>
      </div>

      <!-- Nav -->
      <nav class="sidebar-nav">

        <!-- Top-level -->
        <NavItem :href="route('dashboard')" :active="isActive('dashboard')">
          <HomeIcon class="nav-icon" /> Dashboard
        </NavItem>
        <NavItem :href="route('inbox')" :active="isActive('inbox')">
          <InboxIcon class="nav-icon" /> Inbox
          <span v-if="inboxCount > 0" class="nav-badge">{{ inboxCount }}</span>
        </NavItem>
        <NavItem :href="route('my-tasks')" :active="isActive('my-tasks')">
          <UserIcon class="nav-icon" /> My tasks
          <span v-if="myTasksCount > 0" class="nav-badge">{{ myTasksCount }}</span>
        </NavItem>

        <!-- Projects -->
        <div class="nav-section-label">
          <span>Projects</span>
          <button v-if="workspace && canManageWorkspace" class="section-add-btn" title="New project" @click="showNewProject = true; mobileNavOpen = false">
            <PlusIcon style="width:13px;height:13px" />
          </button>
        </div>

        <button v-if="!workspace" type="button" class="no-workspace-btn" @click="showWorkspaceSwitcher = true; mobileNavOpen = false">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
          </svg>
          Create a workspace first
        </button>

        <div
          v-for="p in projects"
          :key="p.id"
          class="project-row"
          :class="{ active: isProjectActive(p.uuid) }"
        >
          <Link :href="route('projects.show', p.uuid)" class="project-link">
            <span class="project-dot" :style="{ background: p.color }" />
            <span class="truncate">{{ p.name }}</span>
          </Link>
          <Link
            :href="route('projects.settings', p.uuid)"
            class="project-settings-btn"
            :class="{ visible: isProjectSettingsActive(p.uuid) }"
            title="Project settings"
            aria-label="Project settings"
          >
            <SettingsIcon style="width:14px;height:14px" />
          </Link>
        </div>

        <!-- Workspace section -->
        <div class="nav-section-label" style="margin-top:8px">
          <span>Workspace</span>
        </div>
        <NavItem :href="route('sprints.index')" :active="isActive('sprints.index')">
          <LightningIcon class="nav-icon" /> Sprints
        </NavItem>
        <NavItem :href="route('members')" :active="isActive('members')">
          <UserIcon class="nav-icon" /> Members
        </NavItem>
        <NavItem :href="route('audit')" :active="isActive('audit')">
          <HistoryIcon class="nav-icon" /> Audit log
        </NavItem>
        <button type="button" class="nav-btn" @click="showWorkspaceSettings = true; mobileNavOpen = false">
          <SettingsIcon class="nav-icon" /> Settings
        </button>

      </nav>

      <!-- User bar -->
      <div class="user-bar" @click="router.visit(route('profile.edit'))" title="View profile">
        <Avatar :name="user.name" :color="user.avatar_color || null" size="sm" />
        <div class="user-info">
          <div class="user-name">{{ user.name }}</div>
          <div class="user-role">{{ roleLabel }}</div>
        </div>
        <button class="logout-btn" title="Sign out" aria-label="Sign out" @click.stop="logout">
          <LogoutIcon style="width:14px;height:14px" />
        </button>
      </div>

    </aside>

    <!-- Main -->
    <main class="main-area">
      <!-- Mobile top bar: always workspace icon + name (matches design); the
           active tab title lives in .app-topbar below. Only shown ≤768px. -->
      <div class="mobile-topbar">
        <button class="hamburger-btn" type="button" aria-label="Open menu" @click="mobileNavOpen = true">
          <MenuIcon style="width:18px;height:18px" />
        </button>
        <div class="ws-logo" :style="{ background: workspace?.color ?? 'var(--accent)' }">
          {{ workspaceLetter }}
        </div>
        <span class="mobile-ws-name">{{ workspace?.name ?? 'Taskline' }}</span>
      </div>

      <!-- Page topbar: shows the active view label (matches design) -->
      <header v-if="title || $slots.actions" class="app-topbar">
        <div class="crumbs">
          <span class="crumb-current">{{ title }}</span>
        </div>
        <slot name="actions" />
      </header>

      <slot />
    </main>
  </div>

  <ToastContainer />
  <NewProjectModal v-if="workspace && canManageWorkspace" :show="showNewProject" @close="showNewProject = false" />
  <WorkspaceSwitcherModal :show="showWorkspaceSwitcher" @close="showWorkspaceSwitcher = false" />
  <WorkspaceSettingsModal :show="showWorkspaceSettings" @close="showWorkspaceSettings = false" />
</template>

<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { usePage, router, Link } from '@inertiajs/vue3'
import NavItem from '@/Components/UI/NavItem.vue'
import Avatar from '@/Components/UI/Avatar.vue'
import ToastContainer from '@/Components/UI/ToastContainer.vue'
import NewProjectModal from '@/Components/Modals/NewProjectModal.vue'
import WorkspaceSwitcherModal from '@/Components/Modals/WorkspaceSwitcherModal.vue'
import WorkspaceSettingsModal from '@/Components/Modals/WorkspaceSettingsModal.vue'
import { useToast } from '@/composables/useToast'
import {
  ChevronIcon, HomeIcon, InboxIcon, UserIcon,
  PlusIcon, HistoryIcon, SettingsIcon, LogoutIcon,
  MenuIcon, CloseIcon, LightningIcon,
} from '@/Components/UI/Icons.vue'

defineProps({
  // Label shown in the page topbar (e.g. "Dashboard", "Inbox").
  // When empty and no #actions slot is provided, the topbar is hidden so
  // pages that render their own topbar (e.g. Projects/Show) aren't doubled up.
  title: { type: String, default: '' },
})

const page = usePage()
const user = computed(() => page.props.auth.user)
const projects = computed(() => page.props.projects ?? [])
const workspace = computed(() => page.props.workspace)
const { toast } = useToast()

const showNewProject = ref(false)
const showWorkspaceSwitcher = ref(false)
const showWorkspaceSettings = ref(false)

// Mobile off-canvas navigation. On ≤768px the sidebar slides in as a drawer;
// a hamburger in the mobile top bar toggles it. Closed automatically whenever
// an Inertia navigation starts (see onMounted below) so it never lingers.
const mobileNavOpen = ref(false)
let offMobileNav = null

const workspaceLetter = computed(() =>
  workspace.value?.name?.[0]?.toUpperCase() ?? user.value?.name?.[0]?.toUpperCase() ?? 'W'
)

watch(() => page.props.flash, (flash) => {
  if (flash?.success) toast(flash.success)
  if (flash?.error)   toast(flash.error)
}, { deep: true })

function isActive(name) { return route().current(name) }
function isProjectActive(id) {
  return (route().current('projects.show') || route().current('projects.settings'))
    && route().params?.project == id
}
function isProjectSettingsActive(id) {
  return route().current('projects.settings') && route().params?.project == id
}

const inboxCount = computed(() => page.props.inbox_count ?? 0)
const myTasksCount = computed(() => page.props.my_tasks_count ?? 0)

const isOwner = computed(() =>
  workspace.value && workspace.value.owner_id === user.value?.id
)

// Owners and admins manage the workspace (new projects, sprints); members
// and viewers only work with tasks. Role is shared by HandleInertiaRequests.
const canManageWorkspace = computed(() =>
  ['owner', 'admin'].includes(workspace.value?.role)
)

const roleLabel = computed(() => {
  const role = workspace.value?.role ?? (isOwner.value ? 'owner' : 'member')
  return role.charAt(0).toUpperCase() + role.slice(1)
})

function logout() {
  router.post(route('logout'))
}

// Realtime: when an owner changes my project access, refresh sidebar + current
// page so the new project appears (or the revoked one disappears) without a
// manual reload.
let userChannelName = null
onMounted(() => {
  // Any page navigation (nav links, profile, logout) closes the mobile drawer.
  offMobileNav = router.on('start', () => { mobileNavOpen.value = false })

  const uid = user.value?.id
  if (!uid || !window.Echo) return

  userChannelName = `App.Models.User.${uid}`
  window.Echo.private(userChannelName)
    .listen('MemberProjectAccessUpdated', () => {
      router.reload({ preserveScroll: true, preserveState: true })
    })
    // A new mention or assignment landed in my inbox — refresh the sidebar
    // badge (inbox_count is a shared prop) and, if I'm on the Inbox page, its
    // list at the same time.
    .listen('InboxNotificationSent', () => {
      router.reload({ preserveScroll: true, preserveState: true })
    })
    // A manager just approved (or revoked) my task-level access request — the
    // locked "Request access" panel and the inbox lock icon both depend on
    // server state, so re-pull it so the task unlocks (or re-locks) live.
    .listen('TaskAccessRequestUpdated', () => {
      router.reload({ preserveScroll: true, preserveState: true })
    })
})

onBeforeUnmount(() => {
  if (userChannelName && window.Echo) window.Echo.leave(userChannelName)
  userChannelName = null
  offMobileNav?.()
})
</script>

<style scoped>
.app-shell {
  display: flex;
  height: 100vh;
  overflow: hidden;
  background: var(--bg-app);
}

/* ── Sidebar ── */
.sidebar {
  width: var(--sidebar-w, 240px);
  min-width: var(--sidebar-w, 240px);
  background: var(--bg-sunken);
  border-right: 1px solid var(--border);
  display: flex;
  flex-direction: column;
  height: 100vh;
  font-family: var(--font-ui);
}

/* Workspace header */
.workspace-header {
  display: flex;
  align-items: center;
  gap: var(--s-2);
  padding: var(--s-3) var(--s-3);
  border-bottom: 1px solid var(--border);
  cursor: pointer;
  user-select: none;
  transition: background 80ms;
}
.workspace-header:hover { background: var(--bg-hover); }

.ws-logo {
  width: 24px; height: 24px;
  border-radius: var(--r-sm);
  background: var(--accent);
  color: var(--accent-fg);
  font-size: 12px; font-weight: 600;
  display: grid; place-items: center;
  flex-shrink: 0;
}
.ws-name {
  flex: 1; min-width: 0;
  font-size: var(--fs-13); font-weight: 600;
  color: var(--fg);
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.ws-chev { color: var(--fg-subtle); flex-shrink: 0; }

/* Nav */
.sidebar-nav {
  flex: 1; overflow-y: auto;
  padding: var(--s-2);
  display: flex; flex-direction: column;
  gap: 1px;
}

.nav-section-label {
  display: flex; align-items: center; justify-content: space-between;
  padding: var(--s-3) var(--s-2) var(--s-1);
  font-size: var(--fs-12); font-weight: 500;
  color: var(--fg-subtle);
}

.section-add-btn {
  display: flex; align-items: center; justify-content: center;
  width: 20px; height: 20px;
  border-radius: 4px; border: none; background: none;
  color: var(--fg-subtle); cursor: pointer;
  transition: background 80ms, color 80ms;
}
.section-add-btn:hover { background: var(--bg-hover); color: var(--fg); }

.project-dot {
  width: 8px; height: 8px;
  border-radius: 2px;
  display: inline-block; flex-shrink: 0;
}

/* Project row = project link + reveal-on-hover settings gear */
.project-row {
  display: flex; align-items: center;
  border-radius: var(--r-sm);
  transition: background 80ms;
}
.project-row:hover { background: var(--bg-hover); }
.project-row.active { background: var(--bg-active); }
.project-link {
  flex: 1; min-width: 0;
  display: flex; align-items: center; gap: 8px;
  padding: 0 var(--s-2); height: var(--row-h, 32px);
  font-size: var(--fs-13); font-family: var(--font-ui); font-weight: 400;
  color: var(--fg-muted); text-decoration: none;
  white-space: nowrap; overflow: hidden;
}
.project-row:hover .project-link,
.project-row.active .project-link { color: var(--fg); }
.project-row.active .project-link { font-weight: 500; }
.project-settings-btn {
  display: inline-flex; align-items: center; justify-content: center;
  width: 24px; height: 24px; margin-right: 4px; flex-shrink: 0;
  border-radius: var(--r-sm); border: none; background: none;
  color: var(--fg-subtle); cursor: pointer; text-decoration: none;
  opacity: 0;
  transition: opacity 80ms, background 80ms, color 80ms;
}
.project-row:hover .project-settings-btn,
.project-settings-btn.visible { opacity: 1; }
.project-settings-btn:hover { background: var(--bg-active); color: var(--fg); }

.nav-icon {
  width: 16px; height: 16px;
  flex-shrink: 0; opacity: 0.85;
}

.nav-badge {
  margin-left: auto;
  font-size: var(--fs-12);
  color: var(--fg-subtle);
  font-variant-numeric: tabular-nums;
}

/* Settings button — matches nav-item-link exactly */
.nav-btn {
  display: flex; align-items: center;
  gap: var(--s-2); padding: 0 var(--s-2);
  height: var(--row-h); width: 100%;
  border-radius: var(--r-sm); border: none; background: none;
  font-size: var(--fs-13); font-family: var(--font-ui); font-weight: 400;
  color: var(--fg-muted); cursor: pointer; text-align: left;
  transition: background 80ms, color 80ms;
}
.nav-btn:hover { background: var(--bg-hover); color: var(--fg); }

/* No-workspace prompt */
.no-workspace-btn {
  display: flex; align-items: center; gap: 6px;
  width: 100%; padding: 6px 8px;
  border-radius: var(--r-md, 6px);
  border: 1px dashed var(--border-strong, #d4d4cf);
  background: none; color: var(--fg-muted);
  font-size: 12px; cursor: pointer;
  transition: background 80ms, color 80ms, border-color 80ms;
  margin: 2px 0;
}
.no-workspace-btn:hover {
  background: var(--accent-soft, #eef2ff);
  color: var(--accent); border-color: var(--accent);
}

/* User bar */
.user-bar {
  display: flex; align-items: center;
  gap: var(--s-2);
  padding: var(--s-3);
  border-top: 1px solid var(--border);
  cursor: pointer;
  transition: background 80ms;
}
.user-bar:hover { background: var(--bg-hover); }

.user-info { flex: 1; min-width: 0; }
.user-name {
  font-size: var(--fs-13); font-weight: 500; color: var(--fg);
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.user-role {
  font-size: var(--fs-12); color: var(--fg-subtle);
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}

.logout-btn {
  display: flex; align-items: center; justify-content: center;
  width: 26px; height: 26px; flex-shrink: 0;
  border-radius: var(--r-sm); border: none; background: none;
  color: var(--fg-subtle); cursor: pointer;
  transition: background 80ms, color 80ms;
}
.logout-btn:hover { background: var(--bg-active); color: var(--fg); }

/* Main */
.main-area {
  flex: 1; min-width: 0; min-height: 0;
  display: flex; flex-direction: column;
  background: var(--bg-app);
  overflow-y: auto;
}

/* Page topbar */
.app-topbar {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 20px;
  border-bottom: 1px solid var(--border);
  background: var(--bg-app);
  min-height: 48px;
  flex-shrink: 0;
  position: sticky;
  top: 0;
  z-index: 5;
}
.crumbs {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  color: var(--fg-muted);
  flex: 1;
  min-width: 0;
}
.crumb-current { color: var(--fg); font-weight: 500; }

/* ── Mobile drawer + top bar (hidden on desktop) ──────────────────────── */
.mobile-topbar,
.sidebar-backdrop { display: none; }

/* Drawer close × — only revealed on mobile (sits in the workspace header row) */
.drawer-close { display: none; }

@media (max-width: 768px) {
  /* Sidebar slides in as an off-canvas drawer */
  .sidebar {
    position: fixed;
    top: 0; left: 0; bottom: 0;
    z-index: 45;
    width: 264px; min-width: 264px;
    transform: translateX(-100%);
    transition: transform 220ms cubic-bezier(0.32, 0.72, 0, 1);
  }
  .sidebar.mobile-open {
    transform: translateX(0);
    box-shadow: var(--shadow-lg);
  }

  .sidebar-backdrop {
    display: block;
    position: fixed; inset: 0;
    background: rgba(0, 0, 0, 0.4);
    z-index: 44;
    animation: fade-in 120ms ease-out;
  }

  /* Close (×) tucked into the workspace header row — matches the design */
  .drawer-close {
    display: inline-flex; align-items: center; justify-content: center;
    width: 28px; height: 28px; flex-shrink: 0;
    margin-left: 2px;
    border: none; background: none; border-radius: var(--r-sm);
    color: var(--fg-subtle); cursor: pointer;
    transition: background 80ms, color 80ms;
  }
  .drawer-close:hover { background: var(--bg-hover); color: var(--fg); }

  /* Mobile top bar with hamburger */
  .mobile-topbar {
    display: flex;
    align-items: center;
    gap: 10px;
    height: 52px;
    padding: 0 12px;
    border-bottom: 1px solid var(--border);
    background: var(--bg-app);
    position: sticky;
    top: 0;
    z-index: 30;
    flex-shrink: 0;
  }
  .hamburger-btn {
    display: inline-flex; align-items: center; justify-content: center;
    width: 36px; height: 36px;
    border: 1px solid var(--border);
    background: var(--bg-panel);
    border-radius: var(--r-md);
    color: var(--fg);
    cursor: pointer;
    flex-shrink: 0;
    transition: background 80ms;
  }
  .hamburger-btn:hover { background: var(--bg-hover); }
  .mobile-topbar .ws-logo { width: 22px; height: 22px; font-size: 11px; }
  .mobile-ws-name {
    flex: 1; min-width: 0;
    font-size: 14px; font-weight: 600; color: var(--fg);
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  }

  /* When AppLayout renders its own page topbar, drop it below the hamburger bar */
  .app-topbar {
    position: static;
    padding: 10px 16px;
  }

  /* No hover on touch — keep the project settings gear permanently visible */
  .project-settings-btn { opacity: 1; }
}
</style>