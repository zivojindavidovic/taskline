<template>
  <!-- Backdrop -->
  <div
    class="fixed inset-0 z-50 animate-fade-in"
    style="background:rgba(0,0,0,0.15)"
    @click="$emit('close')"
  />

  <!-- Panel -->
  <div
    class="fixed top-0 right-0 h-screen flex flex-col z-[51] animate-slide-in-right"
    style="width:var(--panel-w);max-width:92vw;background:var(--bg-panel);border-left:1px solid var(--border);box-shadow:var(--shadow-lg)"
    role="dialog"
    aria-label="New task"
  >
    <!-- Header -->
    <div class="flex items-center gap-2 px-4 py-3 border-b shrink-0" style="border-color:var(--border)">
      <span class="font-mono text-xs" style="color:var(--fg-muted)">New task</span>
      <div class="flex-1" />
      <button type="button" class="p-1 rounded hover:bg-[var(--bg-hover)]" style="color:var(--fg-muted)" @click="$emit('close')">
        <CloseIcon class="w-4 h-4" />
      </button>
    </div>

    <!-- Scrollable body -->
    <div class="flex-1 overflow-y-auto px-6 py-5 flex flex-col gap-5">

      <!-- Title input -->
      <input
        ref="titleInput"
        v-model="form.title"
        class="w-full text-xl font-semibold bg-transparent border-none outline-none leading-snug"
        style="color:var(--fg)"
        placeholder="Task title"
        spellcheck="false"
        @keydown.meta.enter="submit"
        @keydown.ctrl.enter="submit"
        @keydown.escape="$emit('close')"
      />
      <p v-if="form.errors.title" class="text-xs -mt-4" style="color:#ef4444">{{ form.errors.title }}</p>

      <!-- Properties grid -->
      <div class="grid gap-y-2" style="grid-template-columns:100px 1fr;font-size:13px;align-items:center">

        <!-- Status -->
        <span style="color:var(--fg-muted)">Status</span>
        <DropdownMenu>
          <template #trigger>
            <button type="button" class="prop-pill">
              <span class="w-2 h-2 rounded-full" :style="{ background: selectedColumn?.color }" />
              {{ selectedColumn?.name ?? '—' }}
            </button>
          </template>
          <div class="py-1">
            <MenuItem
              v-for="col in columns"
              :key="col.id"
              @click="form.board_column_id = col.id"
            >
              <CheckIcon v-if="col.id === form.board_column_id" class="w-3.5 h-3.5" style="color:var(--accent)" />
              <span v-else class="w-3.5 h-3.5 inline-block" />
              <span class="w-2 h-2 rounded-full shrink-0" :style="{ background: col.color }" />
              {{ col.name }}
            </MenuItem>
          </div>
        </DropdownMenu>

        <!-- Assignee -->
        <span style="color:var(--fg-muted)">Assignee</span>
        <DropdownMenu :width="220">
          <template #trigger>
            <button type="button" class="prop-pill">
              <Avatar v-if="selectedUser" :name="selectedUser.name" size="sm" />
              <span v-else class="w-6 h-6 rounded-full inline-flex items-center justify-center text-xs" style="background:var(--bg-active);color:var(--fg-subtle)">?</span>
              {{ selectedUser?.name ?? 'Unassigned' }}
            </button>
          </template>
          <div class="py-1">
            <div class="px-2 pt-1 pb-2 text-xs font-medium" style="color:var(--fg-subtle)">Assign to</div>
            <MenuItem @click="form.assignee_id = null">
              <CheckIcon v-if="!form.assignee_id" class="w-3.5 h-3.5" style="color:var(--accent)" />
              <span v-else class="w-3.5 h-3.5 inline-block" />
              Unassigned
            </MenuItem>
            <div class="h-px my-1" style="background:var(--border)" />
            <MenuItem v-for="u in allUsers" :key="u.id" @click="form.assignee_id = u.id">
              <CheckIcon v-if="u.id === form.assignee_id" class="w-3.5 h-3.5" style="color:var(--accent)" />
              <span v-else class="w-3.5 h-3.5 inline-block" />
              <Avatar :name="u.name" size="sm" />
              {{ u.name }}
            </MenuItem>
          </div>
        </DropdownMenu>

        <!-- Priority -->
        <span style="color:var(--fg-muted)">Priority</span>
        <DropdownMenu>
          <template #trigger>
            <button type="button" class="prop-pill">
              <PriorityBadge :priority="form.priority" show-label />
            </button>
          </template>
          <div class="py-1">
            <MenuItem v-for="p in PRIORITIES" :key="p.id" @click="form.priority = p.id">
              <CheckIcon v-if="p.id === form.priority" class="w-3.5 h-3.5" style="color:var(--accent)" />
              <span v-else class="w-3.5 h-3.5 inline-block" />
              <PriorityBadge :priority="p.id" show-label />
            </MenuItem>
          </div>
        </DropdownMenu>

        <!-- Sprint -->
        <span style="color:var(--fg-muted)">Sprint</span>
        <DropdownMenu v-if="sprints.length">
          <template #trigger>
            <button type="button" class="prop-pill">
              <LightningIcon class="w-3.5 h-3.5" style="color:var(--accent)" />
              {{ selectedSprint?.name ?? 'No sprint' }}
            </button>
          </template>
          <div class="py-1">
            <MenuItem @click="form.sprint_id = null">
              <CheckIcon v-if="!form.sprint_id" class="w-3.5 h-3.5" style="color:var(--accent)" />
              <span v-else class="w-3.5 h-3.5 inline-block" />
              <span style="color:var(--fg-muted)">No sprint</span>
            </MenuItem>
            <div class="h-px my-1" style="background:var(--border)" />
            <MenuItem v-for="s in sprints" :key="s.id" @click="form.sprint_id = s.id">
              <CheckIcon v-if="s.id === form.sprint_id" class="w-3.5 h-3.5" style="color:var(--accent)" />
              <span v-else class="w-3.5 h-3.5 inline-block" />
              <LightningIcon class="w-3.5 h-3.5 shrink-0" style="color:var(--accent)" />
              <span class="flex-1">{{ s.name }}</span>
              <LockIcon v-if="s.locked" class="w-3 h-3 ml-auto" style="color:var(--fg-muted)" />
            </MenuItem>
          </div>
        </DropdownMenu>
        <span v-else class="text-sm" style="color:var(--fg-subtle)">—</span>

        <!-- Tags -->
        <span style="color:var(--fg-muted)">Tags</span>
        <DropdownMenu :width="200">
          <template #trigger>
            <button type="button" class="prop-pill flex-wrap">
              <template v-if="form.tags.length">
                <span
                  v-for="tag in form.tags"
                  :key="tag"
                  class="text-[11px] px-1.5 py-0.5 rounded border"
                  style="background:var(--bg-sunken);color:var(--fg-muted);border-color:var(--border)"
                >{{ tag }}</span>
              </template>
              <span v-else style="color:var(--fg-muted)">+ Add tags</span>
            </button>
          </template>
          <div class="py-1">
            <div class="px-2 pt-1 pb-2 text-xs font-medium" style="color:var(--fg-subtle)">Tags</div>
            <MenuItem v-for="tag in ALL_TAGS" :key="tag" @click="toggleTag(tag)">
              <CheckIcon v-if="form.tags.includes(tag)" class="w-3.5 h-3.5" style="color:var(--accent)" />
              <span v-else class="w-3.5 h-3.5 inline-block" />
              {{ tag }}
            </MenuItem>
          </div>
        </DropdownMenu>

        <!-- Due date -->
        <span style="color:var(--fg-muted)">Due date</span>
        <label class="relative inline-flex items-center">
          <span class="prop-pill text-sm cursor-pointer" @click="$refs.dueDateInput.showPicker?.()">
            <CalendarIcon class="w-3.5 h-3.5" style="color:var(--fg-muted)" />
            {{ form.due_date ? formatDate(form.due_date) : 'Set date' }}
          </span>
          <input
            ref="dueDateInput"
            type="date"
            :value="form.due_date ?? ''"
            class="absolute inset-0 opacity-0 w-full cursor-pointer"
            style="pointer-events:none"
            @change="e => form.due_date = e.target.value || null"
          />
        </label>
      </div>

      <!-- Description -->
      <div>
        <div class="text-xs font-medium uppercase tracking-wider mb-2" style="color:var(--fg-muted)">Description</div>
        <textarea
          v-model="form.description"
          rows="4"
          class="w-full text-sm leading-relaxed px-3 py-2 rounded-lg border resize-none"
          style="border-color:var(--border);background:var(--bg-sunken);color:var(--fg)"
          placeholder="Add a description…"
        />
      </div>
    </div>

    <!-- Footer -->
    <div class="flex items-center justify-end gap-2 px-4 py-3 shrink-0 border-t" style="border-color:var(--border);background:var(--bg-panel)">
      <button type="button" class="btn-ghost h-8 px-3 text-sm rounded-lg" @click="$emit('close')">
        Cancel <kbd class="ml-1 text-[10px] px-1 rounded border" style="border-color:var(--border)">Esc</kbd>
      </button>
      <button
        type="button"
        :disabled="!form.title.trim() || form.processing"
        class="btn-primary h-8 px-4 text-sm rounded-lg inline-flex items-center gap-1.5"
        @click="submit"
      >
        Create task
        <kbd class="ml-1 text-[10px] px-1 rounded" style="background:rgba(255,255,255,.15)">⌘↵</kbd>
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, nextTick } from 'vue'
import { useForm } from '@inertiajs/vue3'
import DropdownMenu from '@/Components/UI/DropdownMenu.vue'
import MenuItem from '@/Components/UI/MenuItem.vue'
import Avatar from '@/Components/UI/Avatar.vue'
import PriorityBadge from '@/Components/UI/PriorityBadge.vue'
import { CheckIcon, CloseIcon, LightningIcon, LockIcon, CalendarIcon } from '@/Components/UI/Icons.vue'

const props = defineProps({
  show:          { type: Boolean, required: true },
  projectId:     { type: Number,  required: true },
  sprintId:      { type: Number,  default: null },
  sprints:       { type: Array,   default: () => [] },
  columns:       { type: Array,   default: () => [] },
  defaultColumn: { type: Number,  default: null },
  allUsers:      { type: Array,   default: () => [] },
})
const emit = defineEmits(['close'])

const titleInput = ref(null)
const dueDateInput = ref(null)

const PRIORITIES = [
  { id: 'urgent', label: 'Urgent' },
  { id: 'high',   label: 'High' },
  { id: 'med',    label: 'Medium' },
  { id: 'low',    label: 'Low' },
]
const ALL_TAGS = ['frontend', 'backend', 'design', 'bug', 'feature', 'infra', 'research', 'a11y', 'perf']

const form = useForm({
  project_id:      props.projectId,
  sprint_id:       props.sprintId,
  board_column_id: props.defaultColumn ?? props.columns[0]?.id,
  title:           '',
  description:     '',
  priority:        'med',
  assignee_id:     null,
  tags:            [],
  due_date:        null,
})

watch(() => props.show, val => {
  if (val) {
    form.reset()
    form.project_id      = props.projectId
    form.sprint_id       = props.sprintId
    form.board_column_id = props.defaultColumn ?? props.columns[0]?.id
    nextTick(() => titleInput.value?.focus())
  }
})

watch(() => props.defaultColumn, val => { form.board_column_id = val ?? props.columns[0]?.id })

const selectedColumn = computed(() => props.columns.find(c => c.id === form.board_column_id))
const selectedUser   = computed(() => props.allUsers.find(u => u.id === form.assignee_id))
const selectedSprint = computed(() => props.sprints.find(s => s.id === form.sprint_id))

function toggleTag(tag) {
  const idx = form.tags.indexOf(tag)
  if (idx === -1) form.tags.push(tag)
  else form.tags.splice(idx, 1)
}

function formatDate(d) {
  return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

function submit() {
  if (!form.title.trim()) return
  form.post(route('tasks.store'), {
    preserveScroll: true,
    onSuccess: () => emit('close'),
  })
}
</script>

<style scoped>
.prop-pill {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 2px 8px;
  border-radius: 6px;
  cursor: pointer;
  border: 1px solid transparent;
  font-size: 13px;
  color: var(--fg);
  transition: background 80ms, border-color 80ms;
  background: transparent;
}
.prop-pill:hover {
  background: var(--bg-hover);
  border-color: var(--border);
}

.btn-primary {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: var(--accent);
  color: var(--accent-fg);
  border: none;
  font-weight: 500;
  cursor: pointer;
  transition: background 80ms;
}
.btn-primary:hover:not(:disabled) { background: var(--accent-hover); }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }

.btn-ghost {
  background: transparent;
  border: 1px solid var(--border);
  color: var(--fg-muted);
  cursor: pointer;
  font-weight: 500;
  transition: background 80ms;
}
.btn-ghost:hover { background: var(--bg-hover); }
</style>
