<template>
  <!-- Backdrop -->
  <div
    class="fixed inset-0 z-50 animate-fade-in"
    style="background:rgba(0,0,0,0.15)"
    @click="$emit('close')"
  />

  <!-- Panel -->
  <div
    class="fixed top-0 right-0 h-screen flex flex-col z-[51] animate-slide-in-right"
    style="width:var(--panel-w);max-width:92vw;background:var(--bg-panel);border-left:1px solid var(--border);box-shadow:var(--shadow-lg)"
    role="dialog"
    aria-label="Task details"
  >
    <!-- Header -->
    <div class="flex items-center gap-2 px-4 py-3 border-b shrink-0" style="border-color:var(--border)">
      <span class="w-2 h-2 rounded-full shrink-0" :style="{ background: project?.color }" />
      <span class="font-mono text-xs flex-1" style="color:var(--fg-muted)">{{ task.key }}</span>

      <span
        v-if="locked"
        class="inline-flex items-center gap-1 text-xs font-medium px-2 py-0.5 rounded-full"
        style="background:color-mix(in oklab,var(--status-progress) 14%,var(--bg-panel));color:var(--status-progress);border:1px solid color-mix(in oklab,var(--status-progress) 30%,var(--border))"
      >
        <LockIcon class="w-3 h-3" /> Locked
      </span>

      <span
        v-if="task.completed"
        class="inline-flex items-center gap-1 text-xs font-medium px-2 py-0.5 rounded-full"
        style="background:color-mix(in oklab,var(--status-done) 14%,var(--bg-panel));color:var(--status-done);border:1px solid color-mix(in oklab,var(--status-done) 30%,var(--border))"
      >
        <CheckIcon class="w-3 h-3" /> Completed
      </span>

      <!-- More menu -->
      <DropdownMenu align="right">
        <template #trigger>
          <button type="button" class="p-1 rounded hover:bg-[var(--bg-hover)]" style="color:var(--fg-muted)">
            <MoreIcon class="w-4 h-4" />
          </button>
        </template>
        <div class="py-1">
          <MenuItem @click="copyToClipboard(task.key)"><CopyIcon class="w-3.5 h-3.5 text-[var(--fg-muted)]" /> Copy ID</MenuItem>
          <div class="h-px my-1" style="background:var(--border)" />
          <MenuItem danger :disabled="locked" @click="$emit('delete')"><TrashIcon class="w-3.5 h-3.5" /> Delete task</MenuItem>
        </div>
      </DropdownMenu>

      <button type="button" class="p-1 rounded hover:bg-[var(--bg-hover)]" style="color:var(--fg-muted)" @click="$emit('close')">
        <CloseIcon class="w-4 h-4" />
      </button>
    </div>

    <!-- Scrollable body -->
    <div class="flex-1 overflow-y-auto px-6 py-5 flex flex-col gap-5">

      <!-- Title -->
      <div
        ref="titleEl"
        class="text-xl font-semibold leading-snug rounded-lg p-2 -m-2 transition-colors"
        style="color:var(--fg)"
        :contenteditable="!locked"
        :data-placeholder="'Task title'"
        spellcheck="false"
        @blur="onTitleBlur"
      >{{ task.title }}</div>

      <!-- Completion CTA: in Done but not completed -->
      <div
        v-if="isInDone && !task.completed && !locked"
        class="flex items-center gap-3 p-4 rounded-lg"
        style="background:color-mix(in oklab,var(--status-done) 10%,var(--bg-panel));border:1.5px solid color-mix(in oklab,var(--status-done) 35%,var(--border))"
      >
        <div
          class="w-9 h-9 rounded-full border-2 border-dashed flex items-center justify-center shrink-0"
          style="border-color:color-mix(in oklab,var(--status-done) 50%,var(--border));color:color-mix(in oklab,var(--status-done) 80%,var(--fg-muted))"
        >
          <CheckIcon class="w-5 h-5" />
        </div>
        <div class="flex-1 min-w-0">
          <div class="font-semibold text-sm" style="color:var(--fg)">Ready to complete?</div>
          <div class="text-xs mt-0.5" style="color:var(--fg-muted)">
            Moving to <strong>Done</strong> isn't enough — confirm completion to close this task.
          </div>
        </div>
        <button type="button" class="btn-primary h-8 px-3 text-sm rounded-lg shrink-0" @click="$emit('complete')">
          <CheckIcon class="w-3.5 h-3.5" /> Mark as completed
        </button>
      </div>

      <!-- Completed banner -->
      <div
        v-if="task.completed"
        class="flex items-center gap-3 px-4 py-3 rounded-lg text-sm"
        style="background:color-mix(in oklab,var(--status-done) 10%,var(--bg-panel));border:1px solid color-mix(in oklab,var(--status-done) 25%,var(--border))"
      >
        <CheckIcon class="w-4 h-4 shrink-0" style="color:var(--status-done)" />
        <span class="flex-1" style="color:var(--fg)">
          Completed by <strong>{{ task.completed_by_user?.name ?? '—' }}</strong>
          {{ completedAgo }}.
        </span>
        <button v-if="!locked" type="button" class="btn-secondary h-7 px-3 text-xs rounded-lg" @click="$emit('uncomplete')">
          Reopen
        </button>
      </div>

      <!-- Properties grid -->
      <div class="grid gap-y-2" style="grid-template-columns:100px 1fr;font-size:13px;align-items:center">
        <!-- Status -->
        <span style="color:var(--fg-muted)">Status</span>
        <DropdownMenu>
          <template #trigger>
            <button type="button" class="prop-pill">
              <span class="w-2 h-2 rounded-full" :style="{ background: currentColumn?.color }" />
              {{ currentColumn?.name ?? '—' }}
            </button>
          </template>
          <div class="py-1">
            <MenuItem
              v-for="col in columns"
              :key="col.id"
              :disabled="locked"
              @click="!locked && $emit('update', { board_column_id: col.id })"
            >
              <CheckIcon v-if="col.id === task.board_column_id" class="w-3.5 h-3.5" style="color:var(--accent)" />
              <span v-else class="w-3.5 h-3.5 inline-block" />
              <span class="w-2 h-2 rounded-full shrink-0" :style="{ background: col.color }" />
              {{ col.name }}
            </MenuItem>
          </div>
        </DropdownMenu>

        <!-- Assignee -->
        <span style="color:var(--fg-muted)">Assignee</span>
        <DropdownMenu :width="220">
          <template #trigger>
            <button type="button" class="prop-pill">
              <Avatar v-if="task.assignee" :name="task.assignee.name" size="sm" />
              <span v-else class="w-6 h-6 rounded-full bg-[var(--bg-active)] inline-flex items-center justify-center text-xs" style="color:var(--fg-subtle)">?</span>
              {{ task.assignee?.name ?? 'Unassigned' }}
            </button>
          </template>
          <div class="py-1">
            <div class="px-2 pt-1 pb-2 text-xs font-medium" style="color:var(--fg-subtle)">Assign to</div>
            <MenuItem :disabled="locked" @click="!locked && $emit('update', { assignee_id: null })">
              <CheckIcon v-if="!task.assignee_id" class="w-3.5 h-3.5" style="color:var(--accent)" />
              <span v-else class="w-3.5 h-3.5 inline-block" />
              Unassigned
            </MenuItem>
            <div class="h-px my-1" style="background:var(--border)" />
            <MenuItem
              v-for="u in allUsers"
              :key="u.id"
              :disabled="locked"
              @click="!locked && $emit('update', { assignee_id: u.id })"
            >
              <CheckIcon v-if="u.id === task.assignee_id" class="w-3.5 h-3.5" style="color:var(--accent)" />
              <span v-else class="w-3.5 h-3.5 inline-block" />
              <Avatar :name="u.name" size="sm" />
              {{ u.name }}
            </MenuItem>
          </div>
        </DropdownMenu>

        <!-- Priority -->
        <span style="color:var(--fg-muted)">Priority</span>
        <DropdownMenu>
          <template #trigger>
            <button type="button" class="prop-pill">
              <PriorityBadge :priority="task.priority" show-label />
            </button>
          </template>
          <div class="py-1">
            <MenuItem
              v-for="p in PRIORITIES"
              :key="p.id"
              :disabled="locked"
              @click="!locked && $emit('update', { priority: p.id })"
            >
              <CheckIcon v-if="p.id === task.priority" class="w-3.5 h-3.5" style="color:var(--accent)" />
              <span v-else class="w-3.5 h-3.5 inline-block" />
              <PriorityBadge :priority="p.id" show-label />
            </MenuItem>
          </div>
        </DropdownMenu>

        <!-- Tags -->
        <span style="color:var(--fg-muted)">Tags</span>
        <DropdownMenu :width="200">
          <template #trigger>
            <button type="button" class="prop-pill flex-wrap">
              <template v-if="task.tags?.length">
                <span
                  v-for="tag in task.tags"
                  :key="tag"
                  class="text-[11px] px-1.5 py-0.5 rounded border"
                  style="background:var(--bg-sunken);color:var(--fg-muted);border-color:var(--border)"
                >{{ tag }}</span>
              </template>
              <span v-else style="color:var(--fg-muted)">+ Add tags</span>
            </button>
          </template>
          <div class="py-1">
            <div class="px-2 pt-1 pb-2 text-xs font-medium" style="color:var(--fg-subtle)">Tags</div>
            <MenuItem
              v-for="tag in ALL_TAGS"
              :key="tag"
              :disabled="locked"
              @click="!locked && toggleTag(tag)"
            >
              <CheckIcon v-if="task.tags?.includes(tag)" class="w-3.5 h-3.5" style="color:var(--accent)" />
              <span v-else class="w-3.5 h-3.5 inline-block" />
              {{ tag }}
            </MenuItem>
          </div>
        </DropdownMenu>

        <!-- Dates -->
        <span style="color:var(--fg-muted)">Due date</span>
        <label class="relative inline-flex items-center">
          <span
            class="prop-pill text-sm cursor-pointer"
            :class="{ 'opacity-50': locked }"
            @click="!locked && $refs.dueDateInput.showPicker?.()"
          >
            <CalendarIcon class="w-3.5 h-3.5" style="color:var(--fg-muted)" />
            {{ task.due_date ? formatDate(task.due_date) : 'Set date' }}
          </span>
          <input
            ref="dueDateInput"
            type="date"
            :value="task.due_date ? task.due_date.toString().slice(0, 10) : ''"
            :disabled="locked"
            class="absolute inset-0 opacity-0 w-full cursor-pointer"
            style="pointer-events:none"
            @change="e => $emit('update', { due_date: e.target.value || null })"
          />
        </label>

        <!-- Sprint -->
        <span style="color:var(--fg-muted)">Sprint</span>
        <span class="inline-flex items-center gap-1.5 text-sm" style="color:var(--fg)">
          <LightningIcon class="w-3.5 h-3.5" style="color:var(--fg-muted)" />
          {{ task.sprint?.name ?? '—' }}
        </span>
      </div>

      <!-- Subtasks -->
      <div>
        <div class="flex items-center justify-between mb-2">
          <div class="text-xs font-medium uppercase tracking-wider" style="color:var(--fg-muted)">
            Subtasks
            <span v-if="task.subtasks?.length" class="ml-1.5 normal-case font-normal" style="color:var(--fg-subtle)">{{ completedSubtasksCount }}/{{ task.subtasks.length }}</span>
          </div>
          <button
            v-if="!locked"
            type="button"
            class="inline-flex items-center gap-1 text-xs px-1.5 py-1 rounded transition-colors hover:bg-[var(--bg-hover)]"
            style="color:var(--fg-muted)"
            @click="openSubtaskInput"
          >
            <PlusIcon class="w-3.5 h-3.5" /> Add
          </button>
        </div>

        <!-- Progress bar -->
        <div v-if="task.subtasks?.length" class="h-1 rounded-full mb-3 overflow-hidden" style="background:var(--bg-sunken)">
          <div class="h-full rounded-full transition-all duration-300" style="background:var(--status-done)" :style="{ width: subtaskProgress + '%' }" />
        </div>

        <!-- Subtask list -->
        <div v-if="task.subtasks?.length" class="flex flex-col gap-0.5 mb-2">
          <div
            v-for="sub in task.subtasks"
            :key="sub.id"
            class="flex items-center gap-2.5 px-2 py-1.5 rounded-lg group"
            style="transition:background 80ms"
            @mouseenter="e => e.currentTarget.style.background='var(--bg-hover)'"
            @mouseleave="e => e.currentTarget.style.background='transparent'"
          >
            <button
              type="button"
              class="w-4 h-4 rounded border shrink-0 flex items-center justify-center transition-colors"
              :style="sub.completed ? 'background:var(--status-done);border-color:var(--status-done)' : 'background:transparent;border-color:var(--border-strong)'"
              :disabled="locked"
              @click="$emit('subtaskToggle', sub.id, !sub.completed)"
            >
              <CheckIcon v-if="sub.completed" class="w-2.5 h-2.5" style="color:#fff" />
            </button>
            <span
              class="flex-1 text-sm truncate"
              :class="{ 'line-through opacity-40': sub.completed }"
              style="color:var(--fg)"
            >{{ sub.title }}</span>
            <span class="text-xs font-mono shrink-0 opacity-0 group-hover:opacity-60 transition-opacity" style="color:var(--fg-subtle)">{{ sub.key }}</span>
            <Avatar v-if="sub.assignee" :name="sub.assignee.name" size="sm" class="shrink-0 opacity-0 group-hover:opacity-100 transition-opacity" />
          </div>
        </div>

        <!-- Empty state -->
        <p v-else-if="!showSubtaskInput" class="text-sm" style="color:var(--fg-subtle)">No subtasks.</p>

        <!-- Add subtask inline form -->
        <div v-if="showSubtaskInput" class="flex items-center gap-2 mt-1 px-2 py-1.5 rounded-lg" style="border:1px solid var(--border);background:var(--bg-sunken)">
          <div class="w-4 h-4 rounded border shrink-0" style="border-color:var(--border-strong)" />
          <input
            ref="subtaskInputEl"
            v-model="newSubtaskTitle"
            type="text"
            class="flex-1 text-sm bg-transparent border-none outline-none"
            style="color:var(--fg)"
            placeholder="Subtask title…"
            @keydown.enter.prevent="submitSubtask"
            @keydown.escape="cancelSubtask"
          />
          <button type="button" class="btn-primary h-6 px-2.5 text-xs rounded" :disabled="!newSubtaskTitle.trim()" @click="submitSubtask">Add</button>
          <button type="button" class="btn-ghost h-6 px-2.5 text-xs rounded" @click="cancelSubtask">✕</button>
        </div>
      </div>

      <!-- Description -->
      <div>
        <div class="text-xs font-medium uppercase tracking-wider mb-2" style="color:var(--fg-muted)">Description</div>
        <div
          ref="descEl"
          class="text-sm leading-relaxed rounded-lg p-2 -mx-2 transition-colors min-h-[40px]"
          :class="!task.description ? 'text-[var(--fg-subtle)]' : ''"
          style="color:var(--fg)"
          :contenteditable="!locked"
          :data-placeholder="'Add description…'"
          spellcheck="false"
          @blur="onDescBlur"
        >{{ task.description || '' }}</div>
      </div>

      <!-- Tabs: Comments / Activity -->
      <div>
        <div class="flex gap-4 border-b mb-3" style="border-color:var(--border)">
          <button
            v-for="tab in ['comments','activity']"
            :key="tab"
            type="button"
            class="pb-2 text-sm font-medium capitalize border-b-2 -mb-px transition-colors"
            :style="activeTab === tab
              ? 'color:var(--fg);border-color:var(--accent)'
              : 'color:var(--fg-muted);border-color:transparent'"
            @click="activeTab = tab"
          >
            {{ tab }}
            <span
              class="ml-1 text-[11px] px-1.5 py-0.5 rounded-full"
              style="background:var(--bg-active);color:var(--fg-muted)"
            >{{ tab === 'comments' ? task.comments?.length : task.audit_logs?.length }}</span>
          </button>
        </div>

        <!-- Comments tab -->
        <div v-if="activeTab === 'comments'" class="flex flex-col gap-1">
          <div v-if="!task.comments?.length" class="text-sm py-2" style="color:var(--fg-muted)">
            No comments yet. Start the discussion.
          </div>

          <div v-for="c in task.comments" :key="c.id">
            <div class="flex gap-3 py-2">
              <Avatar :name="c.user?.name" size="sm" />
              <div class="flex-1 min-w-0">
                <div class="flex items-baseline gap-2 mb-1">
                  <span class="text-sm font-semibold" style="color:var(--fg)">{{ c.user?.name }}</span>
                  <span class="text-xs" style="color:var(--fg-subtle)">{{ formatAgo(c.created_at) }}</span>
                </div>
                <div class="text-sm leading-relaxed whitespace-pre-wrap" style="color:var(--fg)">{{ c.body }}</div>
                <button
                  v-if="!locked"
                  type="button"
                  class="text-xs font-medium mt-1"
                  style="color:var(--fg-muted)"
                  @click="replyingTo = replyingTo === c.id ? null : c.id"
                >Reply</button>
              </div>
            </div>

            <!-- Replies -->
            <div v-if="c.replies?.length || replyingTo === c.id" class="ml-9 pl-3 border-l" style="border-color:var(--border)">
              <div v-for="r in c.replies" :key="r.id" class="flex gap-3 py-1.5">
                <Avatar :name="r.user?.name" size="sm" />
                <div class="flex-1 min-w-0">
                  <div class="flex items-baseline gap-2 mb-0.5">
                    <span class="text-sm font-semibold" style="color:var(--fg)">{{ r.user?.name }}</span>
                    <span class="text-xs" style="color:var(--fg-subtle)">{{ formatAgo(r.created_at) }}</span>
                  </div>
                  <div class="text-sm leading-relaxed whitespace-pre-wrap" style="color:var(--fg)">{{ r.body }}</div>
                </div>
              </div>

              <!-- Reply composer -->
              <div v-if="replyingTo === c.id" class="flex gap-2 py-2">
                <Avatar :name="currentUser?.name" size="sm" />
                <div class="flex-1">
                  <textarea
                    v-model="replyText"
                    rows="2"
                    class="w-full text-sm px-3 py-2 rounded-lg border resize-none"
                    style="border-color:var(--border);background:var(--bg-panel);color:var(--fg)"
                    :placeholder="`Reply…`"
                    autofocus
                  />
                  <div class="flex justify-end gap-2 mt-1.5">
                    <button type="button" class="btn-ghost h-7 px-3 text-xs rounded" @click="replyingTo = null; replyText = ''">Cancel</button>
                    <button
                      type="button"
                      :disabled="!replyText.trim()"
                      class="btn-primary h-7 px-3 text-xs rounded"
                      @click="submitReply(c.id)"
                    >Reply</button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Comment composer -->
          <div v-if="!locked" class="flex gap-3 mt-3">
            <Avatar :name="currentUser?.name" size="sm" />
            <div class="flex-1">
              <textarea
                v-model="newComment"
                rows="2"
                class="w-full text-sm px-3 py-2 rounded-lg border resize-none"
                style="border-color:var(--border);background:var(--bg-panel);color:var(--fg)"
                placeholder="Add a comment… (@ to mention)"
              />
              <div class="flex items-center justify-between mt-1.5">
                <span class="text-xs" style="color:var(--fg-subtle)">
                  Markdown supported · <kbd class="px-1 py-0.5 rounded text-[10px] border" style="border-color:var(--border);background:var(--bg-hover)">⌘↵</kbd> to send
                </span>
                <button
                  type="button"
                  :disabled="!newComment.trim()"
                  class="btn-primary h-7 px-3 text-xs rounded inline-flex items-center gap-1"
                  @click="submitComment"
                >
                  <SendIcon class="w-3 h-3" /> Comment
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Activity tab -->
        <div v-if="activeTab === 'activity'" class="flex flex-col">
          <div v-if="!task.audit_logs?.length" class="text-sm py-2" style="color:var(--fg-muted)">No activity yet.</div>
          <div
            v-for="(a, i) in [...(task.audit_logs ?? [])].reverse()"
            :key="i"
            class="flex gap-3 py-2 text-sm"
          >
            <div class="flex flex-col items-center w-6 shrink-0">
              <div class="w-1.5 h-1.5 rounded-full mt-1.5 shrink-0" style="background:var(--border-strong)" />
              <div v-if="i < (task.audit_logs?.length ?? 0) - 1" class="w-px flex-1 mt-1" style="background:var(--border)" />
            </div>
            <div class="flex-1 min-w-0 flex items-start justify-between gap-2">
              <span style="color:var(--fg-muted)">
                <strong style="color:var(--fg)">{{ a.user?.name ?? 'Someone' }}</strong>
                {{ auditLabel(a.action, a.meta) }}
              </span>
              <span class="text-xs shrink-0" style="color:var(--fg-subtle)">{{ formatAgo(a.created_at) }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, nextTick } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import Avatar from '@/Components/UI/Avatar.vue'
import PriorityBadge from '@/Components/UI/PriorityBadge.vue'
import DropdownMenu from '@/Components/UI/DropdownMenu.vue'
import MenuItem from '@/Components/UI/MenuItem.vue'
import {
  LockIcon, CheckIcon, MoreIcon, CopyIcon, TrashIcon,
  CloseIcon, LightningIcon, SendIcon, CalendarIcon, PlusIcon,
} from '@/Components/UI/Icons.vue'

const props = defineProps({
  task:     { type: Object, required: true },
  columns:  { type: Array,  default: () => [] },
  allUsers: { type: Array,  default: () => [] },
  project:  { type: Object, default: null },
  locked:   { type: Boolean, default: false },
})
const emit = defineEmits(['close', 'update', 'comment', 'reply', 'complete', 'uncomplete', 'delete', 'subtask', 'subtaskToggle'])

const page = usePage()
const currentUser = computed(() => page.props.auth.user)

const activeTab       = ref('comments')
const newComment      = ref('')
const replyText       = ref('')
const replyingTo      = ref(null)
const titleEl         = ref(null)
const descEl          = ref(null)
const dueDateInput    = ref(null)
const showSubtaskInput = ref(false)
const newSubtaskTitle  = ref('')
const subtaskInputEl   = ref(null)

const PRIORITIES = [
  { id: 'urgent', label: 'Urgent' },
  { id: 'high',   label: 'High' },
  { id: 'med',    label: 'Medium' },
  { id: 'low',    label: 'Low' },
]

const ALL_TAGS = ['frontend', 'backend', 'design', 'bug', 'feature', 'infra', 'research', 'a11y', 'perf']

const completedSubtasksCount = computed(() => props.task.subtasks?.filter(s => s.completed).length ?? 0)
const subtaskProgress = computed(() => {
  const total = props.task.subtasks?.length ?? 0
  return total === 0 ? 0 : Math.round((completedSubtasksCount.value / total) * 100)
})

const currentColumn = computed(() => props.columns.find(c => c.id === props.task.board_column_id))
const doneColumn    = computed(() => props.columns.find(c => c.name.toLowerCase() === 'done'))
const isInDone      = computed(() => props.task.board_column_id === doneColumn.value?.id)

const completedAgo = computed(() => {
  if (!props.task.completed_at) return ''
  return new Date(props.task.completed_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
})

function onTitleBlur(e) {
  const val = e.target.innerText.trim()
  if (val && val !== props.task.title) emit('update', { title: val })
}

function onDescBlur(e) {
  const val = e.target.innerText.trim()
  if (val !== (props.task.description ?? '')) emit('update', { description: val })
}

function toggleTag(tag) {
  const tags = [...(props.task.tags ?? [])]
  const idx  = tags.indexOf(tag)
  if (idx === -1) tags.push(tag)
  else tags.splice(idx, 1)
  emit('update', { tags })
}

function submitComment() {
  if (!newComment.value.trim()) return
  emit('comment', newComment.value.trim())
  newComment.value = ''
}

function submitReply(parentId) {
  if (!replyText.value.trim()) return
  emit('reply', parentId, replyText.value.trim())
  replyText.value = ''
  replyingTo.value = null
}

function openSubtaskInput() {
  showSubtaskInput.value = true
  nextTick(() => subtaskInputEl.value?.focus())
}

function submitSubtask() {
  const title = newSubtaskTitle.value.trim()
  if (!title) return
  emit('subtask', { title })
  newSubtaskTitle.value = ''
  showSubtaskInput.value = false
}

function cancelSubtask() {
  newSubtaskTitle.value = ''
  showSubtaskInput.value = false
}

function copyToClipboard(text) {
  navigator.clipboard?.writeText(text)
}

function formatDate(d) {
  return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

function formatAgo(date) {
  if (!date) return ''
  const d = new Date(date)
  const diff = (Date.now() - d) / 1000
  if (diff < 60)    return 'just now'
  if (diff < 3600)  return `${Math.floor(diff / 60)}m ago`
  if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
}

function auditLabel(action, meta) {
  switch (action) {
    case 'task.created':          return 'created this task'
    case 'task.completed':        return 'marked as completed'
    case 'task.reopened':         return 'reopened the task'
    case 'task.moved':            return `moved to ${meta?.column ?? '—'}`
    case 'task.renamed':          return `renamed the task`
    case 'task.assigned':         return `updated assignee`
    case 'task.priority_changed': return `changed priority to ${meta?.priority ?? '—'}`
    case 'task.tags_updated':     return 'updated tags'
    case 'task.updated':          return 'updated the task'
    case 'task.subtask_added':    return `added subtask "${meta?.title ?? ''}"`
    default:                      return action
  }
}
</script>

<style scoped>
.prop-pill {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 2px 8px;
  border-radius: 6px;
  cursor: pointer;
  border: 1px solid transparent;
  font-size: 13px;
  color: var(--fg);
  transition: background 80ms, border-color 80ms;
  background: transparent;
}
.prop-pill:hover {
  background: var(--bg-hover);
  border-color: var(--border);
}

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

.btn-secondary {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: var(--bg-panel);
  color: var(--fg);
  border: 1px solid var(--border);
  font-weight: 500;
  cursor: pointer;
  transition: background 80ms;
}
.btn-secondary:hover { background: var(--bg-hover); }

.btn-ghost {
  background: transparent;
  border: 1px solid var(--border);
  color: var(--fg-muted);
  cursor: pointer;
  font-weight: 500;
  transition: background 80ms;
}
.btn-ghost:hover { background: var(--bg-hover); color: var(--fg); }
</style>
