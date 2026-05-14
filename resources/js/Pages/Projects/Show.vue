<template>
  <AppLayout>
    <!-- Top bar: project name + search + new task -->
    <div
      class="flex items-center gap-3 px-5 py-2.5 shrink-0"
      style="border-bottom:1px solid var(--border);background:var(--bg-panel)"
    >
      <span class="w-3 h-3 rounded-full shrink-0" :style="{ background: project.color }" />
      <h1 class="text-sm font-semibold shrink-0" style="color:var(--fg)">{{ project.name }}</h1>

      <div class="h-4 w-px mx-1 shrink-0" style="background:var(--border)" />

      <!-- Search -->
      <div class="flex items-center gap-2 flex-1 max-w-xs px-2.5 h-8 rounded-lg" style="border:1px solid var(--border);background:var(--bg-sunken)">
        <SearchIcon class="w-3.5 h-3.5 shrink-0" style="color:var(--fg-muted)" />
        <input
          v-model="searchQuery"
          class="flex-1 text-sm bg-transparent border-none outline-none"
          style="color:var(--fg)"
          placeholder="Search tasks..."
        />
      </div>

      <div class="flex-1" />

      <!-- New task button -->
      <button
        v-if="!currentSprint?.locked || isBacklog"
        type="button"
        class="btn-primary h-8 px-3 text-sm rounded-lg flex items-center gap-1.5"
        @click="openNewTask(null)"
      >
        <PlusIcon class="w-3.5 h-3.5" /> New task
        <kbd class="ml-1 text-[10px] px-1 rounded" style="background:rgba(255,255,255,.15)">C</kbd>
      </button>
    </div>

    <!-- Sprint header bar -->
    <div
      class="flex items-center gap-2 px-5 py-2 shrink-0"
      style="border-bottom:1px solid var(--border);background:var(--bg-app)"
    >
      <!-- Sprint picker -->
      <DropdownMenu>
        <template #trigger>
          <button type="button" class="sprint-btn">
            <LightningIcon
              class="w-3.5 h-3.5 shrink-0"
              :style="{ color: isBacklog ? 'var(--fg-muted)' : currentSprint?.locked ? 'var(--fg-muted)' : 'var(--accent)' }"
            />
            {{ isBacklog ? 'Backlog' : (currentSprint?.name ?? 'No sprint') }}
            <span
              v-if="currentSprint?.locked"
              class="text-[10px] px-1.5 py-0.5 rounded font-medium ml-0.5"
              style="background:var(--bg-sunken);color:var(--fg-muted)"
            >Locked</span>
            <ChevronIcon class="w-3.5 h-3.5 ml-0.5" />
          </button>
        </template>
        <div class="py-1">
          <MenuItem v-for="s in sprints" :key="s.id" @click="switchSprint(s.id)">
            <CheckIcon v-if="s.id === currentSprint?.id && !isBacklog" class="w-3.5 h-3.5" style="color:var(--accent)" />
            <span v-else class="w-3.5 h-3.5 inline-block" />
            <LightningIcon class="w-3.5 h-3.5 shrink-0" style="color:var(--accent)" />
            <span class="flex-1">{{ s.name }}</span>
            <span class="ml-2 text-xs" style="color:var(--fg-muted)">{{ s.status }}</span>
            <LockIcon v-if="s.locked" class="w-3 h-3 ml-1" style="color:var(--fg-muted)" />
          </MenuItem>
          <div class="h-px my-1" style="background:var(--border)" />
          <MenuItem @click="switchToBacklog">
            <CheckIcon v-if="isBacklog" class="w-3.5 h-3.5" style="color:var(--accent)" />
            <span v-else class="w-3.5 h-3.5 inline-block" />
            <span style="color:var(--fg-muted)">📋</span>
            <span class="flex-1">Backlog</span>
            <span class="ml-2 text-xs" style="color:var(--fg-muted)">no sprint</span>
          </MenuItem>
          <div class="h-px my-1" style="background:var(--border)" />
          <MenuItem @click="showNewSprint = true">
            <PlusIcon class="w-3.5 h-3.5" style="color:var(--accent)" />
            <span style="color:var(--accent)">New sprint</span>
          </MenuItem>
        </div>
      </DropdownMenu>

      <!-- Sprint meta: dates + days remaining -->
      <div v-if="currentSprint" class="flex items-center gap-1 text-xs" style="color:var(--fg-muted)">
        <span>{{ sprintDateRange }}</span>
        <span v-if="daysRemaining !== null">·</span>
        <span v-if="daysRemaining !== null" :style="{ color: daysRemaining <= 3 ? '#ef4444' : 'var(--fg-muted)' }">
          {{ daysRemaining > 0 ? `${daysRemaining}d remaining` : daysRemaining === 0 ? 'Ends today' : 'Ended' }}
        </span>
      </div>

      <div class="flex-1" />

      <!-- Filters -->
      <div class="relative" ref="filterRef">
        <button
          type="button"
          class="header-btn"
          :class="{ 'header-btn--active': hasActiveFilters }"
          @click="showFilters = !showFilters"
        >
          <FilterIcon class="w-3.5 h-3.5" /> Filters
          <span v-if="hasActiveFilters" class="filter-dot" />
        </button>

        <div v-if="showFilters" class="filter-panel">
          <div class="filter-section">
            <p class="filter-section-label">Assignee</p>
            <div class="filter-options">
              <label class="filter-option">
                <input type="checkbox" :value="null" v-model="filterUnassigned" class="filter-check" />
                <span class="filter-avatar-placeholder">?</span>
                <span>Unassigned</span>
              </label>
              <label v-for="u in allUsers" :key="u.id" class="filter-option">
                <input type="checkbox" :value="u.id" v-model="filterAssignees" class="filter-check" />
                <Avatar :name="u.name" size="sm" />
                <span>{{ u.name }}</span>
              </label>
            </div>
          </div>

          <div class="filter-divider" />

          <div class="filter-section">
            <p class="filter-section-label">Priority</p>
            <div class="filter-options">
              <label v-for="p in priorities" :key="p.value" class="filter-option">
                <input type="checkbox" :value="p.value" v-model="filterPriorities" class="filter-check" />
                <PriorityBadge :priority="p.value" />
                <span>{{ p.label }}</span>
              </label>
            </div>
          </div>

          <div class="filter-divider" />

          <div class="filter-section">
            <p class="filter-section-label">Status</p>
            <div class="filter-options">
              <label class="filter-option">
                <input type="checkbox" value="open" v-model="filterStatuses" class="filter-check" />
                <span class="status-dot" style="background:#6366f1" />
                <span>Open</span>
              </label>
              <label class="filter-option">
                <input type="checkbox" value="completed" v-model="filterStatuses" class="filter-check" />
                <span class="status-dot" style="background:var(--status-done)" />
                <span>Completed</span>
              </label>
            </div>
          </div>

          <div class="filter-divider" />

          <button v-if="hasActiveFilters" type="button" class="filter-clear" @click="clearFilters">
            Clear all filters
          </button>
        </div>
      </div>

      <!-- View toggle -->
      <div class="flex rounded-lg overflow-hidden" style="border:1px solid var(--border)">
        <button
          type="button"
          class="view-btn"
          :class="{ active: view === 'board' }"
          @click="view = 'board'"
        ><BoardIcon class="w-3.5 h-3.5" /> Board</button>
        <button
          type="button"
          class="view-btn"
          :class="{ active: view === 'list' }"
          @click="view = 'list'"
        ><ListIcon class="w-3.5 h-3.5" /> List</button>
      </div>

      <!-- Lock / Unlock sprint -->
      <template v-if="currentSprint && !currentSprint.locked">
        <button type="button" class="header-btn" @click="showLockModal = true">
          <LockIcon class="w-3.5 h-3.5" /> Lock sprint
        </button>
      </template>
      <template v-else-if="currentSprint?.locked">
        <button type="button" class="header-btn" @click="unlockSprint">
          <LockIcon class="w-3.5 h-3.5" /> Unlock sprint
        </button>
      </template>
    </div>

    <!-- Locked banner -->
    <div
      v-if="currentSprint?.locked"
      class="flex items-center gap-2 px-5 py-2 text-xs font-medium shrink-0"
      style="background:var(--bg-sunken);border-bottom:1px solid var(--border);color:var(--fg-muted)"
    >
      <LockIcon class="w-3.5 h-3.5" />
      This sprint is locked. Tasks are read-only.
    </div>

    <!-- Board view -->
    <div v-if="view === 'board'" class="flex-1 overflow-hidden">
      <KanbanBoard
        :columns="filteredColumns"
        :tasks="filteredTasks"
        :locked="currentSprint?.locked ?? false"
        @addTask="openNewTask"
        @openTask="openTask"
        @moveTask="moveTask"
        @addColumn="addColumn"
        @renameColumn="renameColumn"
        @deleteColumn="deleteColumn"
      />
    </div>

    <!-- List view -->
    <div v-else class="flex-1 overflow-auto px-5 py-4">
      <div
        v-for="col in filteredColumns"
        :key="col.id"
        class="mb-6"
      >
        <div class="flex items-center gap-2 mb-2">
          <span class="w-2 h-2 rounded-full" :style="{ background: col.color }" />
          <h3 class="text-xs font-semibold uppercase tracking-wide" style="color:var(--fg-muted)">
            {{ col.name }}
            <span class="ml-1 font-normal">{{ tasksByColumn[col.id]?.length ?? 0 }}</span>
          </h3>
        </div>
        <div
          class="rounded-xl overflow-hidden"
          style="border:1px solid var(--border)"
        >
          <div
            v-if="!tasksByColumn[col.id]?.length"
            class="px-4 py-3 text-sm"
            style="color:var(--fg-muted)"
          >No tasks</div>
          <div
            v-for="(task, i) in tasksByColumn[col.id]"
            :key="task.id"
            class="flex items-center gap-3 px-4 py-3 cursor-pointer transition-colors hover:bg-[var(--bg-hover)]"
            :style="i > 0 ? 'border-top:1px solid var(--border)' : ''"
            @click="openTask(task.id)"
          >
            <PriorityBadge :priority="task.priority" />
            <span class="text-xs font-mono shrink-0" style="color:var(--fg-muted)">{{ task.key }}</span>
            <p
              class="flex-1 text-sm font-medium truncate"
              :class="{ 'line-through opacity-40': task.completed }"
              style="color:var(--fg)"
            >{{ task.title }}</p>
            <div v-if="task.tags?.length" class="flex gap-1">
              <span
                v-for="tag in task.tags.slice(0,2)"
                :key="tag"
                class="text-xs px-1.5 py-0.5 rounded"
                style="background:var(--bg-sunken);color:var(--fg-muted)"
              >{{ tag }}</span>
            </div>
            <Avatar v-if="task.assignee" :name="task.assignee.name" size="sm" />
          </div>
        </div>
      </div>
    </div>

    <!-- Task panel -->
    <TaskPanel
      v-if="activeTask"
      :task="activeTask"
      :columns="columns"
      :allUsers="allUsers"
      :project="project"
      :locked="currentSprint?.locked ?? false"
      @close="closeTask"
      @update="handleTaskUpdate"
      @complete="completeTask"
      @uncomplete="uncompleteTask"
      @delete="deleteTask"
      @comment="postComment"
      @reply="postReply"
      @subtask="addSubtask"
      @subtaskToggle="toggleSubtask"
    />

    <!-- New task panel -->
    <NewTaskPanel
      v-if="showNewTask"
      :show="showNewTask"
      :projectId="project.id"
      :sprintId="currentSprint?.id ?? null"
      :sprints="sprints"
      :columns="columns"
      :defaultColumn="defaultColumnId"
      :allUsers="allUsers"
      @close="showNewTask = false"
    />

    <!-- New sprint modal -->
    <NewSprintModal
      :show="showNewSprint"
      :projectId="project.id"
      @close="showNewSprint = false"
    />

    <!-- Lock sprint modal -->
    <LockSprintModal
      :show="showLockModal"
      :sprint="currentSprint"
      :tasks="tasks"
      @close="showLockModal = false"
    />
  </AppLayout>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import KanbanBoard from '@/Components/Board/KanbanBoard.vue'
import TaskPanel from '@/Components/Task/TaskPanel.vue'
import NewTaskPanel from '@/Components/Task/NewTaskPanel.vue'
import NewSprintModal from '@/Components/Modals/NewSprintModal.vue'
import LockSprintModal from '@/Components/Modals/LockSprintModal.vue'
import DropdownMenu from '@/Components/UI/DropdownMenu.vue'
import MenuItem from '@/Components/UI/MenuItem.vue'
import Avatar from '@/Components/UI/Avatar.vue'
import PriorityBadge from '@/Components/UI/PriorityBadge.vue'
import {
  ChevronIcon, CheckIcon, LockIcon, PlusIcon, BoardIcon, ListIcon,
  LightningIcon, FilterIcon, SearchIcon,
} from '@/Components/UI/Icons.vue'
import { useToast } from '@/composables/useToast'

const props = defineProps({
  project:       { type: Object, required: true },
  currentSprint: { type: Object, default: null },
  isBacklog:     { type: Boolean, default: false },
  sprints:       { type: Array,  default: () => [] },
  columns:       { type: Array,  default: () => [] },
  tasks:         { type: Array,  default: () => [] },
  allUsers:      { type: Array,  default: () => [] },
})

const { toast } = useToast()

const view          = ref('board')
const activeTask    = ref(null)
const showNewTask   = ref(false)
const showNewSprint = ref(false)
const showLockModal = ref(false)
const defaultColumnId = ref(null)
const searchQuery   = ref('')

// Filters
const showFilters     = ref(false)
const filterRef       = ref(null)
const filterAssignees = ref([])
const filterUnassigned = ref(false)
const filterPriorities = ref([])
const filterStatuses  = ref([])

const priorities = [
  { value: 'urgent', label: 'Urgent' },
  { value: 'high',   label: 'High' },
  { value: 'med',    label: 'Medium' },
  { value: 'low',    label: 'Low' },
]

const hasActiveFilters = computed(() =>
  filterAssignees.value.length > 0 ||
  filterUnassigned.value ||
  filterPriorities.value.length > 0 ||
  filterStatuses.value.length > 0
)

function clearFilters() {
  filterAssignees.value = []
  filterUnassigned.value = false
  filterPriorities.value = []
  filterStatuses.value = []
}

// Close filter panel when clicking outside
function onClickOutside(e) {
  if (filterRef.value && !filterRef.value.contains(e.target)) {
    showFilters.value = false
  }
}
onMounted(() => document.addEventListener('mousedown', onClickOutside))
onUnmounted(() => document.removeEventListener('mousedown', onClickOutside))

// Keep activeTask in sync when Inertia refreshes props.tasks
watch(() => props.tasks, (tasks) => {
  if (activeTask.value) {
    const updated = tasks.find(t => t.id === activeTask.value.id)
    if (updated) activeTask.value = updated
  }
})

// Open a task panel
function openTask(idOrObj) {
  const id = typeof idOrObj === 'object' ? idOrObj.id : idOrObj
  const task = props.tasks.find(t => t.id === id) ?? null
  activeTask.value = task
  const url = new URL(window.location.href)
  url.searchParams.set('task', id)
  history.replaceState(null, '', url)
}

function closeTask() {
  activeTask.value = null
  const url = new URL(window.location.href)
  url.searchParams.delete('task')
  history.replaceState(null, '', url)
}

// Open task from URL param on load
const urlTask = new URLSearchParams(window.location.search).get('task')
if (urlTask) {
  const t = props.tasks.find(t => t.id === parseInt(urlTask))
  if (t) activeTask.value = t
}

function openNewTask(columnId) {
  defaultColumnId.value = columnId ?? props.columns[0]?.id ?? null
  showNewTask.value = true
}

// Keyboard shortcut: C = new task
function onKeydown(e) {
  if (e.key === 'c' && !e.metaKey && !e.ctrlKey && !e.altKey) {
    const tag = document.activeElement?.tagName
    if (tag === 'INPUT' || tag === 'TEXTAREA' || document.activeElement?.isContentEditable) return
    if (!props.currentSprint?.locked) openNewTask(null)
  }
}
onMounted(() => document.addEventListener('keydown', onKeydown))
onUnmounted(() => document.removeEventListener('keydown', onKeydown))

// Sprint date range display
const sprintDateRange = computed(() => {
  if (!props.currentSprint) return ''
  const fmt = (d) => {
    if (!d) return ''
    const date = new Date(d)
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
  }
  const s = fmt(props.currentSprint.start_date)
  const e = fmt(props.currentSprint.end_date)
  return s && e ? `${s} – ${e}` : s || e
})

const daysRemaining = computed(() => {
  if (!props.currentSprint?.end_date) return null
  const end = new Date(props.currentSprint.end_date)
  end.setHours(23, 59, 59, 999)
  const now = new Date()
  const diff = Math.ceil((end - now) / (1000 * 60 * 60 * 24))
  return diff
})

// Filtered tasks/columns based on search + filters
const filteredTasks = computed(() => {
  let tasks = props.tasks

  // Search
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase()
    tasks = tasks.filter(t =>
      t.title.toLowerCase().includes(q) ||
      t.key?.toLowerCase().includes(q)
    )
  }

  // Assignee filter
  if (filterAssignees.value.length > 0 || filterUnassigned.value) {
    tasks = tasks.filter(t => {
      if (filterUnassigned.value && !t.assignee_id) return true
      if (filterAssignees.value.includes(t.assignee_id)) return true
      return false
    })
  }

  // Priority filter
  if (filterPriorities.value.length > 0) {
    tasks = tasks.filter(t => filterPriorities.value.includes(t.priority))
  }

  // Status filter
  if (filterStatuses.value.length > 0) {
    tasks = tasks.filter(t => {
      if (filterStatuses.value.includes('completed') && t.completed) return true
      if (filterStatuses.value.includes('open') && !t.completed) return true
      return false
    })
  }

  return tasks
})

const filteredColumns = computed(() => props.columns)

const tasksByColumn = computed(() => {
  const m = {}
  props.columns.forEach(c => { m[c.id] = [] })
  filteredTasks.value.forEach(t => {
    if (t.board_column_id && m[t.board_column_id]) m[t.board_column_id].push(t)
  })
  return m
})

const sprintBadgeStyle = computed(() => {
  const s = props.currentSprint?.status
  if (s === 'active')    return 'background:var(--status-active-bg);color:var(--status-active-fg)'
  if (s === 'completed') return 'background:var(--status-done-bg);color:var(--status-done-fg)'
  return 'background:var(--bg-sunken);color:var(--fg-muted)'
})

function switchSprint(id) {
  router.get(route('projects.show', props.project.id), { sprint: id }, { preserveScroll: false })
}

function switchToBacklog() {
  router.get(route('projects.show', props.project.id), { backlog: '1' }, { preserveScroll: false })
}

function unlockSprint() {
  router.post(route('sprints.unlock', props.currentSprint.id), {}, { preserveScroll: true })
}

function moveTask(taskId, columnId) {
  router.post(route('tasks.move', taskId), { board_column_id: columnId }, {
    preserveScroll: true,
    onSuccess: () => {
      if (activeTask.value?.id === taskId) {
        activeTask.value = { ...activeTask.value, board_column_id: columnId }
      }
    },
  })
}

function handleTaskUpdate(data) {
  router.patch(route('tasks.update', activeTask.value.id), data, {
    preserveScroll: true,
  })
}

function completeTask() {
  router.post(route('tasks.complete', activeTask.value.id), {}, {
    preserveScroll: true,
    onSuccess: () => toast('Task marked complete'),
  })
}

function uncompleteTask() {
  router.post(route('tasks.uncomplete', activeTask.value.id), {}, {
    preserveScroll: true,
    onSuccess: () => toast('Task reopened'),
  })
}

function deleteTask() {
  const id = activeTask.value.id
  activeTask.value = null
  router.delete(route('tasks.destroy', id), {
    preserveScroll: true,
    onSuccess: () => toast('Task deleted'),
  })
}

function postComment(body) {
  router.post(route('tasks.comments.store', activeTask.value.id), { body }, { preserveScroll: true })
}

function postReply(commentId, body) {
  router.post(route('tasks.comments.reply', [activeTask.value.id, commentId]), { body }, { preserveScroll: true })
}

function addSubtask(data) {
  router.post(route('tasks.subtasks.store', activeTask.value.id), data, { preserveScroll: true })
}

function toggleSubtask(subtaskId, completed) {
  const routeName = completed ? 'tasks.complete' : 'tasks.uncomplete'
  router.post(route(routeName, subtaskId), {}, { preserveScroll: true })
}

function addColumn(name) {
  router.post(route('columns.store', props.project.id), { name }, { preserveScroll: true })
}

function renameColumn(columnId, name) {
  router.patch(route('columns.update', columnId), { name }, { preserveScroll: true })
}

function deleteColumn(columnId) {
  router.delete(route('columns.destroy', columnId), { preserveScroll: true })
}
</script>

<style scoped>
.sprint-btn {
  display: inline-flex;
  align-items: center;
  height: 28px;
  padding: 0 8px;
  border-radius: 6px;
  border: 1px solid var(--border);
  background: var(--bg-panel);
  color: var(--fg);
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  gap: 5px;
  transition: background 80ms;
}
.sprint-btn:hover { background: var(--bg-hover); }

.header-btn {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  height: 28px;
  padding: 0 10px;
  border-radius: 6px;
  border: 1px solid var(--border);
  background: transparent;
  color: var(--fg-muted);
  font-size: 12px;
  font-weight: 500;
  cursor: pointer;
  transition: background 80ms;
}
.header-btn:hover { background: var(--bg-hover); color: var(--fg); }

.view-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 5px;
  padding: 0 10px;
  height: 28px;
  background: transparent;
  border: none;
  color: var(--fg-muted);
  font-size: 12px;
  font-weight: 500;
  cursor: pointer;
  transition: background 80ms, color 80ms;
  white-space: nowrap;
}
.view-btn:hover { background: var(--bg-hover); color: var(--fg); }
.view-btn.active { background: var(--bg-active); color: var(--fg); }

.btn-primary {
  background: var(--accent);
  color: var(--accent-fg);
  border: none;
  font-weight: 500;
  cursor: pointer;
  transition: background 80ms;
}
.btn-primary:hover { background: var(--accent-hover); }

.header-btn--active {
  border-color: var(--accent);
  color: var(--accent);
}
.filter-dot {
  width: 6px; height: 6px; border-radius: 50%;
  background: var(--accent); flex-shrink: 0;
}

.filter-panel {
  position: absolute;
  top: calc(100% + 6px);
  right: 0;
  z-index: 100;
  width: 240px;
  background: var(--bg-panel);
  border: 1px solid var(--border);
  border-radius: var(--r-lg);
  box-shadow: var(--shadow-lg);
  padding: 8px 0;
}
.filter-section { padding: 6px 12px; }
.filter-section-label {
  font-size: 11px; font-weight: 600; text-transform: uppercase;
  letter-spacing: 0.05em; color: var(--fg-muted);
  margin: 0 0 6px;
}
.filter-options { display: flex; flex-direction: column; gap: 2px; }
.filter-option {
  display: flex; align-items: center; gap: 8px;
  padding: 4px 6px; border-radius: var(--r-sm);
  cursor: pointer; font-size: 13px; color: var(--fg);
  transition: background 80ms;
}
.filter-option:hover { background: var(--bg-hover); }
.filter-check {
  width: 14px; height: 14px; border-radius: 3px;
  accent-color: var(--accent); cursor: pointer; flex-shrink: 0;
}
.filter-avatar-placeholder {
  width: 20px; height: 20px; border-radius: 50%;
  background: var(--bg-sunken); border: 1px solid var(--border);
  font-size: 10px; color: var(--fg-muted);
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.status-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.filter-divider { height: 1px; background: var(--border); margin: 4px 0; }
.filter-clear {
  width: calc(100% - 24px); margin: 4px 12px 2px;
  padding: 6px; border-radius: var(--r-sm);
  border: none; background: none;
  font-size: 12px; color: var(--status-blocked);
  cursor: pointer; text-align: left;
  transition: background 80ms;
}
.filter-clear:hover { background: var(--bg-hover); }
</style>
