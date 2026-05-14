<template>
  <Teleport to="body">
    <Transition name="ws-settings">
      <div
        v-if="show"
        class="ws-settings-backdrop"
        @click.self="$emit('close')"
      >
        <div class="ws-settings-panel" @click.stop>
          <!-- Header -->
          <div class="ws-settings-header">
            <span class="ws-settings-title">Workspace settings</span>
            <button type="button" class="ws-close-btn" @click="$emit('close')">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
          </div>

          <!-- Tab bar -->
          <div class="ws-tab-bar">
            <button
              type="button"
              class="ws-tab-btn"
              :class="{ 'ws-tab-btn--active': activeTab === 'general' }"
              @click="activeTab = 'general'"
            >General</button>
            <button
              type="button"
              class="ws-tab-btn ws-tab-btn--danger-tab"
              :class="{ 'ws-tab-btn--danger-active': activeTab === 'danger' }"
              @click="activeTab = 'danger'"
            >Danger zone</button>
          </div>

          <!-- General tab -->
          <div v-if="activeTab === 'general'" class="ws-tab-content">
            <form class="ws-form" @submit.prevent="saveWorkspace">
              <!-- Live preview -->
              <div class="ws-preview-card">
                <div class="ws-preview-avatar" :style="{ background: form.color }">
                  {{ initials }}
                </div>
                <div>
                  <div class="ws-preview-name">{{ form.name || 'Your workspace' }}</div>
                  <div class="ws-preview-slug">taskline.app/{{ computedSlug }}</div>
                </div>
              </div>

              <!-- Workspace name -->
              <div class="ws-field">
                <label class="ws-field-label">Workspace name</label>
                <input
                  v-model="form.name"
                  type="text"
                  class="ws-field-input"
                  placeholder="e.g. Acme Corp"
                  maxlength="100"
                  @input="onNameInput"
                />
              </div>

              <!-- URL slug -->
              <div class="ws-field">
                <label class="ws-field-label">URL slug</label>
                <div class="ws-slug-container">
                  <span class="ws-slug-prefix">taskline.app/</span>
                  <input
                    v-model="slug"
                    type="text"
                    class="ws-slug-input"
                    @input="slugTouched = true"
                  />
                </div>
              </div>

              <!-- Color -->
              <div class="ws-field">
                <label class="ws-field-label">Color</label>
                <div class="ws-color-row">
                  <button
                    v-for="c in COLORS"
                    :key="c"
                    type="button"
                    class="ws-color-swatch"
                    :class="{ 'ws-color-swatch--active': form.color === c }"
                    :style="{
                      background: c,
                      outlineColor: form.color === c ? c : 'transparent',
                      boxShadow: form.color === c ? `0 0 0 4px ${c}21` : 'none',
                    }"
                    @click="form.color = c"
                  />
                </div>
              </div>

              <!-- Footer -->
              <div class="ws-form-footer">
                <button
                  type="submit"
                  class="ws-btn-primary"
                  :disabled="!form.name.trim() || form.processing"
                >
                  {{ form.processing ? 'Saving…' : form.recentlySuccessful ? '✓ Saved' : 'Save changes' }}
                </button>
              </div>
            </form>
          </div>

          <!-- Danger zone tab -->
          <div v-if="activeTab === 'danger'" class="ws-tab-content">
            <div class="ws-danger-card">
              <div class="ws-danger-title">Delete this workspace</div>
              <p class="ws-danger-desc">
                Permanently delete <strong style="color:var(--fg)">{{ workspace?.name ?? 'this workspace' }}</strong>
                and all its projects, sprints, tasks, and member data.
                <strong>This cannot be undone.</strong>
              </p>

              <!-- Step 0 -->
              <button
                v-if="deleteStep === 0"
                type="button"
                class="ws-btn-danger-ghost"
                @click="deleteStep = 1"
              >Delete workspace...</button>

              <!-- Step 1 -->
              <div v-if="deleteStep === 1" class="ws-delete-confirm">
                <label class="ws-field-label">
                  Type <code class="ws-code-chip">{{ workspace?.name }}</code> to confirm:
                </label>
                <input
                  ref="deleteInput"
                  v-model="deleteConfirmText"
                  type="text"
                  class="ws-field-input ws-field-input--danger"
                />
                <div class="ws-delete-actions">
                  <button
                    type="button"
                    class="ws-btn-cancel-ghost"
                    @click="resetDelete"
                  >Cancel</button>
                  <button
                    type="button"
                    class="ws-btn-danger-ghost"
                    :disabled="deleteConfirmText !== workspace?.name"
                    @click="confirmDelete"
                  >I understand, delete this workspace</button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, computed, watch, nextTick } from 'vue'
import { usePage, useForm, router } from '@inertiajs/vue3'
import { useToast } from '@/composables/useToast'

const props = defineProps({
  show: { type: Boolean, required: true },
})
const emit = defineEmits(['close'])

const page = usePage()
const workspace = computed(() => page.props.workspace)
const { toast } = useToast()

const COLORS = ['#4f46e5', '#0891b2', '#16a34a', '#7c3aed', '#d97706', '#dc2626', '#0d9488']

const activeTab = ref('general')

// Form state backed by Inertia
const form = useForm({ name: '', color: '#4f46e5' })
const slug = ref('')
const slugTouched = ref(false)

// Delete state
const deleteStep = ref(0)
const deleteConfirmText = ref('')
const deleteInput = ref(null)

const initials = computed(() => {
  const words = (form.name || '').trim().split(/\s+/)
  if (words.length === 0 || !words[0]) return 'W'
  return words.slice(0, 2).map(w => w[0]?.toUpperCase() ?? '').join('')
})

function deriveSlug(name) {
  return name
    .toLowerCase()
    .replace(/[^a-z0-9\s-]/g, '')
    .trim()
    .replace(/\s+/g, '-')
}

const computedSlug = computed(() => slug.value || 'your-workspace')

function onNameInput() {
  if (!slugTouched.value) {
    slug.value = deriveSlug(form.name)
  }
}

function saveWorkspace() {
  if (!form.name.trim() || form.processing) return
  form.patch(route('settings.workspace.update'), {
    preserveScroll: true,
    onSuccess: () => toast('Workspace settings saved'),
  })
}

function resetDelete() {
  deleteStep.value = 0
  deleteConfirmText.value = ''
}

function confirmDelete() {
  if (deleteConfirmText.value !== workspace.value?.name) return
  router.delete(route('settings.workspace.destroy'), {
    data: { confirmation: deleteConfirmText.value },
    onSuccess: () => {
      resetDelete()
      emit('close')
    },
  })
}

// Reset state when modal opens
watch(() => props.show, (val) => {
  if (val) {
    activeTab.value = 'general'
    form.name  = workspace.value?.name  ?? ''
    form.color = workspace.value?.color ?? '#4f46e5'
    slug.value = deriveSlug(workspace.value?.name ?? '')
    slugTouched.value = false
    form.clearErrors()
    resetDelete()
  }
})

// Autofocus delete input when step 1 appears
watch(deleteStep, (val) => {
  if (val === 1) {
    nextTick(() => deleteInput.value?.focus())
  }
})
</script>

<style scoped>

/* ── Backdrop ── */
.ws-settings-backdrop {
  position: fixed;
  inset: 0;
  z-index: 60;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(0, 0, 0, 0.18);
  backdrop-filter: blur(2px);
}

/* ── Panel ── */
.ws-settings-panel {
  width: 100%;
  max-width: 520px;
  max-height: 88vh;
  overflow-y: auto;
  background: var(--bg-panel);
  border: 1px solid var(--border);
  border-radius: 12px;
  box-shadow: 0 12px 40px rgba(20, 20, 17, 0.10);
  display: flex;
  flex-direction: column;
  font-family: var(--font-ui);
}

/* ── Header ── */
.ws-settings-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px;
  border-bottom: 1px solid var(--border);
  position: sticky;
  top: 0;
  background: var(--bg-panel);
  z-index: 1;
}

.ws-settings-title {
  font-size: 15px;
  font-weight: 600;
  color: var(--fg);
}

.ws-close-btn {
  width: 20px;
  height: 20px;
  padding: 0;
  border: none;
  background: none;
  color: var(--fg-subtle);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
}

/* ── Tab bar ── */
.ws-tab-bar {
  display: flex;
  padding: 0 20px;
  border-bottom: 1px solid var(--border);
}

.ws-tab-btn {
  padding: 10px 14px;
  font-size: 13px;
  font-weight: 400;
  color: var(--fg-muted);
  background: none;
  border: none;
  border-bottom: 2px solid transparent;
  margin-bottom: -1px;
  cursor: pointer;
  font-family: var(--font-ui);
}

.ws-tab-btn--active {
  font-weight: 600;
  color: var(--fg);
  border-bottom-color: var(--accent);
}

.ws-tab-btn--danger-tab.ws-tab-btn--danger-active {
  font-weight: 600;
  color: var(--status-blocked);
  border-bottom-color: var(--status-blocked);
}

/* ── Tab content ── */
.ws-tab-content {
  padding: 24px;
}

/* ── Form ── */
.ws-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

/* ── Preview card ── */
.ws-preview-card {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 14px;
  background: var(--bg-sunken);
  border: 1px solid var(--border);
  border-radius: 8px;
}

.ws-preview-avatar {
  width: 36px;
  height: 36px;
  border-radius: 9px;
  color: #fff;
  font-weight: 700;
  font-size: 13px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  transition: background 150ms;
}

.ws-preview-name {
  font-weight: 600;
  font-size: 13px;
  color: var(--fg);
}

.ws-preview-slug {
  font-size: 12px;
  color: var(--fg-muted);
  font-family: var(--font-mono);
  margin-top: 1px;
}

/* ── Field ── */
.ws-field {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.ws-field-label {
  font-size: 12px;
  color: var(--fg-muted);
}

.ws-field-input {
  height: 36px;
  padding: 0 10px;
  border-radius: 6px;
  border: 1px solid var(--border);
  background: var(--bg-panel);
  color: var(--fg);
  font-size: 14px;
  font-family: var(--font-ui);
  outline: none;
  width: 100%;
  box-sizing: border-box;
  transition: border-color 120ms, box-shadow 120ms;
}

.ws-field-input:focus {
  border-color: var(--accent);
  box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.12);
}

.ws-field-input--danger {
  border-color: rgba(220, 38, 38, 0.4);
}

.ws-field-input--danger:focus {
  border-color: var(--status-blocked);
  box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.10);
}

/* ── Slug input ── */
.ws-slug-container {
  display: flex;
  border: 1px solid var(--border);
  border-radius: 6px;
  overflow: hidden;
}

.ws-slug-prefix {
  display: flex;
  align-items: center;
  padding: 0 10px;
  font-size: 13px;
  font-family: var(--font-mono);
  color: var(--fg-muted);
  background: var(--bg-sunken);
  border-right: 1px solid var(--border);
  white-space: nowrap;
  user-select: none;
}

.ws-slug-input {
  flex: 1;
  height: 36px;
  padding: 0 10px;
  border: none;
  background: var(--bg-panel);
  color: var(--fg);
  font-size: 13px;
  font-family: var(--font-mono);
  outline: none;
  min-width: 0;
}

.ws-slug-container:focus-within {
  border-color: var(--accent);
  box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.12);
}

/* ── Color swatches ── */
.ws-color-row {
  display: flex;
  gap: 8px;
}

.ws-color-swatch {
  width: 26px;
  height: 26px;
  border-radius: 7px;
  border: none;
  cursor: pointer;
  outline: 2px solid transparent;
  outline-offset: 2px;
  transition: outline-color 100ms, transform 80ms;
  padding: 0;
}

.ws-color-swatch:hover {
  transform: scale(1.1);
}

.ws-color-swatch--active {
  outline-style: solid;
}

/* ── Form footer ── */
.ws-form-footer {
  display: flex;
  justify-content: flex-end;
  padding-top: 8px;
  border-top: 1px solid var(--border);
}

.ws-btn-primary {
  height: 36px;
  padding: 0 16px;
  border-radius: 6px;
  background: var(--accent);
  color: #fff;
  border: none;
  font-size: 13px;
  font-weight: 500;
  font-family: var(--font-ui);
  cursor: pointer;
  transition: background 80ms;
  white-space: nowrap;
}

.ws-btn-primary:hover:not(:disabled) {
  background: #4338ca;
}

.ws-btn-primary:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* ── Danger card ── */
.ws-danger-card {
  border: 1px solid rgba(220, 38, 38, 0.3);
  background: rgba(220, 38, 38, 0.04);
  border-radius: 8px;
  padding: 16px 20px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.ws-danger-title {
  font-weight: 600;
  font-size: 13px;
  color: var(--status-blocked);
}

.ws-danger-desc {
  font-size: 13px;
  color: var(--fg-muted);
  line-height: 1.5;
  margin: 0;
}

.ws-btn-danger-ghost {
  height: 36px;
  padding: 0 14px;
  border-radius: 6px;
  background: transparent;
  color: var(--status-blocked);
  border: 1px solid rgba(220, 38, 38, 0.4);
  font-size: 13px;
  font-weight: 500;
  font-family: var(--font-ui);
  cursor: pointer;
  transition: background 80ms;
  white-space: nowrap;
}

.ws-btn-danger-ghost:hover:not(:disabled) {
  background: rgba(220, 38, 38, 0.06);
}

.ws-btn-danger-ghost:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.ws-btn-cancel-ghost {
  height: 36px;
  padding: 0 14px;
  border-radius: 6px;
  background: transparent;
  color: var(--fg-muted);
  border: 1px solid var(--border);
  font-size: 13px;
  font-weight: 500;
  font-family: var(--font-ui);
  cursor: pointer;
  transition: background 80ms;
}

.ws-btn-cancel-ghost:hover {
  background: var(--bg-sunken);
}

/* ── Delete confirmation ── */
.ws-delete-confirm {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.ws-delete-actions {
  display: flex;
  gap: 8px;
  margin-top: 4px;
}

.ws-code-chip {
  font-family: var(--font-mono);
  font-size: 12px;
  background: var(--bg-sunken);
  border: 1px solid var(--border);
  border-radius: 4px;
  padding: 1px 6px;
  color: var(--fg);
}

/* ── Entry animation ── */
.ws-settings-enter-active {
  transition: opacity 180ms ease;
}
.ws-settings-leave-active {
  transition: opacity 120ms ease;
}
.ws-settings-enter-from,
.ws-settings-leave-to {
  opacity: 0;
}

.ws-settings-enter-active .ws-settings-panel {
  animation: ws-slide-up 180ms ease;
}

@keyframes ws-slide-up {
  from {
    opacity: 0;
    transform: translateY(8px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
</style>
