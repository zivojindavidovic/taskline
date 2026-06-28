<template>
  <AppLayout title="Sprints">
    <div class="sprints-page">

      <!-- Header -->
      <div class="sprints-header">
        <div>
          <h2 class="sprints-title">Sprints</h2>
          <p class="sprints-sub">
            Plan, track, and review sprints across your projects. Completed sprints keep a
            record of what shipped and what rolled over to the backlog.
          </p>
        </div>
        <button
          v-if="canManage"
          type="button"
          class="btn primary"
          :disabled="!projects.length"
          :title="projects.length ? '' : 'Create a project first'"
          @click="openNew"
        >
          <PlusIcon class="btn-icon" /> New sprint
        </button>
      </div>

      <!-- Empty state -->
      <div v-if="!groups.length" class="sprints-empty">
        <LightningIcon class="empty-icon" />
        <div class="empty-title">No sprints yet</div>
        <div class="empty-sub">
          Sprints you create across your projects show up here, grouped by project.
        </div>
        <button v-if="canManage && projects.length" type="button" class="btn secondary" @click="openNew">
          <PlusIcon class="btn-icon" /> Create your first sprint
        </button>
      </div>

      <!-- Project groups -->
      <div v-for="g in groups" :key="g.project.uuid" class="project-group">
        <div class="group-head">
          <span class="group-dot" :style="{ background: g.project.color }" />
          <span class="group-name">{{ g.project.name }}</span>
          <span class="group-count">{{ g.sprints.length }} sprint{{ g.sprints.length === 1 ? '' : 's' }}</span>
        </div>

        <div class="sprint-list">
          <div
            v-for="s in g.sprints"
            :key="s.uuid"
            class="list-card sprint-card"
            :class="{ 'is-active': s.status === 'active' }"
          >
            <!-- Top row: name, status, dates, actions -->
            <div class="sprint-top">
              <div class="sprint-main">
                <div class="sprint-titlerow">
                  <span class="sprint-name">{{ s.name }}</span>
                  <span class="sprint-status" :style="statusStyle(s.status)">
                    <span class="status-dot" :style="{ background: statusMeta(s.status).color }" />
                    {{ statusMeta(s.status).label }}
                  </span>
                  <span v-if="s.locked && s.status !== 'completed'" class="lock-pill">
                    <LockIcon class="lock-ico" /> Locked
                  </span>
                </div>
                <div v-if="s.start_date || s.end_date" class="sprint-dates">
                  <CalendarIcon class="date-ico" />
                  {{ fmtDate(s.start_date) }} → {{ fmtDate(s.end_date) }}
                </div>
                <p v-if="s.goal" class="sprint-goal">{{ s.goal }}</p>
              </div>

              <div v-if="canManage" class="sprint-actions">
                <button type="button" class="btn ghost sm" title="Edit sprint" @click="openEdit(s)">
                  <EditIcon class="btn-icon" /> Edit
                </button>
                <DropdownMenu align="right" :width="190">
                  <template #trigger>
                    <button type="button" class="btn ghost icon-only sm" aria-label="Sprint actions">
                      <MoreIcon />
                    </button>
                  </template>
                  <button type="button" class="menu-item" @click="openEdit(s)">
                    <EditIcon class="menu-ico" /> Edit sprint
                  </button>
                  <button
                    v-if="s.status !== 'completed'"
                    type="button"
                    class="menu-item"
                    @click="complete(s)"
                  >
                    <CheckIcon class="menu-ico" style="color:var(--status-done)" /> Complete sprint
                  </button>
                  <button v-else type="button" class="menu-item" @click="reopen(s)">
                    <LightningIcon class="menu-ico" /> Reopen sprint
                  </button>
                  <div class="menu-divider" />
                  <button type="button" class="menu-item danger" @click="askDelete(s)">
                    <TrashIcon class="menu-ico" /> Delete sprint
                  </button>
                </DropdownMenu>
              </div>
            </div>

            <!-- Progress (open sprints) -->
            <div v-if="s.status !== 'completed'" class="sprint-progress">
              <div class="progress-head">
                <span class="muted">Progress</span>
                <span><strong>{{ s.progress.done }}</strong> of {{ s.progress.total }} done · {{ s.progress.pct }}%</span>
              </div>
              <div class="progress-track">
                <div
                  class="progress-fill"
                  :style="{
                    width: s.progress.pct + '%',
                    background: s.status === 'active' ? 'var(--status-progress)' : 'var(--fg-subtle)',
                  }"
                />
              </div>
            </div>

            <!-- Statistics (completed sprints) -->
            <div v-else-if="s.summary" class="sprint-stats">
              <div class="stat-row">
                <div class="stat-pill">
                  <CheckIcon class="stat-ico" style="color:var(--status-done)" />
                  <strong>{{ s.summary.completed.length }}</strong> completed
                </div>
                <div class="stat-pill">
                  <InboxIcon class="stat-ico" style="color:var(--fg-muted)" />
                  <strong>{{ s.summary.incomplete.length }}</strong> moved to backlog
                </div>
                <div class="stat-pill">
                  <strong>{{ s.summary.completion_rate }}%</strong> completion rate
                </div>
              </div>
              <button type="button" class="btn ghost sm breakdown-toggle" @click="toggle(s.uuid)">
                <component :is="expanded[s.uuid] ? ArrowUpIcon : ArrowDownIcon" class="btn-icon" />
                {{ expanded[s.uuid] ? 'Hide breakdown' : 'View breakdown' }}
              </button>

              <div v-if="expanded[s.uuid]" class="breakdown">
                <div class="break-col">
                  <div class="break-head" style="color:var(--status-done)">
                    <CheckIcon class="break-ico" /> Completed ({{ s.summary.completed.length }})
                  </div>
                  <div v-if="!s.summary.completed.length" class="muted break-empty">No tasks completed.</div>
                  <div v-for="t in s.summary.completed" :key="t.key" class="break-row">
                    <span class="break-key">{{ t.key }}</span><span class="break-title">{{ t.title }}</span>
                  </div>
                </div>
                <div class="break-col">
                  <div class="break-head" style="color:var(--fg-muted)">
                    <InboxIcon class="break-ico" /> Moved to backlog ({{ s.summary.incomplete.length }})
                  </div>
                  <div v-if="!s.summary.incomplete.length" class="muted break-empty">Nothing rolled over.</div>
                  <div v-for="t in s.summary.incomplete" :key="t.key" class="break-row">
                    <span class="break-key">{{ t.key }}</span><span class="break-title">{{ t.title }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <NewSprintModal
      v-if="canManage"
      :show="modalOpen"
      :sprint="editingSprint"
      :projects="projects"
      @close="modalOpen = false"
    />

    <!-- Delete sprint — choose backlog (default) vs. permanent task deletion -->
    <AppModal v-if="canManage" :show="deleteOpen" title="Delete sprint" @close="deleteOpen = false">
      <p class="del-lead">
        Delete <strong>{{ deletingSprint?.name }}</strong>?
      </p>

      <label class="del-opt">
        <input type="checkbox" v-model="deleteTasks" />
        <span>
          Also permanently delete the
          <strong>{{ deletingSprint?.progress?.total ?? 0 }}</strong>
          task{{ (deletingSprint?.progress?.total ?? 0) === 1 ? '' : 's' }} in this sprint
        </span>
      </label>

      <p class="del-note" :class="{ danger: deleteTasks }">
        <template v-if="deleteTasks">
          These tasks — and their subtasks — will be permanently deleted. This can’t be undone.
        </template>
        <template v-else>
          The sprint’s tasks will be moved to the backlog and kept.
        </template>
      </p>

      <template #footer>
        <button type="button" class="btn secondary" @click="deleteOpen = false">Cancel</button>
        <button type="button" class="btn danger" :disabled="deleting" @click="confirmDelete">
          {{ deleteTasks ? 'Delete sprint + tasks' : 'Delete sprint' }}
        </button>
      </template>
    </AppModal>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onBeforeUnmount } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import AppModal from '@/Components/UI/AppModal.vue'
import DropdownMenu from '@/Components/UI/DropdownMenu.vue'
import NewSprintModal from '@/Components/Modals/NewSprintModal.vue'
import {
  PlusIcon, LightningIcon, LockIcon, CalendarIcon, CheckIcon, InboxIcon,
  EditIcon, TrashIcon, MoreIcon, ArrowUpIcon, ArrowDownIcon,
} from '@/Components/UI/Icons.vue'

const props = defineProps({
  projects:  { type: Array,   default: () => [] },
  sprints:   { type: Array,   default: () => [] },
  canManage: { type: Boolean, default: false },
})

const expanded = reactive({})
const modalOpen = ref(false)
const editingSprint = ref(null)

// Delete-sprint modal state
const deleteOpen = ref(false)
const deletingSprint = ref(null)
const deleteTasks = ref(false)
const deleting = ref(false)

// ── Status meta ────────────────────────────────────────────────────────────
const STATUS = {
  planned:   { label: 'Planned',     color: 'var(--fg-muted)' },
  active:    { label: 'In progress', color: 'var(--status-progress)' },
  completed: { label: 'Done',        color: 'var(--status-done)' },
}
function statusMeta(st) { return STATUS[st] || STATUS.planned }
function statusStyle(st) {
  const c = statusMeta(st).color
  return {
    color: c,
    background: `color-mix(in oklab, ${c} 12%, var(--bg-panel))`,
    border: `1px solid color-mix(in oklab, ${c} 28%, var(--border))`,
  }
}

// ── Grouping (mirrors the prototype: by project, active → planned → completed,
//    only projects that actually have sprints) ──────────────────────────────
const groups = computed(() => {
  const order = { active: 0, planned: 1, completed: 2 }
  return props.projects
    .map((project) => ({
      project,
      sprints: props.sprints
        .filter((s) => s.project_uuid === project.uuid)
        .slice()
        .sort((a, b) => (order[a.status] ?? 9) - (order[b.status] ?? 9)),
    }))
    .filter((g) => g.sprints.length > 0)
})

// ── Date display ─────────────────────────────────────────────────────────────
function fmtDate(iso) {
  if (!iso) return '—'
  const d = new Date(iso + 'T00:00:00')
  if (isNaN(d)) return iso
  return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' })
}

function toggle(uuid) { expanded[uuid] = !expanded[uuid] }

// ── Actions ──────────────────────────────────────────────────────────────────
function openNew() {
  editingSprint.value = null
  modalOpen.value = true
}
function openEdit(s) {
  editingSprint.value = s
  modalOpen.value = true
}
function complete(s) {
  router.post(route('sprints.complete', s.uuid), {}, { preserveScroll: true })
}
function reopen(s) {
  router.post(route('sprints.reopen', s.uuid), {}, { preserveScroll: true })
}
function askDelete(s) {
  deletingSprint.value = s
  deleteTasks.value = false
  deleteOpen.value = true
}
function confirmDelete() {
  const s = deletingSprint.value
  if (!s) return
  deleting.value = true
  router.delete(route('sprints.destroy', s.uuid), {
    data: { delete_tasks: deleteTasks.value },
    preserveScroll: true,
    onFinish: () => { deleting.value = false },
    onSuccess: () => { deleteOpen.value = false },
  })
}

// ── Live updates (websockets) ────────────────────────────────────────────────
// Subscribe to every project the viewer can see; refresh the sprint list when a
// sprint changes (create/lock/complete/reopen/delete/update) or a task moves so
// progress + breakdown stay live without a manual reload.
const liveChannels = []
function reloadSprints() {
  router.reload({ only: ['sprints', 'projects'], preserveScroll: true, preserveState: true })
}
onMounted(() => {
  if (!window.Echo) return
  for (const p of props.projects) {
    if (!p.id) continue
    const name = `project.${p.id}`
    window.Echo.private(name)
      .listen('SprintUpdated', reloadSprints)
      .listen('TaskCreated', reloadSprints)
      .listen('TaskUpdated', reloadSprints)
      .listen('TaskDeleted', reloadSprints)
    liveChannels.push(name)
  }
})
onBeforeUnmount(() => {
  if (window.Echo) liveChannels.forEach((n) => window.Echo.leave(n))
})
</script>

<style scoped>
.sprints-page {
  max-width: 920px;
  margin: 0 auto;
  padding: 24px 32px;
  display: flex;
  flex-direction: column;
  gap: var(--s-6);
}

/* ── Header ── */
.sprints-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--s-4);
}
.sprints-title { font-size: var(--fs-20); font-weight: 600; margin: 0; color: var(--fg); }
.sprints-sub {
  font-size: var(--fs-13); color: var(--fg-muted);
  margin: 4px 0 0; max-width: 560px; line-height: 1.5;
}
.btn-icon { width: 14px; height: 14px; flex-shrink: 0; }

/* ── Empty state ── */
.sprints-empty {
  background: var(--bg-panel);
  border: 1px dashed var(--border-strong);
  border-radius: var(--r-lg);
  padding: 48px 20px;
  display: flex; flex-direction: column; align-items: center; gap: 6px;
  text-align: center;
}
.sprints-empty .empty-icon { width: 26px; height: 26px; color: var(--fg-subtle); margin-bottom: 2px; }
.sprints-empty .empty-title { font-size: var(--fs-14); font-weight: 500; color: var(--fg); }
.sprints-empty .empty-sub { font-size: var(--fs-13); color: var(--fg-muted); max-width: 360px; margin-bottom: 6px; }

/* ── Project group ── */
.project-group { display: flex; flex-direction: column; gap: var(--s-3); }
.group-head { display: flex; align-items: center; gap: 8px; }
.group-dot { width: 9px; height: 9px; border-radius: 3px; flex-shrink: 0; }
.group-name { font-weight: 600; font-size: var(--fs-14); color: var(--fg); }
.group-count { font-size: var(--fs-12); color: var(--fg-muted); }

.sprint-list { display: flex; flex-direction: column; gap: var(--s-3); }

/* ── Sprint card ── */
.sprint-card { padding: 16px 18px; }
.sprint-card.is-active { border-color: color-mix(in oklab, var(--accent) 35%, var(--border)); }

.sprint-top { display: flex; align-items: flex-start; gap: 12px; }
.sprint-main { flex: 1; min-width: 0; }
.sprint-titlerow { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.sprint-name { font-weight: 600; font-size: var(--fs-15); color: var(--fg); }

.sprint-status {
  display: inline-flex; align-items: center; gap: 5px;
  font-size: 11px; font-weight: 500;
  padding: 2px 9px; border-radius: 999px;
}
.status-dot { width: 6px; height: 6px; border-radius: 50%; display: inline-block; }

.lock-pill {
  display: inline-flex; align-items: center; gap: 4px;
  font-size: 11px; font-weight: 500; color: var(--fg-muted);
  padding: 2px 8px; border-radius: 999px;
  background: var(--bg-sunken); border: 1px solid var(--border);
}
.lock-ico { width: 11px; height: 11px; }

.sprint-dates {
  display: flex; align-items: center; gap: 5px;
  font-size: var(--fs-12); color: var(--fg-muted); margin-top: 4px;
}
.date-ico { width: 12px; height: 12px; }
.sprint-goal {
  margin: 8px 0 0; font-size: var(--fs-13);
  color: var(--fg-muted); line-height: 1.5;
}

.sprint-actions { display: flex; align-items: center; gap: 6px; flex-shrink: 0; }

/* ── Progress ── */
.sprint-progress { margin-top: 14px; }
.progress-head {
  display: flex; align-items: center; justify-content: space-between;
  font-size: var(--fs-12); margin-bottom: 6px; color: var(--fg);
}
.progress-head strong { font-variant-numeric: tabular-nums; }
.progress-track { height: 6px; background: var(--border); border-radius: 99px; overflow: hidden; }
.progress-fill { height: 100%; border-radius: 99px; transition: width 200ms; }

/* ── Stats (completed) ── */
.sprint-stats { margin-top: 14px; }
.stat-row { display: flex; gap: 10px; flex-wrap: wrap; }
.stat-pill {
  display: inline-flex; align-items: center; gap: 6px;
  font-size: var(--fs-12); color: var(--fg-muted);
  padding: 5px 11px; border-radius: var(--r-lg);
  background: var(--bg-sunken); border: 1px solid var(--border);
}
.stat-pill strong { color: var(--fg); }
.stat-ico { width: 13px; height: 13px; }
.breakdown-toggle { margin-top: 10px; }

.breakdown {
  margin-top: 12px;
  display: grid; grid-template-columns: 1fr 1fr; gap: 16px;
}
.break-head {
  display: flex; align-items: center; gap: 5px;
  font-size: var(--fs-12); font-weight: 600; margin-bottom: 8px;
}
.break-ico { width: 12px; height: 12px; }
.break-empty { font-size: var(--fs-12); }
.break-row {
  display: flex; align-items: baseline; gap: 8px;
  padding: 4px 0; font-size: var(--fs-12);
  border-top: 1px solid var(--border);
}
.break-key {
  font-family: var(--font-mono); font-size: 11px;
  color: var(--fg-subtle); flex-shrink: 0;
}
.break-title { color: var(--fg-muted); line-height: 1.4; }

.muted { color: var(--fg-muted); }

/* ── Dropdown menu items ── */
.menu-item {
  display: flex; align-items: center; gap: 8px;
  width: 100%; text-align: left;
  padding: 6px 8px; border-radius: var(--r-sm);
  font-size: var(--fs-13); color: var(--fg);
  background: none; border: none; cursor: pointer;
}
.menu-item:hover { background: var(--bg-hover); }
.menu-item.danger { color: var(--status-blocked, #dc2626); }
.menu-ico { width: 14px; height: 14px; flex-shrink: 0; }

/* ── Delete modal ── */
.del-lead { font-size: var(--fs-14); color: var(--fg); margin: 0; }
.del-opt {
  display: flex; align-items: flex-start; gap: 8px;
  font-size: var(--fs-13); color: var(--fg); cursor: pointer;
}
.del-opt input { margin-top: 2px; accent-color: var(--status-blocked, #dc2626); }
.del-note {
  font-size: var(--fs-12); color: var(--fg-muted); margin: 0;
  padding: 8px 10px; border-radius: var(--r-md);
  background: var(--bg-sunken); border: 1px solid var(--border);
}
.del-note.danger {
  color: var(--status-blocked, #dc2626);
  background: color-mix(in oklab, var(--status-blocked, #dc2626) 8%, var(--bg-panel));
  border-color: color-mix(in oklab, var(--status-blocked, #dc2626) 30%, var(--border));
}

@media (max-width: 640px) {
  .sprints-page { padding: var(--s-4); }
  .breakdown { grid-template-columns: 1fr; }
}
</style>
