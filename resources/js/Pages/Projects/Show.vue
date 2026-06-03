<template>
  <AppLayout>
    <!-- Top bar: project name + search + new task -->
    <div class="topbar">
      <div class="crumbs">
        <span class="crumb-dot" :style="{ background: project.color }" />
        <span class="crumb-current">{{ project.name }}</span>
      </div>

      <!-- Search -->
      <div class="search-wrap">
        <SearchIcon class="search-icon" />
        <input
          v-model="searchQuery"
          class="search-input"
          placeholder="Search tasks…"
        />
      </div>

      <!-- New task button -->
      <button
        v-if="!currentSprint?.locked || isBacklog"
        type="button"
        class="btn-secondary"
        @click="openNewTask(null)"
      >
        <PlusIcon style="width:14px;height:14px" /> New task
        <span class="kbd-chip">C</span>
      </button>
    </div>

    <!-- Sprint header bar -->
    <div class="sprint-header-row">
      <!-- Sprint picker -->
      <DropdownMenu :width="280">
        <template #trigger>
          <button type="button" class="sprint-picker-btn">
            <component
              :is="isAll ? ListIcon : isBacklog ? InboxIcon : LightningIcon"
              :style="{ color: isAll || isBacklog ? 'var(--fg-muted)' : currentSprint?.locked ? 'var(--status-progress)' : 'var(--fg-muted)' }"
            />
            <span class="sprint-label">{{ isAll ? 'All sprints' : isBacklog ? 'Backlog' : (currentSprint?.name ?? 'No sprint') }}</span>
            <span
              v-if="currentSprint?.locked && !isAll && !isBacklog"
              class="lock-pill-badge"
            ><LockIcon style="width:11px;height:11px" /> Locked</span>
            <ChevronIcon style="color:var(--fg-subtle);margin-left:auto" />
          </button>
        </template>

        <div class="sprint-dd-label">Filter by sprint</div>

        <MenuItem @click="switchToAll">
          <span class="sprint-dd-check"><CheckIcon v-if="isAll" style="width:14px;height:14px;color:var(--accent)" /></span>
          <ListIcon />
          <span style="flex:1">All sprints</span>
          <span class="sprint-dd-count">{{ tasks.length }}</span>
        </MenuItem>

        <MenuItem @click="switchToBacklog">
          <span class="sprint-dd-check"><CheckIcon v-if="isBacklog" style="width:14px;height:14px;color:var(--accent)" /></span>
          <InboxIcon />
          <span style="flex:1">Backlog <span style="color:var(--fg-muted);font-weight:400">(no sprint)</span></span>
          <span class="sprint-dd-count">{{ backlogCount }}</span>
        </MenuItem>

        <template v-if="sprints.length">
          <div class="sprint-dd-divider" />
          <div class="sprint-dd-label">Sprints in {{ project.name }}</div>
          <MenuItem v-for="s in sprints" :key="s.id" @click="switchSprint(s.id)">
            <span class="sprint-dd-check"><CheckIcon v-if="s.id === currentSprint?.id && !isAll && !isBacklog" style="width:14px;height:14px;color:var(--accent)" /></span>
            <LightningIcon />
            <span style="flex:1">{{ s.name }}</span>
            <LockIcon v-if="s.locked" style="width:11px;height:11px;color:var(--fg-subtle);flex-shrink:0" />
            <span v-if="s.start_date || s.end_date" class="sprint-dd-count">{{ fmtSprintDates(s) }}</span>
          </MenuItem>
        </template>

        <template v-if="canManageSprints">
          <div class="sprint-dd-divider" />
          <MenuItem @click="showNewSprint = true">
            <PlusIcon />
            <span>New sprint</span>
          </MenuItem>
        </template>
      </DropdownMenu>

      <!-- Sprint meta: dates + days remaining -->
      <div v-if="currentSprint && !isAll && !isBacklog" class="sprint-meta-row">
        <span>{{ sprintDateRange }}</span>
        <span v-if="daysRemaining !== null" class="days-pill" :style="daysRemainingStyle">
          {{ daysRemainingLabel }}
        </span>
      </div>
      <span v-if="isBacklog" class="sprint-meta-text">Tasks not assigned to a sprint</span>
      <span v-if="isAll" class="sprint-meta-text">Every task in {{ project.name }}</span>

      <!-- Filters -->
      <div class="relative" ref="filterRef">
        <button
          type="button"
          class="filter-trigger-btn"
          @click="showFilters = !showFilters"
        >
          <FilterIcon style="width:14px;height:14px" /> Filters
          <span v-if="activeFilterCount > 0" class="filter-count-badge">{{ activeFilterCount }}</span>
        </button>

        <div v-if="showFilters" class="filter-panel">
          <!-- Assignee -->
          <div class="filter-panel-label">Assignee</div>
          <button type="button" class="filter-menu-item" @click="filterUnassigned = !filterUnassigned">
            <CheckIcon v-if="filterUnassigned" style="width:14px;height:14px;color:var(--accent);flex-shrink:0" />
            <span v-else style="width:14px;flex-shrink:0;display:inline-block" />
            <span class="filter-avatar-placeholder">?</span>
            <span>Unassigned</span>
          </button>
          <button v-for="u in allUsers" :key="u.id" type="button" class="filter-menu-item" @click="toggleFilter(filterAssignees, u.id)">
            <CheckIcon v-if="filterAssignees.includes(u.id)" style="width:14px;height:14px;color:var(--accent);flex-shrink:0" />
            <span v-else style="width:14px;flex-shrink:0;display:inline-block" />
            <Avatar :name="u.name" size="sm" />
            <span>{{ u.name }}</span>
          </button>

          <div class="filter-divider" />

          <!-- Priority -->
          <div class="filter-panel-label">Priority</div>
          <button v-for="p in priorities" :key="p.value" type="button" class="filter-menu-item" @click="toggleFilter(filterPriorities, p.value)">
            <CheckIcon v-if="filterPriorities.includes(p.value)" style="width:14px;height:14px;color:var(--accent);flex-shrink:0" />
            <span v-else style="width:14px;flex-shrink:0;display:inline-block" />
            <PriorityBadge :priority="p.value" />
            <span>{{ p.label }}</span>
          </button>

          <template v-if="availableTags.length">
            <div class="filter-divider" />
            <div class="filter-panel-label">Tags</div>
            <button v-for="tag in availableTags" :key="tag" type="button" class="filter-menu-item" @click="toggleFilter(filterTags, tag)">
              <CheckIcon v-if="filterTags.includes(tag)" style="width:14px;height:14px;color:var(--accent);flex-shrink:0" />
              <span v-else style="width:14px;flex-shrink:0;display:inline-block" />
              <span class="filter-tag-chip">{{ tag }}</span>
            </button>
          </template>

          <div class="filter-divider" />

          <!-- Status -->
          <div class="filter-panel-label">Status</div>
          <button type="button" class="filter-menu-item" @click="filterHideCompleted = !filterHideCompleted">
            <CheckIcon v-if="filterHideCompleted" style="width:14px;height:14px;color:var(--accent);flex-shrink:0" />
            <span v-else style="width:14px;flex-shrink:0;display:inline-block" />
            <span style="flex:1">Hide completed tasks</span>
          </button>
          <button type="button" class="filter-menu-item" @click="toggleFilter(filterStatuses, 'open')">
            <CheckIcon v-if="filterStatuses.includes('open')" style="width:14px;height:14px;color:var(--accent);flex-shrink:0" />
            <span v-else style="width:14px;flex-shrink:0;display:inline-block" />
            <span class="status-dot" style="background:#6366f1" />
            <span>Open</span>
          </button>
          <button type="button" class="filter-menu-item" @click="toggleFilter(filterStatuses, 'completed')">
            <CheckIcon v-if="filterStatuses.includes('completed')" style="width:14px;height:14px;color:var(--accent);flex-shrink:0" />
            <span v-else style="width:14px;flex-shrink:0;display:inline-block" />
            <span class="status-dot" style="background:var(--status-done)" />
            <span>Completed</span>
          </button>

          <template v-if="hasActiveFilters">
            <div class="filter-divider" />
            <button type="button" class="filter-menu-item" style="color:var(--status-blocked)" @click="clearFilters">
              Clear all filters
            </button>
          </template>
        </div>
      </div>

      <span style="flex:1" />

      <!-- View toggle -->
      <div class="view-toggle">
        <button
          type="button"
          class="view-toggle-btn"
          :class="{ active: view === 'board' }"
          @click="view = 'board'"
        ><BoardIcon style="width:14px;height:14px" /> Board</button>
        <button
          type="button"
          class="view-toggle-btn"
          :class="{ active: view === 'list' }"
          @click="view = 'list'"
        ><ListIcon style="width:14px;height:14px" /> List</button>
      </div>

      <!-- Sprint management — workspace owners/admins only -->
      <template v-if="canManageSprints">
        <!-- Lock / Unlock sprint (hidden once sprint is completed) -->
        <template v-if="currentSprint && !isAll && !isBacklog && currentSprint.status !== 'completed' && !currentSprint.locked">
          <button type="button" class="btn-secondary" @click="showLockModal = true">
            <LockIcon style="width:14px;height:14px" /> Lock sprint
          </button>
        </template>
        <template v-else-if="currentSprint && !isAll && !isBacklog && currentSprint.status !== 'completed' && currentSprint.locked">
          <button type="button" class="btn-secondary" @click="unlockSprint">
            <LockIcon style="width:14px;height:14px" /> Unlock
          </button>
        </template>

        <!-- Complete / Reopen sprint -->
        <button
          v-if="currentSprint && !isAll && !isBacklog && currentSprint.status !== 'completed'"
          type="button"
          class="btn-primary"
          title="Mark this sprint as completed"
          @click="completeSprint"
        >
          <CheckIcon style="width:14px;height:14px" /> Complete sprint
        </button>
        <button
          v-else-if="currentSprint && !isAll && !isBacklog && currentSprint.status === 'completed'"
          type="button"
          class="btn-secondary"
          @click="reopenSprint"
        >
          <LightningIcon style="width:14px;height:14px" /> Reopen sprint
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
    <div v-if="view === 'board'" class="flex-1 overflow-hidden flex flex-col">
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
      :allProjects="allProjects"
      :allSprints="sprints"
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
      @subtaskRemove="removeSubtask"
      @subtaskUpdate="handleSubtaskUpdate"
      @subtaskComment="postSubtaskComment"
      @subtaskReply="postSubtaskReply"
      @attachmentUpload="uploadAttachment"
      @attachmentRemove="removeAttachment"
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
      v-if="canManageSprints"
      :show="showNewSprint"
      :projectId="project.uuid"
      @close="showNewSprint = false"
    />

    <!-- Lock sprint modal -->
    <LockSprintModal
      v-if="canManageSprints"
      :show="showLockModal"
      :sprint="currentSprint"
      :tasks="tasks"
      @close="showLockModal = false"
    />
  </AppLayout>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import axios from 'axios'
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
  LightningIcon, FilterIcon, SearchIcon, InboxIcon,
} from '@/Components/UI/Icons.vue'
import { useToast } from '@/composables/useToast'

const props = defineProps({
  project:       { type: Object, required: true },
  currentSprint: { type: Object, default: null },
  isBacklog:     { type: Boolean, default: false },
  isAll:         { type: Boolean, default: false },
  sprints:       { type: Array,  default: () => [] },
  columns:       { type: Array,  default: () => [] },
  tasks:         { type: Array,  default: () => [] },
  allUsers:      { type: Array,  default: () => [] },
  allProjects:   { type: Array,  default: () => [] },
  savedFilters:  {
    type: Object,
    default: () => ({
      sprint_ids: [], assignee_ids: [], priorities: [], status_ids: [],
      statuses: [], hide_completed: false, unassigned: false,
    }),
  },
})

const { toast } = useToast()
const page = usePage()

// Sprint management (create/lock/unlock/complete/reopen) is reserved for
// workspace owners and admins — mirrors the SprintController authorization.
const canManageSprints = computed(() =>
  ['owner', 'admin'].includes(page.props.workspace?.role)
)

// Local tasks mirror — updated optimistically on drag-drop so the board
// doesn't blink while waiting for the Inertia round-trip.
const localTasks = ref([...props.tasks])

const view          = ref('board')
const activeTask    = ref(null)
const showNewTask   = ref(false)
const showNewSprint = ref(false)
const showLockModal = ref(false)
const defaultColumnId = ref(null)
const searchQuery   = ref('')

// Filters — initialized from persisted savedFilters prop
const showFilters         = ref(false)
const filterRef           = ref(null)
const filterAssignees     = ref([...(props.savedFilters.assignee_ids ?? [])])
const filterUnassigned    = ref(props.savedFilters.unassigned ?? false)
const filterPriorities    = ref([...(props.savedFilters.priorities ?? [])])
const filterStatuses      = ref([...(props.savedFilters.statuses ?? [])])
const filterTags          = ref([])
const filterHideCompleted = ref(props.savedFilters.hide_completed ?? false)

function debounce(fn, ms) {
  let t; return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms) }
}

const persistFilters = debounce(() => {
  axios.put(`/projects/${props.project.id}/filters`, {
    assignee_ids:   filterAssignees.value,
    priorities:     filterPriorities.value,
    statuses:       filterStatuses.value,
    hide_completed: filterHideCompleted.value,
    unassigned:     filterUnassigned.value,
  })
}, 600)

watch(
  [filterAssignees, filterUnassigned, filterPriorities, filterStatuses, filterHideCompleted],
  persistFilters,
  { deep: true },
)

const priorities = [
  { value: 'urgent', label: 'Urgent' },
  { value: 'high',   label: 'High' },
  { value: 'med',    label: 'Medium' },
  { value: 'low',    label: 'Low' },
]

// Collect all unique tags across tasks for the filter panel
const availableTags = computed(() => {
  const set = new Set()
  localTasks.value.forEach(t => t.tags?.forEach(tag => set.add(tag)))
  return Array.from(set).sort()
})

const activeFilterCount = computed(() =>
  filterAssignees.value.length +
  (filterUnassigned.value ? 1 : 0) +
  filterPriorities.value.length +
  filterStatuses.value.length +
  filterTags.value.length +
  (filterHideCompleted.value ? 1 : 0)
)

const hasActiveFilters = computed(() => activeFilterCount.value > 0)

const backlogCount = computed(() => localTasks.value.filter(t => !t.sprint_id).length)

function clearFilters() {
  filterAssignees.value     = []
  filterUnassigned.value    = false
  filterPriorities.value    = []
  filterStatuses.value      = []
  filterTags.value          = []
  filterHideCompleted.value = false
}

function toggleFilter(arr, val) {
  const idx = arr.indexOf(val)
  if (idx >= 0) arr.splice(idx, 1)
  else arr.push(val)
}

// Close filter panel when clicking outside
function onClickOutside(e) {
  if (filterRef.value && !filterRef.value.contains(e.target)) {
    showFilters.value = false
  }
}
onMounted(() => document.addEventListener('mousedown', onClickOutside))
onUnmounted(() => document.removeEventListener('mousedown', onClickOutside))

// Real-time updates via Reverb
//
// TaskUpdated broadcasts a slim task payload (assignee/assignees/board_column/
// sprint at most). Rich relations like activities/comments/replies/subtasks are
// not included, so blindly replacing the local task wipes them on the receiver
// — including any activity that TaskActivityRecorded just appended (it fires
// immediately before TaskUpdated in TaskService::update). mergeTask preserves
// those collections instead of letting them go undefined.
function mergeTask(existing, incoming) {
  if (!existing) return incoming
  return {
    ...existing,
    ...incoming,
    activities: incoming.activities ?? existing.activities ?? [],
    comments:   incoming.comments   ?? existing.comments   ?? [],
    replies:    incoming.replies    ?? existing.replies    ?? [],
    subtasks:   incoming.subtasks   ?? existing.subtasks   ?? [],
  }
}

onMounted(() => {
  window.Echo.private(`project.${props.project.id}`)
    .listen('TaskUpdated', ({ task }) => {
      if (task.parent_task_id) {
        if (activeTask.value?.id === task.id) {
          activeTask.value = mergeTask(activeTask.value, task)
        }
        return
      }
      const idx = localTasks.value.findIndex(t => t.id === task.id)
      const belongs = props.isAll
        || (props.isBacklog && !task.sprint_id)
        || (!!props.currentSprint && task.sprint_id === props.currentSprint.id)
      if (idx >= 0 && !belongs) {
        localTasks.value.splice(idx, 1)
      } else if (idx < 0 && belongs) {
        localTasks.value.push(task)
      } else if (idx >= 0) {
        localTasks.value[idx] = mergeTask(localTasks.value[idx], task)
      }
      if (activeTask.value?.id === task.id) {
        activeTask.value = mergeTask(activeTask.value, task)
      }
    })
    .listen('TaskCreated', ({ task }) => {
      if (!localTasks.value.find(t => t.id === task.id)) localTasks.value.push(task)
    })
    .listen('TaskDeleted', ({ task_id }) => {
      localTasks.value = localTasks.value.filter(t => t.id !== task_id)
      if (activeTask.value?.id === task_id) closeTask()
    })
    .listen('TaskActivityRecorded', ({ activity }) => {
      // Append the activity to whichever local task (or its open panel copy)
      // owns it, so the Activity tab updates live for other viewers.
      const task = localTasks.value.find(t => t.id === activity.task_id)
      if (task) {
        task.activities = task.activities ?? []
        if (!task.activities.find(a => a.id === activity.id)) {
          task.activities.push(activity)
        }
      }
      if (activeTask.value?.id === activity.task_id) {
        activeTask.value.activities = activeTask.value.activities ?? []
        if (!activeTask.value.activities.find(a => a.id === activity.id)) {
          activeTask.value.activities.push(activity)
        }
      }
    })
    .listen('CommentAdded', ({ task_id, comment }) => {
      // Append the comment to the local task and active panel so the
      // Comments tab and the derived Participants list update live.
      // TaskPanel watches task.comments.length and reloads participants
      // from the server, so we don't need to touch participants here.
      const appendComment = (owner) => {
        owner.comments = owner.comments ?? []
        if (!owner.comments.find(c => c.id === comment.id)) {
          owner.comments.push(comment)
        }
      }
      // task_id may be a top-level task OR a subtask. Subtasks aren't in
      // localTasks (top-level only), so also look inside each task's subtasks.
      const findOwner = (task) => {
        if (!task) return null
        if (task.id === task_id) return task
        return (task.subtasks ?? []).find(s => s.id === task_id) ?? null
      }
      const localOwner = findOwner(localTasks.value.find(t => t.id === task_id))
        ?? localTasks.value.map(findOwner).find(Boolean)
      if (localOwner) appendComment(localOwner)
      const activeOwner = findOwner(activeTask.value)
      if (activeOwner) appendComment(activeOwner)
    })
    .listen('ReplyAdded', ({ task_id, comment_id, reply }) => {
      const appendReply = (task) => {
        if (!task) return
        const comment = (task.comments ?? []).find(c => c.id === comment_id)
        if (!comment) return
        comment.replies = comment.replies ?? []
        if (!comment.replies.find(r => r.id === reply.id)) {
          comment.replies.push(reply)
        }
      }
      // task_id may be a subtask id — replies live on the subtask itself, which
      // is nested under its parent, so look one level down as well.
      const findTask = (root) => {
        if (!root) return null
        if (root.id === task_id) return root
        return (root.subtasks ?? []).find(s => s.id === task_id) ?? null
      }
      localTasks.value.forEach(t => appendReply(findTask(t)))
      appendReply(findTask(activeTask.value))
    })
    // Board structure changed elsewhere — a column was added/renamed/removed,
    // or a sprint was created/locked/unlocked/completed/reopened. Re-pull just
    // the affected board props instead of mutating the grid by hand;
    // preserveState keeps the open panel, scroll position, and filters intact.
    .listen('BoardColumnUpdated', () => {
      router.reload({ only: ['columns'], preserveScroll: true, preserveState: true })
    })
    .listen('SprintUpdated', () => {
      router.reload({ only: ['sprints', 'currentSprint', 'tasks'], preserveScroll: true, preserveState: true })
    })
})
onUnmounted(() => window.Echo.leave(`project.${props.project.id}`))

// Keep localTasks + activeTask in sync when Inertia refreshes props.tasks
watch(() => props.tasks, (tasks) => {
  localTasks.value = tasks
  if (activeTask.value) {
    const updated = tasks.find(t => t.id === activeTask.value.id)
    if (updated) activeTask.value = updated
  }
})

// Open a task panel. The deep-link in the address bar carries the task's uuid,
// never its integer id.
function openTask(idOrObj) {
  const id = typeof idOrObj === 'object' ? idOrObj.id : idOrObj
  const task = localTasks.value.find(t => t.id === id) ?? null
  activeTask.value = task
  const url = new URL(window.location.href)
  url.searchParams.set('task', task?.uuid ?? id)
  history.replaceState(null, '', url)
}

function closeTask() {
  activeTask.value = null
  const url = new URL(window.location.href)
  url.searchParams.delete('task')
  history.replaceState(null, '', url)
}

// Open task from URL param on load (matched by uuid).
const urlTask = new URLSearchParams(window.location.search).get('task')
if (urlTask) {
  const t = localTasks.value.find(t => t.uuid === urlTask)
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

const daysRemainingLabel = computed(() => {
  const d = daysRemaining.value
  if (d === null) return ''
  if (d > 0) return `${d}d remaining`
  if (d === 0) return 'Ends today'
  return 'Ended'
})

const daysRemainingStyle = computed(() => {
  const d = daysRemaining.value
  if (d === null) return ''
  if (d <= 0) return 'background:color-mix(in oklab,#ef4444 12%,transparent);color:#ef4444'
  if (d <= 3) return 'background:color-mix(in oklab,#f59e0b 12%,transparent);color:#d97706'
  return 'background:var(--bg-active);color:var(--fg-muted)'
})

function fmtSprintDates(s) {
  const fmt = d => d ? new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) : ''
  const start = fmt(s.start_date)
  const end = fmt(s.end_date)
  return start && end ? `${start} – ${end}` : start || end
}

// Filtered tasks/columns based on search + filters
const filteredTasks = computed(() => {
  let tasks = localTasks.value

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

  // Tags filter
  if (filterTags.value.length > 0) {
    tasks = tasks.filter(t =>
      filterTags.value.some(tag => t.tags?.includes(tag))
    )
  }

  // Hide completed filter
  if (filterHideCompleted.value) {
    tasks = tasks.filter(t => !t.completed)
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

// URLs/routes expose the UUIDv7 of each resource, never its integer id. Child
// components still emit integer ids (used for local state matching, drag/drop,
// and request bodies like board_column_id), so these helpers map an integer id
// to the matching uuid for the route() argument only.
const findTaskUuid    = (id) => localTasks.value.find(t => t.id === id)?.uuid ?? id
const findSubtaskUuid = (id) => activeTask.value?.subtasks?.find(s => s.id === id)?.uuid ?? id
const findColumnUuid  = (id) => props.columns?.find(c => c.id === id)?.uuid ?? id
const findSprintUuid  = (id) => props.sprints?.find(s => s.id === id)?.uuid ?? id

function switchSprint(id) {
  router.get(route('projects.show', props.project.uuid), { sprint: findSprintUuid(id) }, { preserveScroll: false })
}

function switchToBacklog() {
  router.get(route('projects.show', props.project.uuid), { backlog: '1' }, { preserveScroll: false })
}

function switchToAll() {
  router.get(route('projects.show', props.project.uuid), { all: '1' }, { preserveScroll: false })
}

function unlockSprint() {
  router.post(route('sprints.unlock', props.currentSprint.uuid), {}, { preserveScroll: true })
}

function completeSprint() {
  router.post(route('sprints.complete', props.currentSprint.uuid), {}, {
    preserveScroll: true,
    onSuccess: () => toast(`${props.currentSprint.name} marked complete`),
  })
}

function reopenSprint() {
  router.post(route('sprints.reopen', props.currentSprint.uuid), {}, {
    preserveScroll: true,
    onSuccess: () => toast(`${props.currentSprint.name} reopened`),
  })
}

function moveTask(taskId, columnId) {
  // Optimistic: update local copy immediately so board doesn't blink
  const prevColumnId = localTasks.value.find(t => t.id === taskId)?.board_column_id
  const task = localTasks.value.find(t => t.id === taskId)
  if (task) task.board_column_id = columnId

  router.post(route('tasks.move', findTaskUuid(taskId)), { board_column_id: columnId }, {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => {
      if (activeTask.value?.id === taskId) {
        activeTask.value = { ...activeTask.value, board_column_id: columnId }
      }
    },
    onError: () => {
      // Revert optimistic update on failure
      if (task) task.board_column_id = prevColumnId
    },
  })
}

function handleTaskUpdate(data) {
  router.patch(route('tasks.update', activeTask.value.uuid), data, {
    preserveScroll: true,
  })
}

function completeTask() {
  router.post(route('tasks.complete', activeTask.value.uuid), {}, {
    preserveScroll: true,
    onSuccess: () => toast('Task marked complete'),
  })
}

function uncompleteTask() {
  router.post(route('tasks.uncomplete', activeTask.value.uuid), {}, {
    preserveScroll: true,
    onSuccess: () => toast('Task reopened'),
  })
}

function deleteTask() {
  const uuid = activeTask.value.uuid
  activeTask.value = null
  router.delete(route('tasks.destroy', uuid), {
    preserveScroll: true,
    onSuccess: () => toast('Task deleted'),
  })
}

function postComment(body) {
  router.post(route('tasks.comments.store', activeTask.value.uuid), { body }, { preserveScroll: true })
}

function postSubtaskComment(subtaskId, body) {
  router.post(route('tasks.comments.store', findSubtaskUuid(subtaskId)), { body }, { preserveScroll: true })
}

function postSubtaskReply(subtaskId, commentId, body) {
  router.post(route('tasks.comments.reply', [findSubtaskUuid(subtaskId), commentId]), { body }, { preserveScroll: true })
}

function postReply(commentId, body) {
  router.post(route('tasks.comments.reply', [activeTask.value.uuid, commentId]), { body }, { preserveScroll: true })
}

function addSubtask(data) {
  router.post(route('tasks.subtasks.store', activeTask.value.uuid), data, { preserveScroll: true })
}

function toggleSubtask(subtaskId, completed) {
  const routeName = completed ? 'tasks.complete' : 'tasks.uncomplete'
  router.post(route(routeName, findSubtaskUuid(subtaskId)), {}, { preserveScroll: true })
}

function removeSubtask(subtaskId) {
  router.delete(route('tasks.destroy', findSubtaskUuid(subtaskId)), { preserveScroll: true })
}

function handleSubtaskUpdate(subtaskId, data) {
  router.patch(route('tasks.subtasks.update', [activeTask.value.uuid, findSubtaskUuid(subtaskId)]), data, {
    preserveScroll: true,
  })
}

function uploadAttachment(file) {
  const form = new FormData()
  form.append('file', file)
  router.post(route('tasks.attachments.store', activeTask.value.uuid), form, { preserveScroll: true })
}

function removeAttachment(attachmentId) {
  router.delete(route('attachments.destroy', attachmentId), { preserveScroll: true })
}

function addColumn(name) {
  router.post(route('columns.store', props.project.uuid), { name }, { preserveScroll: true })
}

function renameColumn(columnId, name) {
  router.patch(route('columns.update', findColumnUuid(columnId)), { name }, { preserveScroll: true })
}

function deleteColumn(columnId) {
  router.delete(route('columns.destroy', findColumnUuid(columnId)), { preserveScroll: true })
}
</script>

<style scoped>
/* ── Topbar ── */
.topbar {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 20px;
  border-bottom: 1px solid var(--border);
  background: var(--bg-app);
  min-height: 48px;
  flex-shrink: 0;
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
.crumb-dot {
  width: 10px; height: 10px;
  border-radius: 3px;
  flex-shrink: 0;
  display: inline-block;
}
.crumb-current { color: var(--fg); font-weight: 500; }

.search-wrap {
  position: relative;
  width: 280px;
  flex-shrink: 0;
}
.search-icon {
  position: absolute;
  left: 8px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--fg-subtle);
  width: 14px; height: 14px;
  pointer-events: none;
}
.search-input {
  padding-left: 28px;
  height: 28px;
  width: 100%;
  border: 1px solid var(--border);
  background: var(--bg-panel);
  border-radius: 6px;
  font-size: 13px;
  font-family: inherit;
  color: var(--fg);
  outline: none;
  transition: border-color 80ms, box-shadow 80ms;
}
.search-input:focus {
  border-color: var(--accent);
  box-shadow: 0 0 0 3px var(--accent-soft);
}
.search-input::placeholder { color: var(--fg-subtle); }

/* Shared secondary button style (topbar new-task + sprint lock/unlock) */
.btn-secondary {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 0 12px;
  height: 28px;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  border: 1px solid var(--border);
  background: var(--bg-panel);
  color: var(--fg);
  white-space: nowrap;
  transition: background 80ms;
  flex-shrink: 0;
}
.btn-secondary:hover { background: var(--bg-hover); }

/* Primary topbar action (Complete sprint) */
.btn-primary {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 0 12px;
  height: 28px;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  border: 1px solid var(--accent);
  background: var(--accent);
  color: var(--accent-fg);
  white-space: nowrap;
  transition: background 80ms;
  flex-shrink: 0;
}
.btn-primary:hover { background: var(--accent-hover); }

.kbd-chip {
  font-family: var(--font-mono);
  font-size: 11px;
  padding: 1px 5px;
  border-radius: 3px;
  background: var(--bg-hover);
  color: var(--fg-muted);
  border: 1px solid var(--border);
  border-bottom-width: 2px;
}

/* ── Sprint header ── */
.sprint-header-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 20px;
  border-bottom: 1px solid var(--border);
  background: var(--bg-app);
  flex-shrink: 0;
}

.sprint-picker-btn {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  height: 32px;
  padding: 0 8px;
  border-radius: 6px;
  border: 1px solid transparent;
  background: transparent;
  color: var(--fg-muted);
  font-size: 13px;
  font-family: inherit;
  cursor: pointer;
  transition: background 80ms;
}
.sprint-picker-btn:hover { background: var(--bg-hover); color: var(--fg); }

.sprint-label {
  font-weight: 600;
  font-size: 15px;
  color: var(--fg);
}

.lock-pill-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
  font-weight: 500;
  padding: 2px 8px;
  border-radius: 999px;
  background: color-mix(in oklab, var(--status-progress) 14%, var(--bg-panel));
  color: var(--status-progress);
  border: 1px solid color-mix(in oklab, var(--status-progress) 30%, var(--border));
}

.sprint-meta-row {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: var(--fg-muted);
}
.sprint-meta-text {
  font-size: 12px;
  color: var(--fg-muted);
}
.days-pill {
  font-size: 11px;
  font-weight: 500;
  padding: 2px 6px;
  border-radius: 999px;
}

/* Filter trigger button — matches design's btn ghost at height 32px */
.filter-trigger-btn {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  height: 32px;
  padding: 0 10px;
  border-radius: 6px;
  border: 1px solid transparent;
  background: transparent;
  color: var(--fg-muted);
  font-size: 13px;
  font-family: inherit;
  font-weight: 500;
  cursor: pointer;
  transition: background 80ms;
  white-space: nowrap;
}
.filter-trigger-btn:hover { background: var(--bg-hover); color: var(--fg); }
.filter-count-badge {
  font-size: 11px;
  font-weight: 600;
  padding: 0 6px;
  border-radius: 10px;
  background: var(--accent);
  color: #fff;
  min-width: 16px;
  text-align: center;
  margin-left: 2px;
}

/* View toggle */
.view-toggle {
  display: flex;
  align-items: center;
  background: var(--bg-sunken);
  border-radius: 6px;
  padding: 2px;
  border: 1px solid var(--border);
  gap: 0;
}
.view-toggle-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 5px;
  padding: 0 8px;
  height: 24px;
  background: transparent;
  border: 1px solid transparent;
  border-radius: 4px;
  color: var(--fg-muted);
  font-size: 12px;
  font-family: inherit;
  font-weight: 500;
  cursor: pointer;
  transition: background 80ms, color 80ms;
  white-space: nowrap;
}
.view-toggle-btn:hover { background: var(--bg-hover); color: var(--fg); }
.view-toggle-btn.active {
  background: var(--bg-panel);
  color: var(--fg);
  border-color: var(--border);
}

.filter-panel {
  position: absolute;
  top: calc(100% + 6px);
  left: 0;
  z-index: 100;
  width: 260px;
  background: var(--bg-panel);
  border: 1px solid var(--border);
  border-radius: var(--r-md);
  box-shadow: var(--shadow-lg);
  padding: 4px;
}
.filter-panel-label {
  font-size: 12px;
  font-weight: 500;
  color: var(--fg-subtle);
  padding: 6px 8px 2px;
}
.filter-menu-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 6px 8px;
  border-radius: var(--r-sm);
  font-size: 13px;
  cursor: pointer;
  width: 100%;
  text-align: left;
  background: none;
  border: none;
  color: var(--fg);
  font-family: inherit;
  transition: background 80ms;
  white-space: nowrap;
}
.filter-menu-item:hover { background: var(--bg-hover); }
.filter-avatar-placeholder {
  width: 20px; height: 20px; border-radius: 50%;
  background: var(--bg-sunken); border: 1px solid var(--border);
  font-size: 10px; color: var(--fg-muted);
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.filter-tag-chip {
  font-size: 11px;
  padding: 1px 6px;
  border-radius: 3px;
  background: var(--bg-sunken);
  color: var(--fg-muted);
  border: 1px solid var(--border);
}
.status-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.filter-divider { height: 1px; background: var(--border); margin: 4px 0; }

/* Sprint dropdown */
.sprint-dd-label {
  padding: 6px 8px 2px;
  font-size: 12px;
  font-weight: 500;
  color: var(--fg-subtle);
}
.sprint-dd-count {
  font-size: 11px;
  font-family: var(--font-mono, monospace);
  color: var(--fg-muted);
}
.sprint-dd-check {
  width: 14px;
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
}
.sprint-dd-divider {
  height: 1px;
  background: var(--border);
  margin: 4px 0;
}
</style>
