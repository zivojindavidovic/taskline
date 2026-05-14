<template>
  <AppModal :show="show" title="New sprint" @close="close">
    <!-- Sprint name -->
    <div class="flex flex-col gap-1">
      <label class="text-xs font-medium" style="color:var(--fg-muted)">Sprint name</label>
      <input
        ref="nameInput"
        v-model="form.name"
        class="w-full h-10 px-3 rounded-lg border text-sm"
        style="border-color:var(--border);background:var(--bg-panel);color:var(--fg)"
        placeholder="e.g. Sprint 2"
        @keydown.meta.enter="submit"
        @keydown.ctrl.enter="submit"
      />
      <p v-if="form.errors.name" class="text-xs text-red-500">{{ form.errors.name }}</p>
    </div>

    <!-- Date row -->
    <div class="flex gap-3">
      <div class="flex flex-col gap-1 flex-1">
        <label class="text-xs font-medium" style="color:var(--fg-muted)">Start date</label>
        <input
          v-model="form.start_date"
          type="date"
          class="w-full h-9 px-3 rounded-lg border text-sm"
          style="border-color:var(--border);background:var(--bg-panel);color:var(--fg)"
        />
        <p v-if="form.errors.start_date" class="text-xs text-red-500">{{ form.errors.start_date }}</p>
      </div>
      <div class="flex flex-col gap-1 flex-1">
        <label class="text-xs font-medium" style="color:var(--fg-muted)">End date</label>
        <input
          v-model="form.end_date"
          type="date"
          class="w-full h-9 px-3 rounded-lg border text-sm"
          style="border-color:var(--border);background:var(--bg-panel);color:var(--fg)"
        />
        <p v-if="form.errors.end_date" class="text-xs text-red-500">{{ form.errors.end_date }}</p>
      </div>
    </div>

    <!-- Sprint goal -->
    <div class="flex flex-col gap-1">
      <label class="text-xs font-medium" style="color:var(--fg-muted)">Sprint goal <span style="color:var(--fg-subtle)">(optional)</span></label>
      <textarea
        v-model="form.goal"
        rows="2"
        class="w-full px-3 py-2 rounded-lg border text-sm resize-none"
        style="border-color:var(--border);background:var(--bg-panel);color:var(--fg)"
        placeholder="What do you want to achieve this sprint?"
      />
    </div>

    <template #footer>
      <button type="button" class="btn-ghost h-8 px-3 text-sm rounded-lg" @click="close">
        Cancel <kbd class="ml-1 text-[10px] px-1 rounded border" style="border-color:var(--border)">Esc</kbd>
      </button>
      <button
        type="button"
        :disabled="!form.name.trim() || form.processing"
        class="btn-primary h-8 px-4 text-sm rounded-lg"
        @click="submit"
      >
        Create sprint
        <kbd class="ml-1 text-[10px] px-1 rounded" style="background:rgba(255,255,255,.15)">⌘↵</kbd>
      </button>
    </template>
  </AppModal>
</template>

<script setup>
import { ref, watch, nextTick } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AppModal from '@/Components/UI/AppModal.vue'

const props = defineProps({
  show:      { type: Boolean, required: true },
  projectId: { type: Number,  required: true },
})
const emit = defineEmits(['close'])

const nameInput = ref(null)

const today = new Date().toISOString().split('T')[0]
const twoWeeks = new Date(Date.now() + 14 * 86400000).toISOString().split('T')[0]

const form = useForm({
  name:       '',
  start_date: today,
  end_date:   twoWeeks,
  goal:       '',
})

watch(() => props.show, val => {
  if (val) {
    form.reset()
    form.name       = ''
    form.start_date = new Date().toISOString().split('T')[0]
    form.end_date   = new Date(Date.now() + 14 * 86400000).toISOString().split('T')[0]
    form.goal       = ''
    nextTick(() => nameInput.value?.focus())
  }
})

function submit() {
  if (!form.name.trim()) return
  form.post(route('sprints.store', props.projectId), {
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
