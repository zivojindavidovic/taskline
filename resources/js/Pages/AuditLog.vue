<template>
  <AppLayout>
    <div class="audit-page">

      <!-- Header -->
      <div class="audit-header">
        <h2 class="audit-title">Audit log</h2>
        <p class="audit-sub">
          Every change across the workspace, in order. Filter by project, person, or action — coming soon.
        </p>
      </div>

      <!-- Filter bar -->
      <div class="audit-filters">
        <!-- Project filter -->
        <button type="button" class="btn secondary sm" @click="toggleMenu('project')">
          <FilterIcon class="btn-icon" />
          <template v-if="filters.project_id !== null">
            <span class="project-dot" :style="{ background: selectedProject?.color }" />
            <span>{{ selectedProject?.name ?? 'Project' }}</span>
          </template>
          <span v-else>All projects</span>
        </button>
        <div v-if="openMenu === 'project'" v-click-outside="closeMenu" class="filter-menu">
          <button type="button" class="menu-item" :class="{ active: filters.project_id === null }" @click="applyFilter('project_id', null)">
            All projects
          </button>
          <div class="menu-divider" />
          <button
            v-for="p in projects"
            :key="p.id"
            type="button"
            class="menu-item"
            :class="{ active: filters.project_id === p.id }"
            @click="applyFilter('project_id', p.id)"
          >
            <span class="project-dot" :style="{ background: p.color }" />
            {{ p.name }}
          </button>
          <div v-if="!projects.length" class="menu-empty">No projects yet</div>
        </div>

        <!-- Person filter -->
        <button type="button" class="btn secondary sm" @click="toggleMenu('user')">
          <UserIcon class="btn-icon" />
          <span>{{ selectedUser?.name ?? 'Anyone' }}</span>
        </button>
        <div v-if="openMenu === 'user'" v-click-outside="closeMenu" class="filter-menu">
          <button type="button" class="menu-item" :class="{ active: filters.user_id === null }" @click="applyFilter('user_id', null)">
            Anyone
          </button>
          <div class="menu-divider" />
          <button
            v-for="m in members"
            :key="m.id"
            type="button"
            class="menu-item"
            :class="{ active: filters.user_id === m.id }"
            @click="applyFilter('user_id', m.id)"
          >
            <Avatar :name="m.name" :color="m.avatar_color || null" size="sm" />
            {{ m.name }}
          </button>
        </div>

        <!-- Range filter -->
        <button type="button" class="btn secondary sm" @click="toggleMenu('range')">
          <CalendarIcon class="btn-icon" />
          <span>{{ rangeLabel }}</span>
        </button>
        <div v-if="openMenu === 'range'" v-click-outside="closeMenu" class="filter-menu">
          <button
            v-for="opt in rangeOptions"
            :key="opt.value"
            type="button"
            class="menu-item"
            :class="{ active: filters.range === opt.value }"
            @click="applyFilter('range', opt.value)"
          >
            {{ opt.label }}
          </button>
        </div>
      </div>

      <!-- Feed -->
      <div v-if="logs.data?.length" class="list-card">
        <div
          v-for="log in logs.data"
          :key="log.id"
          class="task-row audit-event"
        >
          <Avatar :name="log.user?.name ?? 'Someone'" :color="log.user?.avatar_color || null" size="sm" />

          <div class="event-body">
            <p class="event-text">
              <strong>{{ log.user?.name ?? 'Someone' }}</strong><span v-html="describe(log)" />
            </p>
            <div class="event-meta">
              <span class="time" :title="absoluteTime(log.created_at)">{{ relativeTime(log.created_at) }}</span>
              <template v-if="log.project">
                <span class="sep">·</span>
                <span class="proj">
                  <span class="proj-dot" :style="{ background: log.project.color }" />
                  {{ log.project.name }}
                </span>
              </template>
            </div>
          </div>
        </div>
      </div>

      <!-- Empty state -->
      <div v-else class="audit-empty">
        <HistoryIcon class="empty-icon" />
        <div class="empty-title">{{ hasActiveFilters ? 'No events match these filters' : 'No activity yet' }}</div>
        <div class="empty-sub">
          {{ hasActiveFilters
            ? 'Try widening the range or clearing filters.'
            : 'Audit entries appear as soon as anyone in the workspace makes a change.' }}
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="lastPage > 1" class="audit-pager">
        <button
          type="button"
          class="btn ghost sm"
          :disabled="currentPage === 1"
          @click="changePage(currentPage - 1)"
        >
          <ArrowLeftIcon />
          Previous
        </button>
        <span class="pager-info">Page {{ currentPage }} of {{ lastPage }}</span>
        <button
          type="button"
          class="btn ghost sm"
          :disabled="currentPage === lastPage"
          @click="changePage(currentPage + 1)"
        >
          Next
          <ArrowRightIcon />
        </button>
      </div>

    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Avatar from '@/Components/UI/Avatar.vue'
import {
  FilterIcon, UserIcon, CalendarIcon,
  HistoryIcon, ArrowLeftIcon, ArrowRightIcon,
} from '@/Components/UI/Icons.vue'

const props = defineProps({
  logs:     { type: Object, default: () => ({ data: [] }) },
  projects: { type: Array,  default: () => [] },
  members:  { type: Array,  default: () => [] },
  filters:  { type: Object, default: () => ({ project_id: null, user_id: null, range: '7d' }) },
})

// Pagination — Inertia paginator surfaces `current_page` and `last_page` at top level OR inside `meta`.
const currentPage = computed(() => props.logs.current_page ?? props.logs.meta?.current_page ?? 1)
const lastPage    = computed(() => props.logs.last_page    ?? props.logs.meta?.last_page    ?? 1)

const rangeOptions = [
  { value: 'today', label: 'Today' },
  { value: '7d',    label: 'Last 7 days' },
  { value: '30d',   label: 'Last 30 days' },
  { value: 'all',   label: 'All time' },
]
const rangeLabel = computed(() =>
  rangeOptions.find((r) => r.value === props.filters.range)?.label ?? 'Last 7 days'
)

const selectedProject = computed(() => props.projects.find((p) => p.id === props.filters.project_id))
const selectedUser    = computed(() => props.members.find((m) => m.id === props.filters.user_id))

const hasActiveFilters = computed(() =>
  props.filters.project_id !== null ||
  props.filters.user_id !== null ||
  props.filters.range !== '7d'
)

// ── Action humanization ──────────────────────────────────────────────────
// Build the trailing phrase after the actor's name. Returns HTML so we can
// bold task keys and entity names inline (matching the design's flat feed).
function describe(log) {
  const action = log.action || ''
  const meta   = log.meta || {}
  const taskKey   = log.task?.key
  const taskTitle = log.task?.title

  const tk = taskKey
    ? ` <strong>${escape(taskKey)}</strong>`
    : (meta.key ? ` <strong>${escape(meta.key)}</strong>` : '')

  const tt = taskTitle
    ? ` ${escape(taskTitle)}`
    : (meta.title && !taskKey ? ` ${escape(meta.title)}` : '')

  switch (action) {
    case 'task.created':           return ` created${tk}${tt}`
    case 'task.completed':         return ` completed${tk}${tt}`
    case 'task.reopened':          return ` reopened${tk}${tt}`
    case 'task.deleted':           return ` deleted task <strong>${escape(meta.key ?? '')}</strong> ${meta.title ? escape(meta.title) : ''}`
    case 'task.moved':             return ` moved${tk} to <strong>${escape(meta.column ?? '—')}</strong>`
    case 'task.moved_to_backlog':  return ` moved${tk} to backlog`
    case 'task.project_changed':   return ` moved${tk} across projects`
    case 'task.sprint_changed':    return ` changed sprint on${tk}`
    case 'task.assigned':          return ` updated assignees on${tk}`
    case 'task.priority_changed':  return ` set priority on${tk} to <strong>${escape(meta.priority ?? '—')}</strong>`
    case 'task.renamed':           return ` renamed${tk}`
    case 'task.tags_updated':      return ` updated tags on${tk}`
    case 'task.subtask_added':     return ` added subtask <strong>${escape(meta.subtask_key ?? '')}</strong>${tt} on${tk}`
    case 'task.subtask_updated':   return ` updated subtask <strong>${escape(meta.subtask_key ?? '')}</strong> on${tk}`
    case 'task.updated':           return ` updated${tk}`

    case 'sprint.created':         return ` created sprint <strong>${escape(meta.sprint ?? '')}</strong>`
    case 'sprint.locked':          return ` locked <strong>${escape(meta.sprint ?? '')}</strong>`
    case 'sprint.unlocked':        return ` unlocked <strong>${escape(meta.sprint ?? '')}</strong>`
    case 'sprint.completed':       return ` completed <strong>${escape(meta.sprint ?? '')}</strong>`
    case 'sprint.reopened':        return ` reopened <strong>${escape(meta.sprint ?? '')}</strong>`

    case 'project.created':        return ` created project <strong>${escape(meta.name ?? '')}</strong>`

    case 'column.created':         return ` added column <strong>${escape(meta.column ?? '')}</strong>`
    case 'column.deleted':         return ` removed column <strong>${escape(meta.column ?? '')}</strong>`
    case 'column.renamed':         return ` renamed column <strong>${escape(meta.from ?? '')}</strong> → <strong>${escape(meta.to ?? '')}</strong>`

    case 'member.invited':         return ` invited <strong>${escape(meta.email ?? 'a teammate')}</strong>`
    case 'member.removed':         return ` removed <strong>${escape(meta.email ?? 'a teammate')}</strong>`

    case 'workspace.invitation_sent':     return ` invited <strong>${escape(meta.email ?? 'a teammate')}</strong>`
    case 'workspace.invitation_revoked':  return ` revoked an invitation`
    case 'workspace.invitation_accepted': return ` accepted the workspace invitation`

    default: return ` ${escape(action.replace(/[._]/g, ' '))}`
  }
}

function escape(s) {
  return String(s)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;').replace(/'/g, '&#39;')
}

// ── Time formatting ─────────────────────────────────────────────────────
function relativeTime(dt) {
  if (!dt) return ''
  const t   = new Date(dt).getTime()
  const now = Date.now()
  const diff = Math.max(0, now - t)
  const m = Math.floor(diff / 60000)
  if (m < 1)   return 'just now'
  if (m < 60)  return `${m}m`
  const h = Math.floor(m / 60)
  if (h < 24)  return `${h}h`
  const d = Math.floor(h / 24)
  if (d < 7)   return `${d}d`
  return new Date(dt).toLocaleDateString(undefined, { month: 'short', day: 'numeric' })
}

function absoluteTime(dt) {
  if (!dt) return ''
  return new Date(dt).toLocaleString(undefined, {
    month: 'short', day: 'numeric', year: 'numeric',
    hour: 'numeric', minute: '2-digit',
  })
}

// ── Filter menu state ────────────────────────────────────────────────────
const openMenu = ref(null)
function toggleMenu(name) { openMenu.value = openMenu.value === name ? null : name }
function closeMenu() { openMenu.value = null }

function applyFilter(key, value) {
  closeMenu()
  router.get(route('audit'), {
    project_id: key === 'project_id' ? value : props.filters.project_id,
    user_id:    key === 'user_id'    ? value : props.filters.user_id,
    range:      key === 'range'      ? value : props.filters.range,
  }, { preserveScroll: true, preserveState: true, replace: true })
}

function changePage(page) {
  router.get(route('audit'), {
    page,
    project_id: props.filters.project_id,
    user_id:    props.filters.user_id,
    range:      props.filters.range,
  }, { preserveScroll: true, preserveState: true })
}

// ── click-outside directive (local) ──────────────────────────────────────
const vClickOutside = {
  mounted(el, binding) {
    el._onDocClick = (e) => { if (!el.contains(e.target)) binding.value() }
    setTimeout(() => document.addEventListener('mousedown', el._onDocClick), 0)
  },
  unmounted(el) { document.removeEventListener('mousedown', el._onDocClick) },
}

// Close menu on Esc
function onKey(e) { if (e.key === 'Escape') closeMenu() }
onMounted(() => document.addEventListener('keydown', onKey))
onBeforeUnmount(() => document.removeEventListener('keydown', onKey))
</script>

<style scoped>
/* ── Page shell ─────────────────────────────────────────────────────── */
.audit-page {
  max-width: 880px;
  margin: 0 auto;
  padding: var(--s-6) var(--s-8);
  display: flex;
  flex-direction: column;
  gap: var(--s-4);
}

.audit-header { display: flex; flex-direction: column; gap: 2px; }
.audit-title {
  font-size: var(--fs-20);
  font-weight: 600;
  margin: 0;
  color: var(--fg);
}
.audit-sub {
  font-size: var(--fs-13);
  color: var(--fg-muted);
  margin: 0;
}

/* ── Filter bar ─────────────────────────────────────────────────────── */
.audit-filters {
  display: flex;
  align-items: center;
  gap: var(--s-2);
  flex-wrap: wrap;
  position: relative;
}
.audit-filters .btn-icon { width: 14px; height: 14px; flex-shrink: 0; }
.audit-filters .project-dot { width: 8px; height: 8px; border-radius: 2px; flex-shrink: 0; }

.filter-menu {
  position: absolute;
  top: 32px;
  left: 0;
  min-width: 200px;
  max-height: 320px;
  overflow-y: auto;
  background: var(--bg-panel);
  border: 1px solid var(--border);
  border-radius: var(--r-md);
  box-shadow: var(--shadow-md);
  padding: 4px;
  z-index: 20;
}
.filter-menu .menu-item {
  display: flex;
  align-items: center;
  gap: 8px;
  width: 100%;
  text-align: left;
  padding: 6px 8px;
  border-radius: var(--r-sm);
  font-size: var(--fs-13);
  color: var(--fg);
  background: none;
  border: none;
  cursor: pointer;
}
.filter-menu .menu-item:hover  { background: var(--bg-hover); }
.filter-menu .menu-item.active { background: var(--accent-soft); color: var(--accent); }
.filter-menu .menu-item .project-dot { width: 8px; height: 8px; border-radius: 2px; flex-shrink: 0; }
.filter-menu .menu-empty { padding: 8px; color: var(--fg-subtle); font-size: var(--fs-12); }

/* ── Feed (flat list, mirrors the design's .task-row pattern) ───────── */
.audit-event {
  align-items: flex-start;
  cursor: default;
}
.audit-event:hover { background: transparent; }

.event-body { flex: 1; min-width: 0; }
.event-text {
  font-size: var(--fs-13);
  color: var(--fg);
  margin: 0;
  line-height: 1.5;
  overflow-wrap: anywhere;
}
.event-text :deep(strong) { font-weight: 600; color: var(--fg); }

.event-meta {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 2px;
  font-size: var(--fs-12);
  color: var(--fg-subtle);
}
.event-meta .time { font-variant-numeric: tabular-nums; }
.event-meta .proj { display: inline-flex; align-items: center; gap: 4px; }
.event-meta .proj-dot { width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0; }

/* ── Empty state ────────────────────────────────────────────────────── */
.audit-empty {
  background: var(--bg-panel);
  border: 1px dashed var(--border-strong);
  border-radius: var(--r-lg);
  padding: var(--s-12) var(--s-5);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  text-align: center;
}
.audit-empty .empty-icon { width: 28px; height: 28px; color: var(--fg-subtle); margin-bottom: 4px; }
.audit-empty .empty-title { font-size: var(--fs-14); font-weight: 500; color: var(--fg); }
.audit-empty .empty-sub   { font-size: var(--fs-13); color: var(--fg-muted); max-width: 360px; }

/* ── Pagination ────────────────────────────────────────────────────── */
.audit-pager {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--s-3);
  padding-top: var(--s-2);
}
.audit-pager .pager-info {
  font-size: var(--fs-12);
  color: var(--fg-muted);
  font-variant-numeric: tabular-nums;
}
.audit-pager :deep(svg) { width: 14px; height: 14px; }

@media (max-width: 640px) {
  .audit-page { padding: var(--s-4); }
}
</style>
