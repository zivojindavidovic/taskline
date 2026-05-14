<template>
  <AppModal :show="show" title="New task" @close="close">
    <!-- Title -->
    <div class="flex flex-col gap-1">
      <input
        ref="titleInput"
        v-model="form.title"
        class="w-full h-10 px-3 rounded-lg border text-sm"
        style="border-color:var(--border);background:var(--bg-panel);color:var(--fg)"
        placeholder="Task title"
        @keydown.meta.enter="submit"
        @keydown.ctrl.enter="submit"
      />
      <p v-if="form.errors.title" class="text-xs text-red-500">{{ form.errors.title }}</p>
    </div>

    <!-- Description -->
    <textarea
      v-model="form.description"
      rows="3"
      class="w-full px-3 py-2 rounded-lg border text-sm resize-none"
      style="border-color:var(--border);background:var(--bg-panel);color:var(--fg)"
      placeholder="Description (optional)"
    />

    <!-- Inline pickers row -->
    <div class="flex flex-wrap gap-2">
      <!-- Sprint picker -->
      <DropdownMenu v-if="sprints.length">
        <template #trigger>
          <button type="button" class="picker-btn">
            <LightningIcon class="w-3.5 h-3.5" style="color:var(--accent)" />
            {{ selectedSprint?.name ?? 'Sprint' }}
            <ChevronIcon class="w-3.5 h-3.5" />
          </button>
        </template>
        <div class="py-1">
          <MenuItem @click="form.sprint_id = null">
            <CheckIcon v-if="!form.sprint_id" class="w-3.5 h-3.5" style="color:var(--accent)" />
            <span v-else class="w-3.5 h-3.5 inline-block" />
            No sprint
          </MenuItem>
          <div class="h-px my-1" style="background:var(--border)" />
          <MenuItem v-for="s in sprints" :key="s.id" @click="form.sprint_id = s.id">
            <CheckIcon v-if="s.id === form.sprint_id" class="w-3.5 h-3.5" style="color:var(--accent)" />
            <span v-else class="w-3.5 h-3.5 inline-block" />
            <LightningIcon class="w-3.5 h-3.5 shrink-0" style="color:var(--accent)" />
            {{ s.name }}
            <LockIcon v-if="s.locked" class="w-3 h-3 ml-auto" style="color:var(--fg-muted)" />
          </MenuItem>
        </div>
      </DropdownMenu>

      <!-- Column picker -->
      <DropdownMenu>
        <template #trigger>
          <button type="button" class="picker-btn">
            <span class="w-2 h-2 rounded-full" :style="{ background: selectedColumn?.color }" />
            {{ selectedColumn?.name ?? 'Column' }}
            <ChevronIcon class="w-3.5 h-3.5" />
          </button>
        </template>
        <div class="py-1">
          <MenuItem v-for="col in columns" :key="col.id" @click="form.board_column_id = col.id">
            <CheckIcon v-if="col.id === form.board_column_id" class="w-3.5 h-3.5" style="color:var(--accent)" />
            <span v-else class="w-3.5 h-3.5 inline-block" />
            <span class="w-2 h-2 rounded-full" :style="{ background: col.color }" />
            {{ col.name }}
          </MenuItem>
        </div>
      </DropdownMenu>

      <!-- Priority picker -->
      <DropdownMenu>
        <template #trigger>
          <button type="button" class="picker-btn">
            <PriorityBadge :priority="form.priority" show-label />
            <ChevronIcon class="w-3.5 h-3.5" />
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

      <!-- Assignee picker -->
      <DropdownMenu :width="220">
        <template #trigger>
          <button type="button" class="picker-btn">
            <Avatar v-if="selectedUser" :name="selectedUser.name" size="sm" />
            {{ selectedUser?.name ?? 'Assignee' }}
            <ChevronIcon class="w-3.5 h-3.5" />
          </button>
        </template>
        <div class="py-1">
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
    </div>

    <template #footer>
      <button type="button" class="btn-ghost h-8 px-3 text-sm rounded-lg" @click="close">
        Cancel <kbd class="ml-1 text-[10px] px-1 rounded border" style="border-color:var(--border)">Esc</kbd>
      </button>
      <button
        type="button"
        :disabled="!form.title.trim() || form.processing"
        class="btn-primary h-8 px-4 text-sm rounded-lg"
        @click="submit"
      >
        Create task
        <kbd class="ml-1 text-[10px] px-1 rounded" style="background:rgba(255,255,255,.15)">⌘↵</kbd>
      </button>
    </template>
  </AppModal>
</template>

<script setup>
import { ref, computed, watch, nextTick } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AppModal from '@/Components/UI/AppModal.vue'
import DropdownMenu from '@/Components/UI/DropdownMenu.vue'
import MenuItem from '@/Components/UI/MenuItem.vue'
import Avatar from '@/Components/UI/Avatar.vue'
import PriorityBadge from '@/Components/UI/PriorityBadge.vue'
import { ChevronIcon, CheckIcon, LightningIcon, LockIcon } from '@/Components/UI/Icons.vue'

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

const PRIORITIES = [
  { id: 'urgent', label: 'Urgent' },
  { id: 'high',   label: 'High' },
  { id: 'med',    label: 'Medium' },
  { id: 'low',    label: 'Low' },
]

const form = useForm({
  project_id:      props.projectId,
  sprint_id:       props.sprintId,
  board_column_id: props.defaultColumn ?? props.columns[0]?.id,
  title:           '',
  description:     '',
  priority:        'med',
  assignee_id:     null,
  tags:            [],
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

function submit() {
  if (!form.title.trim()) return
  form.post(route('tasks.store'), {
    preserveScroll: true,
    onSuccess: () => close(),
  })
}

function close() {
  form.reset()
  emit('close')
}
</script>

<style scoped>
.picker-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 0 10px;
  height: 28px;
  border-radius: 6px;
  border: 1px solid var(--border);
  background: var(--bg-panel);
  color: var(--fg);
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  transition: background 80ms;
}
.picker-btn:hover { background: var(--bg-hover); }

.btn-ghost {
  background: transparent;
  border: 1px solid var(--border);
  color: var(--fg-muted);
  cursor: pointer;
  font-weight: 500;
}
.btn-ghost:hover { background: var(--bg-hover); }

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
</style>
