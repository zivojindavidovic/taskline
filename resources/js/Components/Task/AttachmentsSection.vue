<template>
  <div class="panel-section">
    <div class="section-head" :style="{ marginBottom: attachments.length ? '8px' : '6px' }">
      <div class="head-left">
        <span class="panel-section-title">Attachments</span>
        <span v-if="attachments.length" class="count-mono">{{ attachments.length }}</span>
      </div>
      <button
        v-if="!locked"
        type="button"
        class="btn ghost sm"
        @click="triggerPick"
      >
        <PaperclipIcon /> Attach
      </button>
    </div>

    <!-- List -->
    <div v-if="attachments.length" class="attachments-list">
      <div
        v-for="att in attachments"
        :key="att.id"
        class="attachment-row"
      >
        <div
          class="attachment-thumb"
          :class="{ 'is-image': isImage(att) && att.url }"
        >
          <img v-if="isImage(att) && att.url" :src="att.url" :alt="att.original_name" />
          <template v-else>
            <PaperclipIcon />
            <span v-if="ext(att.original_name)" class="ext">{{ ext(att.original_name) }}</span>
          </template>
        </div>
        <div class="attachment-meta">
          <div class="attachment-name" :title="att.original_name">{{ att.original_name }}</div>
          <div class="attachment-sub">
            {{ fmtSize(att.size) }}<template v-if="att.uploader"> · {{ att.uploader.name }}</template>
          </div>
        </div>
        <a
          v-if="att.url"
          :href="att.url"
          target="_blank"
          download
          class="btn ghost icon-only sm"
          title="Download"
        ><DownloadIcon /></a>
        <button
          v-if="!locked"
          type="button"
          class="btn ghost icon-only sm"
          :aria-label="`Remove ${att.original_name}`"
          title="Remove"
          @click="$emit('remove', att.id)"
        ><CloseIcon /></button>
      </div>
    </div>

    <!-- Dropzone -->
    <div
      v-if="!locked"
      class="attachment-dropzone"
      :class="{ 'drag-over': isDragOver, compact: attachments.length > 0, empty: attachments.length === 0 }"
      role="button"
      tabindex="0"
      @click="triggerPick"
      @dragover.prevent="isDragOver = true"
      @dragleave="onDragLeave"
      @drop.prevent="onDrop"
      @keydown.enter.space.prevent="triggerPick"
    >
      <PaperclipIcon />
      <span>
        {{ isDragOver ? 'Drop to attach' : attachments.length === 0 ? 'Drag files here or click to attach' : 'Add more files' }}
      </span>
      <input ref="hiddenInput" type="file" multiple hidden @change="onPick" />
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { PaperclipIcon, CloseIcon, DownloadIcon } from '@/Components/UI/Icons.vue'

const props = defineProps({
  attachments: { type: Array, default: () => [] },
  locked:      { type: Boolean, default: false },
})
const emit = defineEmits(['upload', 'remove'])

const isDragOver  = ref(false)
const hiddenInput = ref(null)

function triggerPick() { if (!props.locked) hiddenInput.value?.click() }
function onPick(e) {
  Array.from(e.target.files || []).forEach(f => emit('upload', f))
  e.target.value = ''
}
function onDragLeave(e) {
  if (!e.currentTarget.contains(e.relatedTarget)) isDragOver.value = false
}
function onDrop(e) {
  isDragOver.value = false
  if (props.locked) return
  Array.from(e.dataTransfer?.files || []).forEach(f => emit('upload', f))
}
function isImage(att) { return att.mime_type?.startsWith('image/') }
function ext(name)    { return (name?.split('.').pop() || '').slice(0, 4).toUpperCase() }
function fmtSize(b) {
  if (b == null) return ''
  if (b < 1024)        return b + ' B'
  if (b < 1024 * 1024) return (b / 1024).toFixed(b < 10240 ? 1 : 0) + ' KB'
  return (b / (1024 * 1024)).toFixed(b < 10 * 1024 * 1024 ? 1 : 0) + ' MB'
}
</script>

<style scoped>
/* All selectors carry the scoped data-attribute, beating Tailwind utility specificity. */
.panel-section { display: flex; flex-direction: column; gap: 8px; }
.panel-section-title {
  font-size: 12px; color: var(--fg-muted); font-weight: 500;
  text-transform: uppercase; letter-spacing: 0.04em; line-height: 1;
}
.section-head { display: flex; align-items: center; justify-content: space-between; }
.head-left    { display: flex; align-items: center; gap: 6px; }
.count-mono   { font-size: 11px; color: var(--fg-subtle); font-family: var(--font-mono); }

.attachments-list { display: flex; flex-direction: column; gap: 6px; margin-bottom: 10px; }
.attachment-row {
  display: flex; align-items: center; gap: 10px;
  padding: 8px 10px;
  border: 1px solid var(--border);
  border-radius: 6px;
  background: var(--bg-panel);
  transition: border-color 100ms, background 100ms;
}
.attachment-row:hover { border-color: var(--border-strong); background: var(--bg-hover); }
.attachment-thumb {
  position: relative; width: 36px; height: 36px;
  border-radius: 4px;
  background: var(--bg-sunken);
  border: 1px solid var(--border);
  flex-shrink: 0; display: grid; place-items: center;
  color: var(--fg-muted); overflow: hidden;
}
.attachment-thumb :deep(svg) { width: 16px; height: 16px; }
.attachment-thumb.is-image    { background: var(--bg-active); border-color: transparent; }
.attachment-thumb img         { width: 100%; height: 100%; object-fit: cover; display: block; }
.attachment-thumb .ext {
  position: absolute; bottom: 1px; right: 1px;
  font-family: var(--font-mono); font-size: 8px; line-height: 1;
  padding: 1px 3px; background: var(--fg); color: var(--bg-panel);
  border-radius: 2px; letter-spacing: 0.04em; font-weight: 600;
}
.attachment-meta { flex: 1; min-width: 0; }
.attachment-name { font-size: 13px; font-weight: 500; color: var(--fg); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.attachment-sub  { font-size: 11px; color: var(--fg-subtle); font-family: var(--font-mono); }

.attachment-dropzone {
  display: flex; align-items: center; justify-content: center; gap: 8px;
  padding: 14px 12px;
  border: 1px dashed var(--border-strong);
  border-radius: 6px;
  color: var(--fg-muted);
  font-size: 13px;
  cursor: pointer;
  background: transparent;
  transition: border-color 120ms, color 120ms, background 120ms;
  user-select: none;
}
.attachment-dropzone.compact   { padding: 10px 12px; font-size: 12px; }
.attachment-dropzone:hover     { border-color: var(--accent); color: var(--accent); background: color-mix(in oklab, var(--accent) 5%, transparent); }
.attachment-dropzone.drag-over { border-style: solid; border-color: var(--accent); color: var(--accent); background: color-mix(in oklab, var(--accent) 10%, transparent); }
.attachment-dropzone :deep(svg) { width: 14px; height: 14px; }

/* Local button system — keeps this component self-contained */
.btn {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 0 12px; height: 28px;
  border-radius: 6px;
  font-size: 13px; font-weight: 500;
  cursor: pointer;
  border: 1px solid transparent;
  background: none; color: inherit;
  font-family: inherit;
  white-space: nowrap;
  transition: background 80ms;
}
.btn.ghost          { color: var(--fg-muted); }
.btn.ghost:hover    { background: var(--bg-hover); color: var(--fg); }
.btn.sm             { height: 24px; padding: 0 8px; font-size: 12px; }
.btn.icon-only      { padding: 0; width: 28px; justify-content: center; }
.btn.icon-only.sm   { width: 24px; }
.btn :deep(svg)     { width: 14px; height: 14px; }
</style>
