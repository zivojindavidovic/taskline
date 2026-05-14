<template>
  <div class="app-shell">
    <!-- Sidebar -->
    <aside class="sidebar">

      <!-- Workspace header -->
      <div class="workspace-header" @click="showWorkspaceSwitcher = true">
        <div class="ws-logo" :style="{ background: workspace?.color ?? 'var(--accent)' }">
          {{ workspaceLetter }}
        </div>
        <span class="ws-name">{{ workspace?.name ?? 'My Workspace' }}</span>
        <ChevronIcon class="ws-chev" />
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
          <button v-if="workspace" class="section-add-btn" title="New project" @click="showNewProject = true">
            <PlusIcon style="width:13px;height:13px" />
          </button>
        </div>

        <button v-if="!workspace" type="button" class="no-workspace-btn" @click="showWorkspaceSwitcher = true">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
          </svg>
          Create a workspace first
        </button>

        <template v-for="p in projects" :key="p.id">
          <NavItem :href="route('projects.show', p.id)" :active="isProjectActive(p.id)">
            <span class="project-dot" :style="{ background: p.color }" />
            <span class="truncate">{{ p.name }}</span>
          </NavItem>
          <NavItem
            v-if="isProjectActive(p.id)"
            :href="route('projects.members', p.id)"
            :active="route().current('projects.members') && route().params?.project == p.id"
            style="padding-left: 28px"
          >
            <UsersIcon style="width:14px;height:14px;flex-shrink:0" />
            <span class="truncate">Members</span>
          </NavItem>
        </template>

        <!-- Workspace section -->
        <div class="nav-section-label" style="margin-top:8px">
          <span>Workspace</span>
        </div>
        <NavItem :href="route('members')" :active="isActive('members')">
          <UsersIcon class="nav-icon" /> Members
        </NavItem>
        <NavItem :href="route('audit')" :active="isActive('audit')">
          <HistoryIcon class="nav-icon" /> Audit log
        </NavItem>
        <button type="button" class="nav-btn" @click="showWorkspaceSettings = true">
          <SettingsIcon class="nav-icon" /> Settings
        </button>

      </nav>

      <!-- User bar -->
      <div class="user-bar">
        <div class="user-bar-inner" @click="router.visit(route('profile.edit'))">
          <Avatar :name="user.name" :color="user.avatar_color || null" size="sm" />
          <div class="user-info">
            <div class="user-name">{{ user.name }}</div>
            <div class="user-role">Owner</div>
          </div>
        </div>
        <button class="bell-btn" title="Notifications" @click="toast('Notifications — coming soon')">
          <BellIcon style="width:14px;height:14px" />
        </button>
      </div>

    </aside>

    <!-- Main -->
    <main class="main-area">
      <slot />
    </main>
  </div>

  <ToastContainer />
  <NewProjectModal v-if="workspace" :show="showNewProject" @close="showNewProject = false" />
  <WorkspaceSwitcherModal :show="showWorkspaceSwitcher" @close="showWorkspaceSwitcher = false" />
  <WorkspaceSettingsModal :show="showWorkspaceSettings" @close="showWorkspaceSettings = false" />
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import NavItem from '@/Components/UI/NavItem.vue'
import Avatar from '@/Components/UI/Avatar.vue'
import ToastContainer from '@/Components/UI/ToastContainer.vue'
import NewProjectModal from '@/Components/Modals/NewProjectModal.vue'
import WorkspaceSwitcherModal from '@/Components/Modals/WorkspaceSwitcherModal.vue'
import WorkspaceSettingsModal from '@/Components/Modals/WorkspaceSettingsModal.vue'
import { useToast } from '@/composables/useToast'
import {
  ChevronIcon, HomeIcon, InboxIcon, UserIcon,
  PlusIcon, HistoryIcon, SettingsIcon, BellIcon, UsersIcon,
} from '@/Components/UI/Icons.vue'

const page = usePage()
const user = computed(() => page.props.auth.user)
const projects = computed(() => page.props.projects ?? [])
const workspace = computed(() => page.props.workspace)
const { toast } = useToast()

const showNewProject = ref(false)
const showWorkspaceSwitcher = ref(false)
const showWorkspaceSettings = ref(false)

const workspaceLetter = computed(() =>
  workspace.value?.name?.[0]?.toUpperCase() ?? user.value?.name?.[0]?.toUpperCase() ?? 'W'
)

watch(() => page.props.flash, (flash) => {
  if (flash?.success) toast(flash.success)
  if (flash?.error)   toast(flash.error)
}, { deep: true })

function isActive(name) { return route().current(name) }
function isProjectActive(id) {
  return (
    (route().current('projects.show') || route().current('projects.members')) &&
    route().params?.project == id
  )
}

const inboxCount = computed(() => page.props.inbox_count ?? 0)
const myTasksCount = computed(() => page.props.my_tasks_count ?? 0)
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
  gap: 8px;
  padding: 10px 12px;
  border-bottom: 1px solid var(--border);
  cursor: pointer;
  user-select: none;
  transition: background 80ms;
}
.workspace-header:hover { background: var(--bg-hover); }

.ws-logo {
  width: 24px; height: 24px;
  border-radius: 5px;
  background: var(--accent);
  color: #fff;
  font-size: 12px; font-weight: 700;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.ws-name {
  flex: 1; min-width: 0;
  font-size: 13px; font-weight: 600;
  color: var(--fg);
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.ws-chev { width: 14px; height: 14px; color: var(--fg-subtle); flex-shrink: 0; }

/* Nav */
.sidebar-nav {
  flex: 1; overflow-y: auto;
  padding: 8px;
  display: flex; flex-direction: column;
  gap: 1px;
}

.nav-section-label {
  display: flex; align-items: center; justify-content: space-between;
  padding: 12px 8px 4px;
  font-size: 12px; font-weight: 500;
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

.nav-icon {
  width: 16px; height: 16px;
  flex-shrink: 0; opacity: 0.85;
}

.nav-badge {
  margin-left: auto;
  font-size: 11px; font-weight: 500;
  color: var(--fg-subtle);
  font-variant-numeric: tabular-nums;
}

/* Settings button — matches nav-item-link exactly */
.nav-btn {
  display: flex; align-items: center;
  gap: 8px; padding: 0 8px;
  height: 32px; width: 100%;
  border-radius: 4px; border: none; background: none;
  font-size: 13px; font-family: var(--font-ui); font-weight: 400;
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
  padding: 10px 12px; gap: 6px;
  border-top: 1px solid var(--border);
}
.user-bar-inner {
  display: flex; align-items: center; gap: 8px;
  flex: 1; min-width: 0; cursor: pointer;
  border-radius: 4px; padding: 2px 4px;
  transition: background 80ms;
}
.user-bar-inner:hover { background: var(--bg-hover); }

.user-info { flex: 1; min-width: 0; }
.user-name {
  font-size: 13px; font-weight: 500; color: var(--fg);
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.user-role {
  font-size: 12px; color: var(--fg-subtle);
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}

.bell-btn {
  display: flex; align-items: center; justify-content: center;
  width: 28px; height: 28px; flex-shrink: 0;
  border-radius: 5px; border: none; background: none;
  color: var(--fg-muted); cursor: pointer;
  transition: background 80ms, color 80ms;
}
.bell-btn:hover { background: var(--bg-hover); color: var(--fg); }

/* Main */
.main-area {
  flex: 1; min-width: 0; min-height: 0;
  display: flex; flex-direction: column;
  background: var(--bg-app);
  overflow-y: auto;
}
</style>