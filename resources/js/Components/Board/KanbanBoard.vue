<template>
  <div class="board">
    <BoardColumn
      v-for="(col, i) in columns"
      :key="col.id"
      :column="col"
      :index="i"
      :count="columns.length"
      :tasks="tasksByColumn[col.id] ?? []"
      :locked="locked"
      :draggingId="draggingId"
      :colDragId="colDragId"
      @addTask="$emit('addTask', $event)"
      @openTask="$emit('openTask', $event)"
      @dragStart="draggingId = $event"
      @dragEnd="draggingId = null"
      @drop="onDrop"
      @rename="(id, name) => $emit('renameColumn', id, name)"
      @delete="$emit('deleteColumn', $event)"
      @recolor="(id, color) => $emit('recolorColumn', id, color)"
      @move="moveColumn"
      @card-drop="onCardDrop"
      @card-move="onCardMove"
      @colDragStart="colDragId = $event"
      @colDragEnd="colDragId = null"
      @colDrop="onColDrop"
    />

    <!-- Add column -->
    <template v-if="!locked">
      <button v-if="!addingColumn" type="button" class="add-column" @click="addingColumn = true">
        <PlusIcon class="w-4 h-4" /> Add column
      </button>
      <div v-else class="column" style="padding:8px;gap:8px;display:flex;flex-direction:column;">
        <input
          ref="newColInput"
          v-model="newColName"
          class="input"
          placeholder="Column name…"
          @keydown.enter="submitNewCol"
          @keydown.escape="cancelNewCol"
        />
        <div style="display:flex;justify-content:flex-end;gap:6px;">
          <button type="button" class="btn ghost sm" @click="cancelNewCol">Cancel</button>
          <button
            type="button"
            :disabled="!newColName.trim()"
            class="btn primary sm"
            @click="submitNewCol"
          >Add</button>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, nextTick, watch } from 'vue'
import BoardColumn from './BoardColumn.vue'
import { PlusIcon } from '@/Components/UI/Icons.vue'

const props = defineProps({
  columns:  { type: Array,  required: true },
  tasks:    { type: Array,  required: true },
  locked:   { type: Boolean, default: false },
})
const emit = defineEmits(['addTask', 'openTask', 'moveTask', 'reorderTasks', 'addColumn', 'renameColumn', 'deleteColumn', 'recolorColumn', 'reorderColumns'])

// Group tasks by column, each column ordered top-to-bottom by persisted position.
const tasksByColumn = computed(() => {
  const m = {}
  props.columns.forEach(c => { m[c.id] = [] })
  props.tasks.forEach(t => {
    if (t.board_column_id && m[t.board_column_id]) {
      m[t.board_column_id].push(t)
    }
  })
  Object.values(m).forEach(list =>
    list.sort((a, b) => (a.position ?? 0) - (b.position ?? 0) || (a.id - b.id))
  )
  return m
})

// Task drag state
const draggingId = ref(null)

function onDrop(colId) {
  if (draggingId.value) {
    emit('moveTask', draggingId.value, colId)
  }
  draggingId.value = null
}

// ── Card reorder ───────────────────────────────────────────────────────────
// Both a drop onto a sibling card and the "Move up / Move down" menu resolve to
// one new ordering of a single column's task ids, emitted upward in one shot —
// the vertical twin of the column reorder above. A card dropped from another
// column is folded into the destination column's order here.
function onCardDrop(targetTaskId, place) {
  const dragId = draggingId.value
  draggingId.value = null
  if (dragId == null || dragId === targetTaskId) return

  const target = props.tasks.find(t => t.id === targetTaskId)
  if (!target) return
  const colId = target.board_column_id

  const ids = (tasksByColumn.value[colId] ?? []).map(t => t.id).filter(id => id !== dragId)
  const idx = ids.indexOf(targetTaskId)
  if (idx < 0) return
  ids.splice(place === 'after' ? idx + 1 : idx, 0, dragId)
  emit('reorderTasks', colId, ids)
}

function onCardMove(taskId, dir) {
  const task = props.tasks.find(t => t.id === taskId)
  if (!task) return
  const colId = task.board_column_id

  const ids = (tasksByColumn.value[colId] ?? []).map(t => t.id)
  const i = ids.indexOf(taskId)
  const j = i + dir
  if (i < 0 || j < 0 || j >= ids.length) return
  ;[ids[i], ids[j]] = [ids[j], ids[i]]
  emit('reorderTasks', colId, ids)
}

// ── Column reorder ─────────────────────────────────────────────────────────
// Both the menu actions (Move left/right) and drag-to-reorder resolve to a
// single new ordering of column ids, which is emitted upward in one shot.
const colDragId = ref(null)

const currentOrder = () => props.columns.map(c => c.id)

function moveColumn(id, dir) {
  const ids = currentOrder()
  const i = ids.indexOf(id)
  const j = i + dir
  if (i < 0 || j < 0 || j >= ids.length) return
  ;[ids[i], ids[j]] = [ids[j], ids[i]]
  emit('reorderColumns', ids)
}

function reorderColumns(fromId, toId) {
  if (fromId === toId) return
  const ids = currentOrder()
  const from = ids.indexOf(fromId)
  const to   = ids.indexOf(toId)
  if (from < 0 || to < 0) return
  ids.splice(to, 0, ids.splice(from, 1)[0])
  emit('reorderColumns', ids)
}

function onColDrop(targetId) {
  if (colDragId.value != null) reorderColumns(colDragId.value, targetId)
  colDragId.value = null
}

// Add column
const addingColumn = ref(false)
const newColName   = ref('')
const newColInput  = ref(null)

watch(addingColumn, v => {
  if (v) nextTick(() => newColInput.value?.focus())
})

function submitNewCol() {
  const name = newColName.value.trim()
  if (name) {
    emit('addColumn', name)
    newColName.value  = ''
    addingColumn.value = false
  }
}

function cancelNewCol() {
  newColName.value  = ''
  addingColumn.value = false
}
</script>

