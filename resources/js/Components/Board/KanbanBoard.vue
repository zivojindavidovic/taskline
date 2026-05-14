<template>
  <div class="flex gap-3 px-5 py-4 overflow-x-auto overflow-y-hidden h-full items-start">
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
    <div v-if="!locked" class="shrink-0 w-[280px]">
      <div
        v-if="!addingColumn"
        class="flex items-center justify-center gap-2 h-10 rounded-lg border border-dashed text-sm cursor-pointer transition-colors hover:bg-[var(--bg-hover)]"
        style="border-color:var(--border-strong);color:var(--fg-muted)"
        @click="addingColumn = true"
      >
        <PlusIcon class="w-4 h-4" /> Add column
      </div>
      <div v-else class="rounded-lg border p-2 flex flex-col gap-2" style="border-color:var(--border);background:var(--bg-sunken)">
        <input
          ref="newColInput"
          v-model="newColName"
          class="w-full h-8 px-3 rounded border text-sm"
          style="border-color:var(--border);background:var(--bg-panel);color:var(--fg)"
          placeholder="Column name…"
          @keydown.enter="submitNewCol"
          @keydown.escape="cancelNewCol"
        />
        <div class="flex justify-end gap-1.5">
          <button type="button" class="btn-ghost text-sm px-3 h-7 rounded" @click="cancelNewCol">Cancel</button>
          <button
            type="button"
            :disabled="!newColName.trim()"
            class="btn-primary text-sm px-3 h-7 rounded"
            @click="submitNewCol"
          >Add</button>
        </div>
      </div>
    </div>
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

<style scoped>
.btn-ghost {
  background: transparent;
  border: 1px solid var(--border);
  color: var(--fg-muted);
  cursor: pointer;
  transition: background 80ms;
}
.btn-ghost:hover { background: var(--bg-hover); }

.btn-primary {
  background: var(--accent);
  border: none;
  color: var(--accent-fg);
  cursor: pointer;
  font-weight: 500;
  transition: background 80ms;
}
.btn-primary:hover:not(:disabled) { background: var(--accent-hover); }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
</style>
