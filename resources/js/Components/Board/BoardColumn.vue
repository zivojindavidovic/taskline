<template>
  <div
    class="column"
    :class="{ 'col-dragging': colDragId === column.id, 'col-drop-over': colDropOver && colDragId !== column.id }"
    @dragover="onColDragOver"
    @dragleave="onColDragLeave"
    @drop="onColDrop"
  >
    <!-- Header (drag handle for reordering columns) -->
    <div
      class="column-header"
      :class="{ 'col-grab': !locked && !editing }"
      :draggable="!locked && !editing"
      @dragstart="onColHeaderDragStart"
      @dragend="$emit('colDragEnd')"
    >
      <!-- Color dot — click to recolor the column -->
      <DropdownMenu v-if="!locked" align="left" :width="180">
        <template #trigger>
          <button type="button" class="dot-btn" title="Change column color" aria-label="Change column color">
            <span class="dot" :style="{ background: column.color }" />
          </button>
        </template>
        <template #default="{ close }">
          <div class="menu-label">Column color</div>
          <div class="color-grid">
            <button
              v-for="c in COLUMN_COLORS"
              :key="c"
              type="button"
              class="swatch"
              :class="{ active: (column.color || '').toLowerCase() === c.toLowerCase() }"
              :style="{ background: c }"
              :title="c"
              @click="$emit('recolor', column.id, c); close()"
            />
          </div>
        </template>
      </DropdownMenu>
      <span v-else class="dot" :style="{ background: column.color }" />

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
          <MenuItem
            :disabled="locked || index === 0"
            @click="!locked && index > 0 && $emit('move', column.id, -1)"
          >
            <ArrowLeftIcon class="w-3.5 h-3.5" /> Move left
          </MenuItem>
          <MenuItem
            :disabled="locked || index === count - 1"
            @click="!locked && index < count - 1 && $emit('move', column.id, 1)"
          >
            <ArrowRightIcon class="w-3.5 h-3.5" /> Move right
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
      @dragover.prevent="!colDragId && (dragOver = true)"
      @dragleave="onDragLeave"
      @drop.prevent="onDrop"
    >
      <!-- Sprint locking keeps task details immutable, not their board progress. -->
      <TaskCard
        v-for="(task, ti) in tasks"
        :key="task.id"
        :task="task"
        :index="ti"
        :count="tasks.length"
        :locked="false"
        :dragging="draggingId === task.id"
        @open="$emit('openTask', $event)"
        @dragStart="$emit('dragStart', $event)"
        @dragEnd="$emit('dragEnd')"
        @card-drop="(id, place) => $emit('card-drop', id, place)"
        @move="(id, dir) => $emit('card-move', id, dir)"
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
import { ref, nextTick, watch } from 'vue'
import TaskCard from './TaskCard.vue'
import DropdownMenu from '@/Components/UI/DropdownMenu.vue'
import MenuItem from '@/Components/UI/MenuItem.vue'
import { MoreIcon, EditIcon, PlusIcon, TrashIcon, ArrowLeftIcon, ArrowRightIcon } from '@/Components/UI/Icons.vue'

const props = defineProps({
  column:     { type: Object, required: true },
  tasks:      { type: Array,  default: () => [] },
  locked:     { type: Boolean, default: false },
  draggingId: { type: [String, Number], default: null },
  index:      { type: Number, default: 0 },
  count:      { type: Number, default: 1 },
  colDragId:  { type: [String, Number], default: null },
})
const emit = defineEmits([
  'addTask', 'openTask', 'dragStart', 'dragEnd', 'drop', 'rename', 'delete', 'recolor',
  'move', 'colDragStart', 'colDragEnd', 'colDrop', 'card-drop', 'card-move',
])

// Palette offered when recoloring a column via its header dot.
const COLUMN_COLORS = [
  '#64748b', '#94948c', '#6b7280', '#0f172a',
  '#dc2626', '#ea580c', '#d97706', '#ca8a04',
  '#65a30d', '#16a34a', '#059669', '#0d9488',
  '#0891b2', '#0284c7', '#2563eb', '#4f46e5',
  '#7c3aed', '#9333ea', '#c026d3', '#db2777',
]

// Task drag-over highlight
const dragOver = ref(false)

// A card dropped onto a sibling card stops propagation, so the body's own drop
// never clears this highlight. Reset it whenever any task drag finishes.
watch(() => props.draggingId, (v) => { if (v == null) dragOver.value = false })

function onDragLeave(e) {
  if (!e.currentTarget.contains(e.relatedTarget)) {
    dragOver.value = false
  }
}

function onDrop() {
  // Ignore task-drop while a column reorder is in flight.
  if (props.colDragId) return
  dragOver.value = false
  emit('drop', props.column.id)
}

// ── Column drag-to-reorder ────────────────────────────────────────────────
const colDropOver = ref(false)

function onColHeaderDragStart(e) {
  if (props.locked || editing.value) { e.preventDefault(); return }
  // Don't start a column drag from an interactive control inside the header
  // (menu trigger, rename input) — those have their own behaviour.
  if (e.target.closest('button, input, .menu, [role="menu"]')) { e.preventDefault(); return }
  e.dataTransfer.effectAllowed = 'move'
  try { e.dataTransfer.setData('text/plain', String(props.column.id)) } catch (_) {}
  emit('colDragStart', props.column.id)
}

function onColDragOver(e) {
  if (props.colDragId != null && props.colDragId !== props.column.id) {
    e.preventDefault()
    colDropOver.value = true
  }
}

function onColDragLeave(e) {
  if (!e.currentTarget.contains(e.relatedTarget)) colDropOver.value = false
}

function onColDrop(e) {
  if (props.colDragId != null && props.colDragId !== props.column.id) {
    e.preventDefault()
    emit('colDrop', props.column.id)
  }
  colDropOver.value = false
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
