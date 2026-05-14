<template>
  <div class="flex flex-col rounded-lg border shrink-0 w-[280px] max-h-full" style="background:var(--bg-sunken);border-color:var(--border)">
    <!-- Header -->
    <div class="flex items-center gap-2 px-3 py-2.5 border-b" style="border-color:var(--border)">
      <span class="w-2 h-2 rounded-full shrink-0" :style="{ background: column.color }" />

      <!-- Editable name -->
      <span class="flex-1 font-semibold text-sm" style="color:var(--fg)">
        <input
          v-if="editing"
          ref="nameInput"
          v-model="editName"
          class="w-full bg-transparent font-semibold text-sm border-none outline-none focus:ring-0 p-0"
          style="color:var(--fg)"
          @blur="commitRename"
          @keydown.enter="commitRename"
          @keydown.escape="cancelRename"
        />
        <span
          v-else
          class="cursor-default"
          :title="locked ? 'Sprint locked' : 'Click to rename'"
          @click="!locked && startRename()"
        >{{ column.name }}</span>
      </span>

      <span class="text-xs tabular-nums" style="color:var(--fg-subtle)">{{ tasks.length }}</span>

      <!-- More menu -->
      <DropdownMenu align="right">
        <template #trigger>
          <button
            type="button"
            class="p-1 rounded hover:bg-[var(--bg-hover)] transition-colors"
            style="color:var(--fg-muted)"
          >
            <MoreIcon class="w-4 h-4" />
          </button>
        </template>
        <div class="py-1">
          <MenuItem :disabled="locked" @click="!locked && startRename()">
            <EditIcon class="w-3.5 h-3.5" style="color:var(--fg-muted)" /> Rename column
          </MenuItem>
          <MenuItem :disabled="locked" @click="!locked && $emit('addTask', column.id)">
            <PlusIcon class="w-3.5 h-3.5" style="color:var(--fg-muted)" /> Add task
          </MenuItem>
          <div class="h-px my-1" style="background:var(--border)" />
          <MenuItem :disabled="locked || tasks.length > 0" danger @click="tryDelete">
            <TrashIcon class="w-3.5 h-3.5" /> Delete column
          </MenuItem>
        </div>
      </DropdownMenu>
    </div>

    <!-- Body -->
    <div
      ref="body"
      class="flex-1 flex flex-col gap-2 p-2 overflow-y-auto"
      :class="dragOver ? 'column-drag-over' : ''"
      @dragover.prevent="!locked && (dragOver = true)"
      @dragleave="dragOver = false"
      @drop.prevent="onDrop"
    >
      <TaskCard
        v-for="task in tasks"
        :key="task.id"
        :task="task"
        :dragging="draggingId === task.id"
        @open="$emit('openTask', $event)"
        @dragStart="$emit('dragStart', $event)"
        @dragEnd="$emit('dragEnd')"
      />
    </div>

    <!-- Add task button (hidden when locked) -->
    <div v-if="!locked" class="px-2 py-2 border-t" style="border-color:var(--border)">
      <button
        type="button"
        class="flex items-center gap-1.5 w-full px-2 py-1 rounded text-sm transition-colors hover:bg-[var(--bg-hover)]"
        style="color:var(--fg-muted)"
        @click="$emit('addTask', column.id)"
      >
        <PlusIcon class="w-3.5 h-3.5" /> Add task
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, nextTick } from 'vue'
import TaskCard from './TaskCard.vue'
import DropdownMenu from '@/Components/UI/DropdownMenu.vue'
import MenuItem from '@/Components/UI/MenuItem.vue'
import { MoreIcon, EditIcon, PlusIcon, TrashIcon } from '@/Components/UI/Icons.vue'

const props = defineProps({
  column:     { type: Object, required: true },
  tasks:      { type: Array,  default: () => [] },
  locked:     { type: Boolean, default: false },
  draggingId: { type: [String, Number], default: null },
})
const emit = defineEmits(['addTask', 'openTask', 'dragStart', 'dragEnd', 'drop', 'rename', 'delete'])

// Drag-over highlight
const dragOver = ref(false)

function onDrop() {
  dragOver.value = false
  emit('drop', props.column.id)
}

// Inline rename
const editing   = ref(false)
const editName  = ref('')
const nameInput = ref(null)

function startRename() {
  editName.value = props.column.name
  editing.value  = true
  nextTick(() => nameInput.value?.focus())
}

function commitRename() {
  const name = editName.value.trim()
  if (name && name !== props.column.name) {
    emit('rename', props.column.id, name)
  }
  editing.value = false
}

function cancelRename() {
  editing.value = false
}

function tryDelete() {
  if (props.tasks.length === 0) emit('delete', props.column.id)
}
</script>
