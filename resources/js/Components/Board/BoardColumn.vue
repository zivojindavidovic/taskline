<template>
  <div class="column">
    <!-- Header -->
    <div class="column-header">
      <span class="dot" :style="{ background: column.color }" />

      <!-- Editable name -->
      <span class="name">
        <input
          v-if="editing"
          ref="nameInput"
          v-model="editName"
          @blur="commitRename"
          @keydown.enter="commitRename"
          @keydown.escape="cancelRename"
        />
        <span
          v-else
          :title="locked ? 'Sprint locked' : 'Click to rename'"
          @click="!locked && startRename()"
        >{{ column.name }}</span>
      </span>

      <span class="count">{{ tasks.length }}</span>

      <!-- More menu -->
      <DropdownMenu align="right">
        <template #trigger>
          <button type="button" class="btn ghost icon-only sm">
            <MoreIcon class="w-4 h-4" />
          </button>
        </template>
        <div class="py-1">
          <MenuItem :disabled="locked" @click="!locked && startRename()">
            <EditIcon class="w-3.5 h-3.5" /> Rename column
          </MenuItem>
          <MenuItem :disabled="locked" @click="!locked && $emit('addTask', column.id)">
            <PlusIcon class="w-3.5 h-3.5" /> Add task
          </MenuItem>
          <div class="menu-divider" />
          <MenuItem :disabled="locked || tasks.length > 0" danger @click="tryDelete">
            <TrashIcon class="w-3.5 h-3.5" /> Delete column
          </MenuItem>
        </div>
      </DropdownMenu>
    </div>

    <!-- Body -->
    <div
      class="column-body"
      :class="{ 'drag-over': dragOver }"
      @dragover.prevent="!locked && (dragOver = true)"
      @dragleave="onDragLeave"
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

      <!-- Empty column drop zone -->
      <div v-if="tasks.length === 0" class="column-empty" aria-hidden="true">
        <div class="column-empty-inner">
          <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M8 3v10M3 8h10"/>
          </svg>
          <span>{{ dragOver ? 'Release to drop here' : 'Drop tasks here' }}</span>
        </div>
      </div>
    </div>

    <!-- Add task button (hidden when locked) -->
    <div v-if="!locked" class="column-add">
      <button
        type="button"
        class="btn ghost sm"
        style="width:100%;justify-content:flex-start;"
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

function onDragLeave(e) {
  if (!e.currentTarget.contains(e.relatedTarget)) {
    dragOver.value = false
  }
}

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

