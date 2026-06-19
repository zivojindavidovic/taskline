<template>
  <div class="side-panel-backdrop" @click="$emit('close')" />

  <div class="side-panel" role="dialog" aria-label="New task">
    <!-- Header — mirrors TaskPanel: project dot + NEW kicker -->
    <div class="panel-header">
      <span class="dot" :style="{ background: projectColor }" />
      <span class="id mono">NEW</span>
      <div class="spacer" />
      <button type="button" class="btn ghost icon-only sm" aria-label="Close" @click="$emit('close')">
        <CloseIcon />
      </button>
    </div>

    <!-- Body — same rhythm as TaskPanel -->
    <div class="panel-body">
      <!-- Title (full-width input styled like contenteditable .panel-title) -->
      <input
        ref="titleInput"
        v-model="form.title"
        class="panel-title-input"
        :class="{ invalid: form.errors.title }"
        placeholder="Task title"
        spellcheck="false"
        @keydown.meta.enter="submit"
        @keydown.ctrl.enter="submit"
        @keydown.escape="$emit('close')"
      />
      <p v-if="form.errors.title" class="field-error">{{ form.errors.title }}</p>

      <!-- Properties grid -->
      <div class="panel-section">
        <div class="props">
          <div class="key">Status</div>
          <div class="val">
            <DropdownMenu>
              <template #trigger>
                <span class="prop-pill">
                  <span class="dot" :style="{ background: selectedColumn?.color }" />
                  <span>{{ selectedColumn?.name ?? '—' }}</span>
                </span>
              </template>
              <div>
                <MenuItem
                  v-for="col in columns"
                  :key="col.id"
                  @click="form.board_column_id = col.id"
                >
                  <span class="check-slot"><CheckIcon v-if="col.id === form.board_column_id" class="check" /></span>
                  <span class="dot" :style="{ background: col.color }" />
                  <span>{{ col.name }}</span>
                </MenuItem>
              </div>
            </DropdownMenu>
          </div>

          <div class="key">Assignee</div>
          <div class="val">
            <DropdownMenu :width="220">
              <template #trigger>
                <span class="prop-pill">
                  <template v-if="selectedUser">
                    <Avatar :name="selectedUser.name" size="sm" />
                    <span>{{ selectedUser.name }}</span>
                  </template>
                  <template v-else>
                    <span class="avatar-empty">?</span>
                    <span class="muted">Unassigned</span>
                  </template>
                </span>
              </template>
              <div>
                <div class="menu-label">Assign to</div>
                <MenuItem @click="form.assignee_id = null">
                  <span class="check-slot"><CheckIcon v-if="!form.assignee_id" class="check" /></span>
                  Unassigned
                </MenuItem>
                <div class="menu-divider" />
                <MenuItem v-for="u in allUsers" :key="u.id" @click="form.assignee_id = u.id">
                  <span class="check-slot"><CheckIcon v-if="u.id === form.assignee_id" class="check" /></span>
                  <Avatar :name="u.name" size="sm" />
                  <span>{{ u.name }}</span>
                </MenuItem>
              </div>
            </DropdownMenu>
          </div>

          <div class="key">Priority</div>
          <div class="val">
            <DropdownMenu>
              <template #trigger>
                <span class="prop-pill"><PriorityBadge :priority="form.priority" show-label /></span>
              </template>
              <div>
                <MenuItem v-for="p in PRIORITIES" :key="p.id" @click="form.priority = p.id">
                  <span class="check-slot"><CheckIcon v-if="p.id === form.priority" class="check" /></span>
                  <PriorityBadge :priority="p.id" show-label />
                </MenuItem>
              </div>
            </DropdownMenu>
          </div>

          <div class="key">Sprint</div>
          <div class="val">
            <DropdownMenu v-if="sprints.length">
              <template #trigger>
                <span class="prop-pill">
                  <LightningIcon class="dim-icon" />
                  <span :class="{ muted: !selectedSprint }">{{ selectedSprint?.name ?? 'No sprint' }}</span>
                </span>
              </template>
              <div>
                <MenuItem @click="form.sprint_id = null">
                  <span class="check-slot"><CheckIcon v-if="!form.sprint_id" class="check" /></span>
                  <span class="muted">No sprint</span>
                </MenuItem>
                <div class="menu-divider" />
                <MenuItem
                  v-for="s in sprints"
                  :key="s.id"
                  :disabled="s.locked"
                  @click="!s.locked && (form.sprint_id = s.id)"
                >
                  <span class="check-slot"><CheckIcon v-if="s.id === form.sprint_id" class="check" /></span>
                  <LightningIcon class="dim-icon" />
                  <span class="grow">{{ s.name }}</span>
                  <span v-if="s.locked" class="locked-tail">locked</span>
                </MenuItem>
              </div>
            </DropdownMenu>
            <span v-else class="muted">—</span>
          </div>

          <div class="key">Dates</div>
          <div class="val">
            <DropdownMenu :width="280">
              <template #trigger>
                <span class="prop-pill">
                  <CalendarIcon class="dim-icon" />
                  <span v-if="form.start_date || form.due_date">{{ dateRangeLabel }}</span>
                  <span v-else class="muted">Set dates…</span>
                </span>
              </template>
              <div class="dropdown-pad" @click.stop>
                <div class="menu-label">Date range</div>
                <div class="date-fields">
                  <label class="field-block">
                    <span class="field-label">Start</span>
                    <input
                      type="date"
                      :value="form.start_date ?? ''"
                      class="input"
                      @change="e => form.start_date = e.target.value || null"
                    />
                  </label>
                  <label class="field-block">
                    <span class="field-label">Due</span>
                    <input
                      type="date"
                      :value="form.due_date ?? ''"
                      class="input"
                      @change="e => form.due_date = e.target.value || null"
                    />
                  </label>
                  <button
                    v-if="form.start_date || form.due_date"
                    type="button"
                    class="btn ghost sm clear-btn"
                    @click="form.start_date = null; form.due_date = null"
                  >
                    <CloseIcon /> Clear dates
                  </button>
                </div>
              </div>
            </DropdownMenu>
          </div>

          <div class="key">Tags</div>
          <div class="val">
            <DropdownMenu :width="220" keep-open>
              <template #trigger>
                <span class="prop-pill">
                  <span v-if="form.tags.length" class="tags">
                    <span v-for="tag in form.tags" :key="tag" class="tag">{{ tag }}</span>
                  </span>
                  <span v-else class="muted">+ Add tags</span>
                </span>
              </template>
              <div>
                <div class="menu-label">Tags</div>
                <div class="dropdown-pad" @click.stop>
                  <input
                    v-model="tagSearch"
                    class="input"
                    autofocus
                    placeholder="Find or create a tag…"
                    @keydown.enter.prevent="addNewTag"
                    @keydown.stop
                  />
                </div>
                <MenuItem
                  v-if="canAddDraftTag"
                  @click.stop="addNewTag"
                >
                  <span class="check-slot"><PlusIcon class="check" /></span>
                  <span>Create <strong>{{ normalizeTag(tagSearch) }}</strong></span>
                </MenuItem>
                <div v-if="filteredTagOptions.length === 0 && !canAddDraftTag" class="muted small-pad">No matches</div>
                <MenuItem
                  v-for="tag in filteredTagOptions"
                  :key="tag"
                  data-keep-open
                  @click.stop="toggleTag(tag)"
                >
                  <span class="check-slot"><CheckIcon v-if="form.tags.includes(tag)" class="check" /></span>
                  <span>{{ tag }}</span>
                </MenuItem>
              </div>
            </DropdownMenu>
          </div>
        </div>
      </div>

      <!-- Description -->
      <div class="panel-section">
        <div class="panel-section-title">Description</div>
        <textarea
          v-model="form.description"
          class="description-textarea"
          rows="4"
          placeholder="Add a description…"
        />
      </div>

      <!-- Attachments -->
      <AttachmentsSection
        :attachments="pendingAttachmentPreviews"
        :locked="false"
        @upload="addPendingFile"
        @remove="removePendingFile"
      />
    </div>

    <!-- Footer -->
    <div class="panel-footer">
      <button type="button" class="btn secondary sm" @click="$emit('close')">
        Cancel <span class="kbd">Esc</span>
      </button>
      <button
        type="button"
        class="btn primary"
        :disabled="!form.title.trim() || form.processing"
        @click="submit"
      >
        Create task <span class="kbd inverse">⌘↵</span>
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, nextTick } from 'vue'
import { useForm } from '@inertiajs/vue3'
import DropdownMenu from '@/Components/UI/DropdownMenu.vue'
import MenuItem from '@/Components/UI/MenuItem.vue'
import Avatar from '@/Components/UI/Avatar.vue'
import PriorityBadge from '@/Components/UI/PriorityBadge.vue'
import AttachmentsSection from '@/Components/Task/AttachmentsSection.vue'
import {
  CheckIcon, CloseIcon, LightningIcon, CalendarIcon, PlusIcon,
} from '@/Components/UI/Icons.vue'

const props = defineProps({
  show:          { type: Boolean, required: true },
  projectId:     { type: Number,  required: true },
  projectColor:  { type: String,  default: 'var(--accent)' },
  sprintId:      { type: Number,  default: null },
  sprints:       { type: Array,   default: () => [] },
  columns:       { type: Array,   default: () => [] },
  defaultColumn: { type: Number,  default: null },
  allUsers:      { type: Array,   default: () => [] },
  allTags:       { type: Array,   default: () => [] },
})
const emit = defineEmits(['close'])

const titleInput   = ref(null)
const tagSearch    = ref('')
const pendingFiles = ref([])

const PRIORITIES = [
  { id: 'urgent', label: 'Urgent' },
  { id: 'high',   label: 'High' },
  { id: 'med',    label: 'Medium' },
  { id: 'low',    label: 'Low' },
]
const ALL_TAGS = ['frontend', 'backend', 'design', 'bug', 'feature', 'infra', 'research', 'a11y', 'perf']

const form = useForm({
  project_id:      props.projectId,
  sprint_id:       props.sprintId,
  board_column_id: props.defaultColumn ?? props.columns[0]?.id,
  title:           '',
  description:     '',
  priority:        'med',
  assignee_id:     null,
  tags:            [],
  start_date:      null,
  due_date:        null,
})

watch(() => props.show, val => {
  if (val) {
    form.reset()
    form.project_id      = props.projectId
    form.sprint_id       = props.sprintId
    form.board_column_id = props.defaultColumn ?? props.columns[0]?.id
    pendingFiles.value   = []
    tagSearch.value      = ''
    nextTick(() => titleInput.value?.focus())
  }
})
watch(() => props.defaultColumn, v => { form.board_column_id = v ?? props.columns[0]?.id })

const selectedColumn = computed(() => props.columns.find(c => c.id === form.board_column_id))
const selectedUser   = computed(() => props.allUsers.find(u => u.id === form.assignee_id))
const selectedSprint = computed(() => props.sprints.find(s => s.id === form.sprint_id))

const allTagOptions = computed(() => [...new Set([...ALL_TAGS, ...props.allTags, ...form.tags])])
const filteredTagOptions = computed(() => {
  const q = tagSearch.value.trim().toLowerCase()
  if (!q) return allTagOptions.value
  return allTagOptions.value.filter(t => t.includes(q))
})
const canAddDraftTag = computed(() => {
  const n = normalizeTag(tagSearch.value)
  return n && !allTagOptions.value.includes(n)
})

const dateRangeLabel = computed(() => {
  const s = form.start_date, d = form.due_date
  if (s && d && s !== d) return `${formatDate(s)} → ${formatDate(d)}`
  return formatDate(d || s)
})

const pendingAttachmentPreviews = computed(() =>
  pendingFiles.value.map((file, i) => ({
    id: `pending-${i}`,
    original_name: file.name,
    mime_type: file.type,
    size: file.size,
    url: file.type.startsWith('image/') ? URL.createObjectURL(file) : null,
  }))
)

function normalizeTag(s) { return s.trim().toLowerCase().replace(/\s+/g, '-') }
function addNewTag() {
  const t = normalizeTag(tagSearch.value)
  if (!t) return
  if (!form.tags.includes(t)) form.tags.push(t)
  tagSearch.value = ''
}
function toggleTag(tag) {
  const idx = form.tags.indexOf(tag)
  if (idx === -1) form.tags.push(tag); else form.tags.splice(idx, 1)
}
function addPendingFile(file) { pendingFiles.value.push(file) }
function removePendingFile(id) {
  const idx = pendingAttachmentPreviews.value.findIndex(p => p.id === id)
  if (idx !== -1) pendingFiles.value.splice(idx, 1)
}
function formatDate(d) {
  if (!d) return ''
  return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}
function submit() {
  if (!form.title.trim()) return
  const data = { ...form.data(), files: pendingFiles.value }
  form.transform(() => data).post(route('tasks.store'), {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => emit('close'),
  })
}
</script>

<style scoped>
/* ============================================================
   Side panel shell — copied from design 1:1
   ============================================================ */
.side-panel-backdrop {
  position: fixed; inset: 0;
  background: rgba(0,0,0,0.15);
  z-index: 50;
  animation: ntp-fadeIn 120ms ease-out;
}
:global([data-theme="dark"]) .side-panel-backdrop { background: rgba(0,0,0,0.4); }
.side-panel {
  position: fixed; top: 0; right: 0;
  height: 100vh;
  width: var(--panel-w, 480px); max-width: 92vw;
  background: var(--bg-panel);
  border-left: 1px solid var(--border);
  box-shadow: var(--shadow-lg);
  z-index: 51;
  display: flex; flex-direction: column;
  animation: ntp-slideIn 180ms cubic-bezier(0.32, 0.72, 0, 1);
}
/* Full-width sheet on phones */
@media (max-width: 768px) {
  .side-panel { width: 100vw; max-width: 100vw; border-left: none; }
}
@keyframes ntp-fadeIn  { from { opacity: 0 } to { opacity: 1 } }
@keyframes ntp-slideIn { from { transform: translateX(40px); opacity: 0 } to { transform: none; opacity: 1 } }

/* Header */
.panel-header {
  display: flex; align-items: center; gap: 8px;
  padding: 12px 16px;
  border-bottom: 1px solid var(--border);
  flex-shrink: 0;
}
.panel-header > .dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.panel-header .id    { font-family: var(--font-mono); font-size: 12px; color: var(--fg-muted); }
.panel-header .spacer { flex: 1; }

/* Body */
.panel-body {
  flex: 1; overflow-y: auto;
  padding: 20px 24px;
  display: flex; flex-direction: column;
  gap: 20px;
}

/* Title field — looks like the contenteditable .panel-title in TaskPanel */
.panel-title-input {
  font-size: 20px; font-weight: 600; line-height: 1.3;
  color: var(--fg);
  border: 1px solid transparent;
  border-radius: 6px;
  padding: 6px 8px; margin: -6px -8px;
  width: calc(100% + 16px);
  background: transparent;
  font-family: inherit;
  outline: none;
}
.panel-title-input::placeholder { color: var(--fg-subtle); font-weight: 600; }
.panel-title-input:hover  { background: var(--bg-hover); }
.panel-title-input:focus  { border-color: var(--accent); background: var(--bg-panel); }
.panel-title-input.invalid { border-color: var(--status-blocked); }
.field-error { font-size: 12px; color: var(--status-blocked); margin: -12px 0 0; }

/* Section block */
.panel-section { display: flex; flex-direction: column; gap: 8px; }
.panel-section-title {
  font-size: 12px; color: var(--fg-muted); font-weight: 500;
  text-transform: uppercase; letter-spacing: 0.04em; line-height: 1;
}

/* Props grid (Status, Assignee, …) */
.props {
  display: grid;
  grid-template-columns: 100px 1fr;
  row-gap: 6px;
  align-items: center;
  font-size: 13px;
}
.props .key { color: var(--fg-muted); }
.props .val { color: var(--fg); min-width: 0; }

.prop-pill {
  display: inline-flex; align-items: center; gap: 8px;
  padding: 2px 6px; border-radius: 4px;
  cursor: pointer;
  border: 1px solid transparent;
  margin: -2px -6px;
  font-size: 13px;
  background: transparent;
  color: var(--fg);
}
.prop-pill:hover { background: var(--bg-hover); border-color: var(--border); }
.prop-pill > .dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.prop-pill .dim-icon :deep(svg),
.prop-pill > .dim-icon { width: 13px; height: 13px; color: var(--fg-muted); }

.muted  { color: var(--fg-muted); }
.grow   { flex: 1; }
.dim-icon { color: var(--fg-muted); }
.dim-icon :deep(svg) { width: 13px; height: 13px; }

.avatar-empty {
  width: 22px; height: 22px; border-radius: 50%;
  background: var(--bg-active); color: var(--fg-subtle);
  display: inline-flex; align-items: center; justify-content: center;
  font-size: 11px;
}

.tags { display: flex; flex-wrap: wrap; gap: 4px; }
.tag {
  display: inline-flex; align-items: center;
  font-size: 11px;
  padding: 1px 6px;
  border-radius: 3px;
  background: var(--bg-sunken);
  color: var(--fg-muted);
  border: 1px solid var(--border);
}

/* Menu helpers */
.check-slot   { width: 14px; display: inline-flex; align-items: center; }
.check        { width: 14px; height: 14px; color: var(--accent); }
.menu-label   { font-size: 12px; color: var(--fg-subtle); padding: 6px 8px 2px; font-weight: 500; }
.menu-divider { height: 1px; background: var(--border); margin: 4px 0; }

.locked-tail { margin-left: auto; font-size: 11px; color: var(--fg-subtle); }

.dropdown-pad { padding: 4px 8px 8px; }
.date-fields  { display: flex; flex-direction: column; gap: 8px; }
.field-block  { display: flex; flex-direction: column; gap: 4px; }
.field-label  { font-size: 11px; color: var(--fg-muted); }
.clear-btn    { align-self: flex-start; }
.small-pad    { font-size: 12px; padding: 6px 12px; }

/* Description — no contenteditable since this is creation flow */
.description-textarea {
  font-size: 14px; line-height: 1.6;
  color: var(--fg);
  border: 1px solid var(--border);
  border-radius: 6px;
  padding: 8px 10px;
  background: var(--bg-panel);
  resize: vertical;
  width: 100%;
  min-height: 80px;
  font-family: inherit;
  outline: none;
}
.description-textarea:hover  { background: var(--bg-hover); }
.description-textarea:focus  { border-color: var(--accent); background: var(--bg-panel); box-shadow: 0 0 0 3px var(--accent-soft); }
.description-textarea::placeholder { color: var(--fg-subtle); }

/* Inputs */
.input {
  display: flex; align-items: center;
  height: 32px;
  padding: 0 12px;
  border: 1px solid var(--border);
  background: var(--bg-panel);
  border-radius: 6px;
  font-size: 13px;
  width: 100%;
  color: var(--fg);
  font-family: inherit;
}
.input:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-soft); }
.input::placeholder { color: var(--fg-subtle); }
input[type="date"].input { padding: 0 8px; height: 28px; font-size: 13px; }

/* Footer */
.panel-footer {
  display: flex; align-items: center; justify-content: flex-end;
  gap: 8px;
  padding: 12px 16px;
  border-top: 1px solid var(--border);
  background: var(--bg-panel);
  flex-shrink: 0;
}

/* Buttons */
.btn {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 0 12px;
  height: 28px;
  border-radius: 6px;
  font-size: 13px; font-weight: 500;
  cursor: pointer;
  border: 1px solid transparent;
  background: none; color: inherit;
  font-family: inherit;
  white-space: nowrap;
  transition: background 80ms;
}
.btn:disabled       { opacity: 0.5; cursor: not-allowed; }
.btn.primary        { background: var(--accent); color: var(--accent-fg); border-color: var(--accent); }
.btn.primary:hover:not(:disabled) { background: var(--accent-hover); }
.btn.secondary      { background: var(--bg-panel); color: var(--fg); border-color: var(--border); }
.btn.secondary:hover:not(:disabled) { background: var(--bg-hover); }
.btn.ghost          { color: var(--fg-muted); }
.btn.ghost:hover:not(:disabled) { background: var(--bg-hover); color: var(--fg); }
.btn.sm             { height: 24px; padding: 0 8px; font-size: 12px; }
.btn.icon-only      { padding: 0; width: 28px; justify-content: center; }
.btn.icon-only.sm   { width: 24px; }
.btn :deep(svg)     { width: 14px; height: 14px; }

.kbd {
  display: inline-flex; align-items: center;
  font-family: var(--font-mono); font-size: 11px;
  padding: 1px 5px;
  border-radius: 3px;
  border: 1px solid var(--border);
  background: var(--bg-sunken);
  color: var(--fg-muted);
  margin-left: 4px;
}
.kbd.inverse {
  background: color-mix(in oklab, var(--accent-fg) 22%, transparent);
  border-color: color-mix(in oklab, var(--accent-fg) 30%, transparent);
  color: var(--accent-fg);
}
</style>
