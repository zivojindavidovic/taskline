<template>
  <AppModal :show="show" title="New project" @close="close">
    <!-- Name -->
    <div class="flex flex-col gap-1">
      <input
        ref="nameInput"
        v-model="form.name"
        class="w-full h-10 px-3 rounded-lg border text-sm"
        style="border-color:var(--border);background:var(--bg-panel);color:var(--fg)"
        placeholder="Project name"
        @keydown.meta.enter="submit"
        @keydown.ctrl.enter="submit"
        @input="syncKey"
      />
      <p v-if="form.errors.name" class="text-xs text-red-500">{{ form.errors.name }}</p>
    </div>

    <!-- Key -->
    <div class="flex flex-col gap-1">
      <label class="text-xs font-medium" style="color:var(--fg-muted)">Project key</label>
      <input
        v-model="form.key"
        class="w-full h-9 px-3 rounded-lg border text-sm font-mono uppercase"
        style="border-color:var(--border);background:var(--bg-panel);color:var(--fg)"
        placeholder="e.g. MOB"
        maxlength="6"
        @input="form.key = form.key.toUpperCase().replace(/[^A-Z0-9]/g, '')"
      />
      <p v-if="form.errors.key" class="text-xs text-red-500">{{ form.errors.key }}</p>
    </div>

    <!-- Color -->
    <div class="flex flex-col gap-2">
      <label class="text-xs font-medium" style="color:var(--fg-muted)">Color</label>
      <div class="flex gap-2 flex-wrap">
        <button
          v-for="c in COLORS"
          :key="c"
          type="button"
          class="w-6 h-6 rounded-full border-2 transition-transform hover:scale-110"
          :style="{
            background: c,
            borderColor: form.color === c ? 'var(--fg)' : 'transparent',
          }"
          @click="form.color = c"
        />
      </div>
    </div>

    <template #footer>
      <button type="button" class="btn-ghost h-8 px-3 text-sm rounded-lg" @click="close">
        Cancel
      </button>
      <button
        type="button"
        :disabled="!form.name.trim() || !form.key.trim() || form.processing"
        class="btn-primary h-8 px-4 text-sm rounded-lg"
        @click="submit"
      >
        Create project
      </button>
    </template>
  </AppModal>
</template>

<script setup>
import { ref, watch, nextTick } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AppModal from '@/Components/UI/AppModal.vue'

const props = defineProps({
  show: { type: Boolean, required: true },
})
const emit = defineEmits(['close'])

const nameInput = ref(null)

const COLORS = [
  '#4f46e5', '#7c3aed', '#db2777', '#dc2626',
  '#ea580c', '#d97706', '#16a34a', '#0891b2',
  '#0284c7', '#64748b',
]

const form = useForm({
  name:  '',
  key:   '',
  color: '#4f46e5',
})

watch(() => props.show, val => {
  if (val) {
    form.reset()
    form.color = '#4f46e5'
    nextTick(() => nameInput.value?.focus())
  }
})

function syncKey() {
  const words = form.name.trim().split(/\s+/)
  if (words.length === 1) {
    form.key = words[0].slice(0, 4).toUpperCase().replace(/[^A-Z0-9]/g, '')
  } else {
    form.key = words.map(w => w[0]).join('').slice(0, 4).toUpperCase().replace(/[^A-Z0-9]/g, '')
  }
}

function submit() {
  if (!form.name.trim() || !form.key.trim()) return
  form.post(route('projects.store'), {
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
