<template>
  <AppModal :show="show" title="Lock sprint" @close="$emit('close')">
    <p class="text-sm" style="color:var(--fg-muted)">
      Locking <strong style="color:var(--fg)">{{ sprint?.name }}</strong> will prevent any further
      changes to its tasks and columns. This action can be undone by an admin.
    </p>

    <div
      v-if="openCount > 0"
      class="flex items-start gap-3 px-3 py-2.5 rounded-lg text-sm"
      style="background:var(--status-warn-bg);color:var(--status-warn-fg);border:1px solid var(--status-warn-fg)20"
    >
      <span class="mt-0.5">⚠️</span>
      <span>
        There {{ openCount === 1 ? 'is' : 'are' }}
        <strong>{{ openCount }} open {{ openCount === 1 ? 'task' : 'tasks' }}</strong>
        that {{ openCount === 1 ? 'has' : 'have' }} not been completed.
        They will remain as-is when the sprint is locked.
      </span>
    </div>

    <template #footer>
      <button type="button" class="btn-ghost h-8 px-3 text-sm rounded-lg" @click="$emit('close')">
        Cancel
      </button>
      <button
        type="button"
        :disabled="loading"
        class="btn-danger h-8 px-4 text-sm rounded-lg"
        @click="confirm"
      >
        {{ loading ? 'Locking…' : 'Lock sprint' }}
      </button>
    </template>
  </AppModal>
</template>

<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AppModal from '@/Components/UI/AppModal.vue'

const props = defineProps({
  show:   { type: Boolean, required: true },
  sprint: { type: Object,  default: null },
  tasks:  { type: Array,   default: () => [] },
})
const emit = defineEmits(['close'])

const loading = ref(false)

const openCount = computed(() =>
  props.tasks.filter(t => !t.completed).length
)

function confirm() {
  if (!props.sprint) return
  loading.value = true
  router.post(route('sprints.lock', props.sprint.id), {}, {
    preserveScroll: true,
    onFinish: () => {
      loading.value = false
      emit('close')
    },
  })
}
</script>

<style scoped>
.btn-ghost {
  background: transparent;
  border: 1px solid var(--border);
  color: var(--fg-muted);
  cursor: pointer;
  font-weight: 500;
}
.btn-ghost:hover { background: var(--bg-hover); }

.btn-danger {
  background: var(--status-blocked);
  color: #fff;
  border: none;
  font-weight: 500;
  cursor: pointer;
  transition: background 80ms;
}
.btn-danger:hover:not(:disabled) { background: #b91c1c; }
.btn-danger:disabled { opacity: 0.5; cursor: not-allowed; }
</style>
