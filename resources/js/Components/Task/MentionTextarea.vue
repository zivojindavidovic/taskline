<template>
  <div class="mention-wrap" @keydown="onKeydown">
    <textarea
      ref="taRef"
      :value="modelValue"
      :placeholder="placeholder"
      :class="textareaClass"
      @input="onInput"
      @keyup="onKeyup"
      @click="onCursor"
      @focus="onCursor"
    />
    <div v-if="dropdownOpen && filtered.length" class="mention-dropdown" :style="dropdownStyle">
      <button
        v-for="(u, i) in filtered"
        :key="u.id"
        type="button"
        class="mention-item"
        :class="{ active: i === activeIndex }"
        @mousedown.prevent="pick(u)"
        @mouseenter="activeIndex = i"
      >
        <span class="mention-avatar" :style="{ background: u.avatar_color || '#94948c' }">
          {{ (u.name || '?').charAt(0).toUpperCase() }}
        </span>
        <span class="mention-name">{{ u.name }}</span>
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, nextTick } from 'vue'

const props = defineProps({
  modelValue:     { type: String, default: '' },
  users:          { type: Array,  default: () => [] },
  placeholder:    { type: String, default: '' },
  textareaClass:  { type: String, default: 'input textarea' },
})
const emit = defineEmits(['update:modelValue'])

const taRef        = ref(null)
const dropdownOpen = ref(false)
const triggerStart = ref(-1)
const query        = ref('')
const activeIndex  = ref(0)
const dropdownStyle = ref({ top: '100%', left: '0' })

const filtered = computed(() => {
  const q = query.value.toLowerCase()
  const list = props.users.filter(u => !q || (u.name || '').toLowerCase().includes(q) || (u.email || '').toLowerCase().includes(q))
  return list.slice(0, 8)
})

function onInput(e) {
  emit('update:modelValue', e.target.value)
  detectMentionTrigger()
}
function onKeyup() { detectMentionTrigger() }
function onCursor() { detectMentionTrigger() }

function detectMentionTrigger() {
  const ta = taRef.value
  if (!ta) return
  const text = ta.value
  const caret = ta.selectionStart ?? text.length
  const upTo = text.slice(0, caret)
  // Find the last `@` not preceded by an alphanumeric char (so emails don't trigger)
  const match = upTo.match(/(^|[^A-Za-z0-9_])@([\w.-]*)$/)
  if (match) {
    triggerStart.value = caret - match[2].length - 1   // position of `@`
    query.value       = match[2]
    dropdownOpen.value = true
    activeIndex.value  = 0
  } else {
    dropdownOpen.value = false
    triggerStart.value = -1
    query.value        = ''
  }
}

function onKeydown(e) {
  if (!dropdownOpen.value || !filtered.value.length) return
  if (e.key === 'ArrowDown') {
    e.preventDefault()
    activeIndex.value = (activeIndex.value + 1) % filtered.value.length
  } else if (e.key === 'ArrowUp') {
    e.preventDefault()
    activeIndex.value = (activeIndex.value - 1 + filtered.value.length) % filtered.value.length
  } else if (e.key === 'Enter' || e.key === 'Tab') {
    e.preventDefault()
    pick(filtered.value[activeIndex.value])
  } else if (e.key === 'Escape') {
    dropdownOpen.value = false
  }
}

function pick(user) {
  if (!user) return
  const ta = taRef.value
  const text = ta.value
  const caret = ta.selectionStart ?? text.length
  const before = text.slice(0, triggerStart.value)
  const after  = text.slice(caret)
  const token  = `@[${user.name}](user:${user.id}) `
  const next   = before + token + after
  emit('update:modelValue', next)
  dropdownOpen.value = false
  nextTick(() => {
    ta.focus()
    const pos = (before + token).length
    ta.setSelectionRange(pos, pos)
  })
}

defineExpose({
  focus() { taRef.value?.focus() },
})
</script>

<style scoped>
.mention-wrap { position: relative; width: 100%; }
.mention-dropdown {
  position: absolute;
  z-index: 60;
  top: 100%;
  left: 0;
  margin-top: 4px;
  min-width: 220px;
  max-height: 240px;
  overflow-y: auto;
  background: var(--bg-panel, #fff);
  border: 1px solid var(--border, #e5e5e3);
  border-radius: 8px;
  box-shadow: 0 8px 24px rgba(0,0,0,0.12);
  padding: 4px;
}
.mention-item {
  display: flex;
  gap: 8px;
  align-items: center;
  width: 100%;
  padding: 6px 8px;
  border: 0;
  background: transparent;
  border-radius: 6px;
  cursor: pointer;
  font-size: 13px;
  text-align: left;
  color: var(--fg, inherit);
}
.mention-item.active,
.mention-item:hover {
  background: var(--bg-hover, rgba(0,0,0,0.05));
}
.mention-avatar {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 22px;
  height: 22px;
  border-radius: 50%;
  color: #fff;
  font-size: 11px;
  font-weight: 600;
  flex-shrink: 0;
}
.mention-name { flex: 1; }
</style>
