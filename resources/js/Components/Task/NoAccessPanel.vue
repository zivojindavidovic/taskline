<!--
  NoAccessPanel — shown when the user opens a task they can't view (e.g. from an
  Inbox @-mention into a project they're not a member of). Renders a redacted,
  blurred preview behind a lock card with a "Request access" action. Mirrors the
  TaskPanel side-panel shell so it slides in identically.
-->
<template>
  <div class="side-panel-backdrop" @click="$emit('close')" />
  <div class="side-panel" role="dialog" aria-label="Restricted task">
    <div class="panel-header">
      <span class="dot" :style="{ background: project?.color || 'var(--fg-subtle)' }" />
      <span class="id mono">{{ task?.key }}</span>
      <span class="lock-pill blocked"><LockIcon /> No access</span>
      <button type="button" class="btn ghost icon-only sm" aria-label="Close" @click="$emit('close')">
        <CloseIcon />
      </button>
    </div>

    <div class="panel-body">
      <!-- Redacted faux preview -->
      <div class="redacted" aria-hidden="true">
        <div class="bar" style="width:72%;height:22px;margin-bottom:18px" />
        <div class="bar" style="width:100%;height:40px;margin-bottom:28px;border-radius:8px" />
        <div class="meta-grid">
          <template v-for="(w, i) in metaWidths" :key="i">
            <div class="bar" style="width:70%;height:12px;margin:0" />
            <div class="bar" :style="{ width: w, height: '12px', margin: 0 }" />
          </template>
        </div>
        <div class="bar" style="width:40%;height:12px;margin:24px 0 12px" />
        <div class="bar" style="width:100%;height:12px" />
        <div class="bar" style="width:96%;height:12px" />
        <div class="bar" style="width:88%;height:12px" />
        <div class="bar" style="width:60%;height:12px" />
      </div>

      <!-- Lock overlay -->
      <div class="lock-overlay">
        <div class="lock-card">
          <div class="lock-badge"><LockIcon /></div>

          <div class="lock-title">You don't have access</div>
          <p class="lock-sub">
            This task lives in <strong>{{ project?.name || 'a private project' }}</strong>, which you're
            not a member of. Request access to view and collaborate on it.
          </p>

          <template v-if="!requested">
            <div v-if="approvers.length" class="approved-by">
              <span>Approved by</span>
              <span class="avatar-stack">
                <Avatar v-for="p in approvers" :key="p.id" :name="p.name" size="sm" />
              </span>
            </div>
            <button type="button" class="btn primary request-btn" :disabled="submitting" @click="handleRequest">
              <LockIcon /> {{ submitting ? 'Sending…' : 'Request access' }}
            </button>
          </template>

          <div v-else class="request-sent">
            <span class="sent-check"><CheckIcon /></span>
            <div>
              <div class="sent-title">Request sent</div>
              <div class="sent-sub">
                We've notified {{ approvers[0]?.name || 'the project admins' }}. You'll get an Inbox
                notification once it's approved.
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import axios from 'axios'
import Avatar from '@/Components/UI/Avatar.vue'
import { LockIcon, CheckIcon, CloseIcon } from '@/Components/UI/Icons.vue'
import { useToast } from '@/composables/useToast'

const props = defineProps({
  task:           { type: Object, default: null },
  project:        { type: Object, default: null },
  approvers:      { type: Array,  default: () => [] },
  pendingRequest: { type: Object, default: null },
})
const emit = defineEmits(['close', 'request'])
const { toast } = useToast()

const requested  = ref(props.pendingRequest?.status === 'pending')
const submitting = ref(false)
const metaWidths = ['55%', '40%', '48%', '62%', '35%']

async function handleRequest() {
  if (requested.value || submitting.value || !props.task?.id) return
  submitting.value = true
  try {
    await axios.post(route('tasks.access-requests.store', props.task.id))
    requested.value = true
    toast('Access request sent')
    emit('request', props.task)
  } catch (e) {
    toast(e.response?.data?.message || 'Could not send request')
  } finally {
    submitting.value = false
  }
}
</script>

<style scoped>
/* ===== Side panel shell (mirrors TaskPanel) ===== */
.side-panel-backdrop {
  position: fixed; inset: 0;
  background: rgba(0, 0, 0, 0.15);
  z-index: 50;
  animation: na-fadeIn 120ms ease-out;
}
:global([data-theme="dark"]) .side-panel-backdrop { background: rgba(0, 0, 0, 0.4); }
.side-panel {
  position: fixed; top: 0; right: 0;
  height: 100vh;
  width: var(--panel-w, 480px); max-width: 92vw;
  background: var(--bg-panel);
  border-left: 1px solid var(--border);
  box-shadow: var(--shadow-lg);
  z-index: 51;
  display: flex; flex-direction: column;
  animation: na-slideIn 180ms cubic-bezier(0.32, 0.72, 0, 1);
}
@keyframes na-fadeIn  { from { opacity: 0 } to { opacity: 1 } }
@keyframes na-slideIn { from { transform: translateX(40px); opacity: 0 } to { transform: none; opacity: 1 } }

.panel-header {
  display: flex; align-items: center; gap: 8px;
  padding: 12px 16px;
  border-bottom: 1px solid var(--border);
  flex-shrink: 0;
}
.panel-header > .dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.panel-header .id    { font-family: var(--font-mono); font-size: 12px; color: var(--fg-muted); flex: 1; }

.lock-pill {
  display: inline-flex; align-items: center; gap: 4px;
  font-size: 12px; font-weight: 500;
  padding: 2px 8px; border-radius: 999px;
}
.lock-pill :deep(svg) { width: 11px; height: 11px; }
.lock-pill.blocked {
  background: color-mix(in oklab, var(--status-blocked) 12%, var(--bg-panel));
  color: var(--status-blocked);
  border: 1px solid color-mix(in oklab, var(--status-blocked) 28%, var(--border));
}

/* ===== Body — redacted preview + lock overlay ===== */
.panel-body { flex: 1; position: relative; overflow: hidden; }
.redacted { filter: blur(7px); opacity: 0.45; pointer-events: none; user-select: none; padding: 24px; }
.bar { background: var(--bg-sunken); border-radius: 6px; margin-bottom: 10px; }
.meta-grid { display: grid; grid-template-columns: 92px 1fr; gap: 12px 16px; }

.lock-overlay {
  position: absolute; inset: 0;
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  padding: 28px; text-align: center;
  background: linear-gradient(to bottom, color-mix(in oklab, var(--bg-panel) 55%, transparent) 0%, var(--bg-panel) 30%);
}
.lock-card {
  width: 100%; max-width: 360px;
  background: var(--bg-panel);
  border: 1px solid var(--border);
  border-radius: var(--r-xl, 16px);
  box-shadow: var(--shadow-lg);
  padding: 28px 26px;
}
.lock-badge {
  width: 52px; height: 52px; border-radius: 50%;
  margin: 0 auto 16px;
  display: grid; place-items: center;
  background: color-mix(in oklab, var(--status-blocked) 12%, var(--bg-panel));
  color: var(--status-blocked);
}
.lock-badge :deep(svg) { width: 22px; height: 22px; }
.lock-title { font-size: 17px; font-weight: 600; margin-bottom: 6px; color: var(--fg); }
.lock-sub   { font-size: 13px; line-height: 1.55; margin-bottom: 18px; color: var(--fg-muted); }
.lock-sub strong { color: var(--fg); }

.approved-by {
  display: flex; align-items: center; justify-content: center; gap: 8px;
  font-size: 12px; color: var(--fg-subtle); margin-bottom: 16px;
}
.avatar-stack { display: inline-flex; }
.avatar-stack :deep(.avatar:not(:first-child)) { margin-left: -6px; box-shadow: 0 0 0 2px var(--bg-panel); border-radius: 50%; }

.request-btn { width: 100%; justify-content: center; }
.request-btn :deep(svg) { width: 13px; height: 13px; }

.request-sent {
  display: flex; align-items: flex-start; gap: 10px; text-align: left;
  padding: 12px 14px; border-radius: var(--r-md, 8px);
  background: color-mix(in oklab, var(--status-done) 10%, var(--bg-panel));
  border: 1px solid color-mix(in oklab, var(--status-done) 28%, var(--border));
}
.sent-check { color: var(--status-done); flex-shrink: 0; margin-top: 1px; }
.sent-check :deep(svg) { width: 16px; height: 16px; }
.sent-title { font-size: 13px; font-weight: 600; margin-bottom: 2px; color: var(--fg); }
.sent-sub   { font-size: 12px; line-height: 1.5; color: var(--fg-muted); }
</style>
