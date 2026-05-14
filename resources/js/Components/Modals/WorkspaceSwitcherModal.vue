<template>
  <AppModal :show="show" title="Workspaces" @close="$emit('close')">
    <!-- Workspace list -->
    <div class="ws-list">
      <button
        v-for="ws in workspaces"
        :key="ws.id"
        type="button"
        class="ws-item"
        :class="{ 'ws-item--active': ws.id === currentWorkspace?.id }"
        @click="switchTo(ws)"
      >
        <div class="ws-avatar" :style="{ background: ws.color }">
          {{ ws.name[0]?.toUpperCase() }}
        </div>
        <div class="flex-1 min-w-0 text-left">
          <p class="text-sm font-medium truncate" style="color:var(--fg)">{{ ws.name }}</p>
          <p class="text-xs" style="color:var(--fg-muted)">
            {{ ws.id === currentWorkspace?.id ? 'Current workspace' : 'Click to switch' }}
          </p>
        </div>
        <svg v-if="ws.id === currentWorkspace?.id" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="color:var(--accent);flex-shrink:0"><polyline points="20 6 9 17 4 12"/></svg>
      </button>

      <div v-if="!workspaces.length" class="text-sm text-center py-4" style="color:var(--fg-muted)">
        No workspaces yet.
      </div>
    </div>

    <div class="h-px my-1" style="background:var(--border)" />

    <!-- Create new workspace -->
    <div v-if="!showCreate" class="px-1">
      <button type="button" class="new-ws-btn" @click="showCreate = true">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Create new workspace
      </button>
    </div>

    <div v-else class="create-form">
      <p class="text-xs font-semibold mb-3" style="color:var(--fg-muted);text-transform:uppercase;letter-spacing:.05em">New workspace</p>
      <div class="flex flex-col gap-3">
        <input
          ref="nameInput"
          v-model="createForm.name"
          class="field-input"
          placeholder="Workspace name"
          @keydown.enter="submitCreate"
          @keydown.escape="showCreate = false"
        />
        <div>
          <p class="text-xs mb-1.5" style="color:var(--fg-muted)">Color</p>
          <div class="flex gap-2 flex-wrap">
            <button
              v-for="c in COLORS"
              :key="c"
              type="button"
              class="color-swatch"
              :style="{
                background: c,
                outline: createForm.color === c ? '2px solid var(--fg)' : '2px solid transparent',
                outlineOffset: '2px',
              }"
              @click="createForm.color = c"
            />
          </div>
        </div>
        <p v-if="createForm.errors.name" class="text-xs" style="color:var(--status-blocked)">{{ createForm.errors.name }}</p>
        <div class="flex gap-2 justify-end">
          <button type="button" class="btn-ghost" @click="showCreate = false">Cancel</button>
          <button
            type="button"
            class="btn-primary"
            :disabled="!createForm.name.trim() || createForm.processing"
            @click="submitCreate"
          >
            <svg v-if="createForm.processing" class="spinner" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>
            Create
          </button>
        </div>
      </div>
    </div>
  </AppModal>
</template>

<script setup>
import { ref, watch, nextTick } from 'vue'
import { useForm, router, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import AppModal from '@/Components/UI/AppModal.vue'

const props = defineProps({
  show: { type: Boolean, required: true },
})
const emit = defineEmits(['close'])

const page = usePage()
const workspaces = computed(() => page.props.workspaces ?? [])
const currentWorkspace = computed(() => page.props.workspace)

const showCreate = ref(false)
const nameInput  = ref(null)

const COLORS = [
  '#4f46e5', '#7c3aed', '#db2777', '#dc2626',
  '#ea580c', '#d97706', '#16a34a', '#0891b2',
  '#0284c7', '#64748b',
]

const createForm = useForm({ name: '', color: '#4f46e5' })

watch(() => props.show, val => {
  if (!val) {
    showCreate.value = false
    createForm.reset()
  }
})

watch(showCreate, val => {
  if (val) nextTick(() => nameInput.value?.focus())
})

function switchTo(ws) {
  if (ws.id === currentWorkspace.value?.id) return
  router.post(route('workspaces.switch'), { workspace_id: ws.id }, {
    onSuccess: () => emit('close'),
  })
}

function submitCreate() {
  if (!createForm.name.trim()) return
  createForm.post(route('workspaces.store'), {
    onSuccess: () => {
      showCreate.value = false
      createForm.reset()
      emit('close')
    },
  })
}
</script>

<style scoped>
.ws-list { display: flex; flex-direction: column; gap: 2px; }
.ws-item {
  display: flex; align-items: center; gap: 10px;
  padding: 8px 10px; border-radius: var(--r-md);
  width: 100%; background: none; border: none; cursor: pointer;
  transition: background 80ms;
}
.ws-item:hover { background: var(--bg-hover); }
.ws-item--active { background: var(--accent-soft); }

.ws-avatar {
  width: 32px; height: 32px; border-radius: 8px; flex-shrink: 0;
  color: #fff; font-size: 14px; font-weight: 700;
  display: flex; align-items: center; justify-content: center;
}

.new-ws-btn {
  width: 100%; display: flex; align-items: center; gap: 8px;
  padding: 8px 10px; border-radius: var(--r-md);
  background: none; border: none; cursor: pointer;
  font-size: 13px; color: var(--accent); font-weight: 500;
  transition: background 80ms;
}
.new-ws-btn:hover { background: var(--accent-soft); }

.create-form {
  padding: 8px 4px 4px;
}
.field-input {
  width: 100%; height: 34px; padding: 0 10px; box-sizing: border-box;
  border-radius: var(--r-md); border: 1px solid var(--border);
  background: var(--bg-sunken); color: var(--fg); font-size: 13px;
  outline: none; transition: border-color 80ms;
}
.field-input:focus { border-color: var(--accent); }
.color-swatch {
  width: 20px; height: 20px; border-radius: 50%; border: none;
  cursor: pointer; transition: transform 80ms;
}
.color-swatch:hover { transform: scale(1.15); }

.btn-ghost {
  height: 30px; padding: 0 12px; border-radius: var(--r-md);
  background: transparent; border: 1px solid var(--border);
  color: var(--fg-muted); font-size: 12px; font-weight: 500; cursor: pointer;
  transition: background 80ms;
}
.btn-ghost:hover { background: var(--bg-hover); }

.btn-primary {
  height: 30px; padding: 0 14px; border-radius: var(--r-md);
  background: var(--accent); color: var(--accent-fg);
  border: none; font-size: 12px; font-weight: 500; cursor: pointer;
  display: flex; align-items: center; gap: 5px;
  transition: background 80ms;
}
.btn-primary:hover:not(:disabled) { background: var(--accent-hover); }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }

@keyframes spin { to { transform: rotate(360deg); } }
.spinner { animation: spin 0.7s linear infinite; }
</style>
