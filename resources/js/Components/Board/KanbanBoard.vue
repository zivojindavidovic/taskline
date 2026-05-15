<template>
  <div class="board">
    <BoardColumn
      v-for="col in columns"
      :key="col.id"
      :column="col"
      :tasks="tasksByColumn[col.id] ?? []"
      :locked="locked"
      :draggingId="draggingId"
      @addTask="$emit('addTask', $event)"
      @openTask="$emit('openTask', $event)"
      @dragStart="draggingId = $event"
      @dragEnd="draggingId = null"
      @drop="onDrop"
      @rename="(id, name) => $emit('renameColumn', id, name)"
      @delete="$emit('deleteColumn', $event)"
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
const emit = defineEmits(['addTask', 'openTask', 'moveTask', 'addColumn', 'renameColumn', 'deleteColumn'])

// Group tasks by column
const tasksByColumn = computed(() => {
  const m = {}
  props.columns.forEach(c => { m[c.id] = [] })
  props.tasks.forEach(t => {
    if (t.board_column_id && m[t.board_column_id]) {
      m[t.board_column_id].push(t)
    }
  })
  return m
})

// Drag state
const draggingId = ref(null)

function onDrop(colId) {
  if (draggingId.value) {
    emit('moveTask', draggingId.value, colId)
  }
  draggingId.value = null
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

