<template>
  <span
    ref="trigger"
    role="button"
    :aria-label="ariaLabel || 'Project access'"
    aria-haspopup="dialog"
    :aria-expanded="open"
    :title="triggerTitle"
    class="pac-trigger"
    :class="{ 'pac-trigger--open': open, 'pac-trigger--locked': locked }"
    @click="toggleOpen"
  >
    <span class="pac-dots">
      <span v-if="shownDots.length === 0" class="pac-dot pac-dot--empty" />
      <span
        v-for="(p, i) in shownDots"
        :key="p.id"
        class="pac-dot"
        :style="{ background: p.color, marginLeft: i === 0 ? 0 : '-3px' }"
      />
    </span>
    <span class="pac-label">{{ label }}</span>
  </span>

  <Teleport to="body">
    <div
      v-if="open"
      ref="popover"
      role="dialog"
      aria-label="Project access"
      class="pac-popover"
      :style="popoverStyle"
    >
      <div class="pac-popover__head">
        <span class="pac-popover__title">Project access</span>
        <span v-if="locked" class="pac-popover__hint">Owner — all projects</span>
        <button
          v-else
          type="button"
          class="pac-popover__select-all"
          @click.stop="setAll(!allOn)"
        >
          {{ allOn ? 'Clear all' : 'Select all' }}
        </button>
      </div>

      <div class="pac-popover__list">
        <button
          v-for="p in projects"
          :key="p.id"
          type="button"
          class="pac-item"
          :class="{ 'pac-item--disabled': locked }"
          :disabled="locked"
          @click.stop="toggle(p.id)"
        >
          <span
            class="pac-check"
            :class="{ 'pac-check--on': selectedIds.includes(p.id) }"
          >
            <svg
              v-if="selectedIds.includes(p.id)"
              width="10"
              height="10"
              viewBox="0 0 16 16"
              fill="none"
              stroke="currentColor"
              stroke-width="2.5"
              stroke-linecap="round"
              stroke-linejoin="round"
            >
              <path d="M3 8.5l3 3 7-7" />
            </svg>
          </span>
          <span class="pac-color" :style="{ background: p.color }" />
          <span class="pac-name">{{ p.name }}</span>
          <span class="pac-key">{{ p.key }}</span>
        </button>
      </div>

      <div v-if="locked" class="pac-popover__foot">
        Workspace owners always have access to every project. Change the role to edit access.
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  projects:   { type: Array, default: () => [] },
  locked:     { type: Boolean, default: false },
  ariaLabel:  { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue', 'change'])

const POP_WIDTH = 240

const open     = ref(false)
const trigger  = ref(null)
const popover  = ref(null)
const pos      = ref({ top: 0, left: 0, placeAbove: false })

const selectedIds = computed(() => {
  if (props.locked) return props.projects.map(p => p.id)
  return Array.isArray(props.modelValue) ? props.modelValue : []
})

const allOn = computed(() =>
  props.projects.length > 0 && selectedIds.value.length === props.projects.length
)

const shownDots = computed(() => {
  const map = new Map(props.projects.map(p => [p.id, p]))
  return selectedIds.value.map(id => map.get(id)).filter(Boolean).slice(0, 3)
})

const label = computed(() => {
  if (allOn.value) return 'All projects'
  const n = selectedIds.value.length
  if (n === 0) return 'No access'
  return `${n} project${n === 1 ? '' : 's'}`
})

const triggerTitle = computed(() => {
  if (props.locked) return 'Owner — all projects'
  const n = selectedIds.value.length
  if (n === 0) return 'No projects'
  const map = new Map(props.projects.map(p => [p.id, p]))
  return selectedIds.value.map(id => map.get(id)?.name).filter(Boolean).join(', ')
})

const popoverStyle = computed(() => {
  const placeAbove = pos.value.placeAbove
  return {
    top:    placeAbove ? 'auto' : pos.value.top + 'px',
    bottom: placeAbove ? (window.innerHeight - pos.value.top) + 'px' : 'auto',
    left:   pos.value.left + 'px',
    width:  POP_WIDTH + 'px',
  }
})

function toggle(pid) {
  if (props.locked) return
  const current = selectedIds.value
  const next = current.includes(pid)
    ? current.filter(x => x !== pid)
    : [...current, pid]
  commit(next)
}

function setAll(on) {
  if (props.locked) return
  commit(on ? props.projects.map(p => p.id) : [])
}

function commit(next) {
  emit('update:modelValue', next)
  emit('change', next)
}

function toggleOpen() {
  open.value = !open.value
}

function recompute() {
  const t = trigger.value
  if (!t) return
  const r  = t.getBoundingClientRect()
  const vw = window.innerWidth
  const vh = window.innerHeight
  const spaceBelow = vh - r.bottom
  const placeAbove = spaceBelow < 240 && r.top > spaceBelow
  let left = r.right - POP_WIDTH
  if (left < 8) left = 8
  if (left + POP_WIDTH > vw - 8) left = vw - 8 - POP_WIDTH
  const top = placeAbove ? r.top - 6 : r.bottom + 6
  pos.value = { top, left, placeAbove }
}

function onDocDown(e) {
  if (popover.value && popover.value.contains(e.target)) return
  if (trigger.value && trigger.value.contains(e.target)) return
  open.value = false
}

function onKey(e) {
  if (e.key === 'Escape') open.value = false
}

function onReflow() {
  recompute()
}

watch(open, (isOpen) => {
  if (isOpen) {
    nextTick(recompute)
    document.addEventListener('mousedown', onDocDown)
    document.addEventListener('keydown', onKey)
    window.addEventListener('resize', onReflow)
    window.addEventListener('scroll', onReflow, true)
  } else {
    document.removeEventListener('mousedown', onDocDown)
    document.removeEventListener('keydown', onKey)
    window.removeEventListener('resize', onReflow)
    window.removeEventListener('scroll', onReflow, true)
  }
})

onBeforeUnmount(() => {
  document.removeEventListener('mousedown', onDocDown)
  document.removeEventListener('keydown', onKey)
  window.removeEventListener('resize', onReflow)
  window.removeEventListener('scroll', onReflow, true)
})
</script>

<style scoped>
.pac-trigger {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  height: 24px;
  padding: 0 8px 0 6px;
  border: 1px solid var(--border);
  border-radius: 99px;
  background: var(--bg-panel);
  color: var(--fg-muted);
  font-size: 11px;
  font-weight: 500;
  font-family: inherit;
  cursor: pointer;
  white-space: nowrap;
  user-select: none;
  transition: background 80ms, border-color 80ms;
}
.pac-trigger:hover { background: var(--bg-hover); }
.pac-trigger--open { background: var(--bg-hover); }
.pac-trigger--locked { opacity: 0.85; }

.pac-dots { display: inline-flex; }
.pac-dot {
  width: 8px;
  height: 8px;
  border-radius: 99px;
  box-shadow: 0 0 0 1.5px var(--bg-panel);
}
.pac-dot--empty {
  background: transparent;
  border: 1px dashed var(--border);
  box-shadow: none;
}

.pac-label { line-height: 1; }

.pac-popover {
  position: fixed;
  max-height: 320px;
  display: flex;
  flex-direction: column;
  background: var(--bg-panel);
  border: 1px solid var(--border);
  border-radius: 8px;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
  z-index: 300;
  overflow: hidden;
}

.pac-popover__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 12px 6px;
  border-bottom: 1px solid var(--border);
  flex-shrink: 0;
}
.pac-popover__title {
  font-size: 11px;
  font-weight: 600;
  color: var(--fg-subtle);
  letter-spacing: 0.04em;
  text-transform: uppercase;
}
.pac-popover__hint {
  font-size: 11px;
  color: var(--fg-subtle);
  font-weight: 500;
}
.pac-popover__select-all {
  background: none;
  border: none;
  padding: 0;
  color: var(--accent);
  font-size: 11px;
  font-weight: 500;
  font-family: inherit;
  cursor: pointer;
}
.pac-popover__select-all:hover { text-decoration: underline; }

.pac-popover__list {
  overflow-y: auto;
  padding: 4px 0;
  flex: 1;
  min-height: 0;
}

.pac-item {
  display: flex;
  align-items: center;
  gap: 8px;
  width: 100%;
  padding: 6px 12px;
  background: none;
  border: none;
  text-align: left;
  font-size: 12px;
  font-family: inherit;
  color: var(--fg);
  cursor: pointer;
  transition: background 60ms;
}
.pac-item:hover:not(.pac-item--disabled) { background: var(--bg-hover); }
.pac-item--disabled { cursor: default; opacity: 0.7; }

.pac-check {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 14px;
  height: 14px;
  border-radius: 4px;
  border: 1px solid var(--border);
  background: transparent;
  color: #fff;
  flex-shrink: 0;
}
.pac-check--on {
  border-color: var(--accent);
  background: var(--accent);
}

.pac-color {
  width: 8px;
  height: 8px;
  border-radius: 99px;
  flex-shrink: 0;
}
.pac-name {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.pac-key {
  font-family: var(--font-mono);
  font-size: 10px;
  color: var(--fg-subtle);
}

.pac-popover__foot {
  padding: 6px 12px 8px;
  border-top: 1px solid var(--border);
  font-size: 11px;
  color: var(--fg-subtle);
  flex-shrink: 0;
}
</style>
