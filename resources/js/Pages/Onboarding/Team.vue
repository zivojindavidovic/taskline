<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

const props = defineProps({
  workspaceName: String,
  host: String,
  created: { type: Array, default: null },
})

function genPassword(len = 14) {
  const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789'
  const arr = new Uint32Array(len)
  crypto.getRandomValues(arr)
  let out = ''
  for (let i = 0; i < len; i++) out += chars[arr[i] % chars.length]
  return out
}

let nid = 1
const form = useForm({
  members: [{ id: nid++, name: '', email: '', role: 'member', password: genPassword() }],
})

const emailDomain = computed(() => (props.host || 'company.com').replace(/^www\./, '').replace(/:\d+$/, ''))
const ready = computed(() => form.members.filter((m) => m.email.trim().includes('@')))

const copiedId = ref(null)
function copy(text, id) {
  try { navigator.clipboard?.writeText(text) } catch (e) { /* noop */ }
  copiedId.value = id
  setTimeout(() => { if (copiedId.value === id) copiedId.value = null }, 1400)
}

function addRow() {
  if (form.members.length < 8) form.members.push({ id: nid++, name: '', email: '', role: 'member', password: genPassword() })
}
function removeRow(id) { form.members = form.members.filter((m) => m.id !== id) }
function regen(id) {
  const m = form.members.find((x) => x.id === id)
  if (m) m.password = genPassword()
}

function submit() {
  if (!ready.value.length) return
  form.transform((data) => ({
    members: data.members
      .filter((m) => m.email.trim().includes('@'))
      .map(({ name, email, role, password }) => ({ name, email, role, password })),
  })).post(route('onboarding.team.store'))
}

function skip() { router.visit(route('onboarding.done')) }
function finish() { router.visit(route('onboarding.done')) }

function credentialsText(list) {
  return [
    'Taskline — team account credentials',
    `Instance: ${props.host}`,
    `Generated: ${new Date().toLocaleString()}`,
    '',
    ...list.map((m) => `${m.name || '(no name)'}  <${m.email}>  ·  ${m.role}\n    password: ${m.password}`),
    '',
    'Share each login securely. Members should change their password after first sign-in.',
  ].join('\n')
}
function downloadTxt(list) {
  const blob = new Blob([credentialsText(list)], { type: 'text/plain' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url; a.download = 'taskline-team-credentials.txt'
  document.body.appendChild(a); a.click(); a.remove()
  setTimeout(() => URL.revokeObjectURL(url), 1000)
}
</script>

<template>
  <GuestLayout>
    <Head title="Add your team" />

    <!-- Reveal stage: accounts created, show credentials once -->
    <template v-if="created">
      <div class="icon-circle">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3"/></svg>
      </div>
      <div class="center">
        <h1 class="auth-title">{{ created.length }} {{ created.length === 1 ? 'account' : 'accounts' }} created</h1>
        <p class="auth-subtitle small">Share these credentials securely — they're shown here only once.</p>
      </div>

      <div v-if="!created.length" class="empty">No new accounts were created (those emails may already exist).</div>

      <div class="cred-list">
        <div v-for="(m, i) in created" :key="i" class="cred-row">
          <div class="cred-meta">
            <div class="cred-email">{{ m.email }}</div>
            <div class="cred-pw">{{ m.password }}</div>
          </div>
          <button class="mini-btn" :class="{ ok: copiedId === 'c' + i }" @click="copy(`${m.email}  ${m.password}`, 'c' + i)" title="Copy login">
            <svg v-if="copiedId === 'c' + i" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            <svg v-else width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
          </button>
        </div>
      </div>

      <div v-if="created.length" class="dual">
        <button class="btn-sso" @click="copy(credentialsText(created), 'all')">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
          {{ copiedId === 'all' ? 'Copied!' : 'Copy all' }}
        </button>
        <button class="btn-sso" @click="downloadTxt(created)">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
          Download .txt
        </button>
      </div>

      <button class="btn-submit" @click="finish">Enter your workspace</button>
    </template>

    <!-- Form stage -->
    <template v-else>
      <div class="step-bar">
        <span class="seg on" /><span class="seg on" /><span class="seg on" />
        <span class="step-label">Step 3 of 3</span>
      </div>

      <h1 class="auth-title">Add your team</h1>
      <p class="auth-subtitle">
        Create logins for your teammates in <strong>{{ workspaceName }}</strong>. On a self-hosted instance there's no
        email verification — a password is generated for each member; share it so they can sign in.
      </p>

      <div class="members">
        <div v-for="(m, i) in form.members" :key="m.id" class="member-card">
          <div class="row">
            <input class="field-input grow" type="text" placeholder="Full name" v-model="m.name" />
            <select class="field-input role" v-model="m.role">
              <option value="admin">Admin</option>
              <option value="member">Member</option>
            </select>
          </div>
          <div class="row">
            <input class="field-input grow" type="email" :placeholder="`teammate${i + 1}@${emailDomain}`" v-model="m.email" />
            <button v-if="form.members.length > 1" class="mini-btn" title="Remove" @click="removeRow(m.id)">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
            </button>
          </div>
          <div class="pw-readout">
            <span class="pw-label">pw</span>
            <code>{{ m.password }}</code>
            <button class="mini-btn" title="Regenerate" @click="regen(m.id)">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
            </button>
            <button class="mini-btn" :class="{ ok: copiedId === m.id }" title="Copy password" @click="copy(m.password, m.id)">
              <svg v-if="copiedId === m.id" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
              <svg v-else width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
            </button>
          </div>
        </div>
      </div>

      <button v-if="form.members.length < 8" class="btn-back add" @click="addRow">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Add another member
      </button>

      <button class="btn-submit" :disabled="form.processing || !ready.length" @click="submit">
        <svg v-if="form.processing" class="spinner" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4"/></svg>
        {{ form.processing ? 'Creating accounts…' : (ready.length ? `Create ${ready.length} ${ready.length === 1 ? 'account' : 'accounts'}` : 'Add at least one email') }}
      </button>

      <div class="skip-row">
        <button class="btn-link" @click="skip">Skip for now — I'll add the team later</button>
      </div>
    </template>
  </GuestLayout>
</template>

<style scoped>
.auth-title { font-size: 20px; font-weight: 600; color: var(--fg); margin: 0 0 6px; line-height: 1.3; }
.auth-subtitle { font-size: 14px; color: var(--fg-muted); margin: 0 0 16px; line-height: 1.5; }
.auth-subtitle.small { margin-bottom: 0; }
.auth-subtitle strong { color: var(--fg); font-weight: 600; }
.center { text-align: center; margin-bottom: 24px; }

.icon-circle { width: 52px; height: 52px; border-radius: 50%; margin: 0 auto 20px; display: grid; place-items: center; background: var(--accent-soft); color: var(--accent); }

.step-bar { display: flex; align-items: center; gap: 6px; margin-bottom: 24px; }
.seg { height: 3px; flex: 1; border-radius: 2px; background: var(--border); }
.seg.on { background: var(--accent); }
.step-label { font-size: 11px; color: var(--fg-subtle); margin-left: 4px; white-space: nowrap; }

.members { display: flex; flex-direction: column; gap: 10px; }
.member-card { display: flex; flex-direction: column; gap: 8px; padding: 12px; border: 1px solid var(--border); border-radius: var(--r-lg); background: var(--bg-panel); }
.row { display: flex; gap: 8px; }

.field-input {
  width: 100%; height: 38px; padding: 0 12px; border-radius: var(--r-md); border: 1px solid var(--border);
  background: var(--bg-panel); color: var(--fg); font-size: 14px; font-family: var(--font-ui);
  outline: none; transition: border-color 120ms, box-shadow 120ms; box-sizing: border-box;
}
.field-input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-soft); }
.field-input.grow { flex: 1; }
.field-input.role { width: 104px; cursor: pointer; }

.pw-readout { display: flex; align-items: center; gap: 8px; padding: 6px 8px; border-radius: var(--r-md); background: var(--bg-sunken); border: 1px solid var(--border); }
.pw-label { font-size: 11px; color: var(--fg-subtle); flex-shrink: 0; }
.pw-readout code { flex: 1; font-family: var(--font-mono); font-size: 12.5px; color: var(--fg-muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.mini-btn {
  width: 28px; height: 28px; flex-shrink: 0; display: grid; place-items: center;
  border: 1px solid var(--border); border-radius: var(--r-sm); background: var(--bg-panel);
  color: var(--fg-subtle); cursor: pointer; transition: color 80ms, border-color 80ms;
}
.mini-btn:hover { color: var(--fg); }
.mini-btn.ok { color: var(--status-done); border-color: var(--status-done); }

.cred-list { display: flex; flex-direction: column; gap: 8px; }
.cred-row { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border: 1px solid var(--border); border-radius: var(--r-md); background: var(--bg-panel); }
.cred-meta { flex: 1; min-width: 0; }
.cred-email { font-weight: 600; font-size: 13px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.cred-pw { font-size: 12.5px; font-family: var(--font-mono); color: var(--fg-muted); margin-top: 2px; }
.empty { font-size: 13px; color: var(--fg-subtle); text-align: center; padding: 12px 0; }

.dual { display: flex; gap: 8px; margin-top: 16px; }
.btn-sso {
  flex: 1; height: 38px; border-radius: var(--r-md); border: 1px solid var(--border); background: var(--bg-panel);
  color: var(--fg); font-family: var(--font-ui); font-size: 13px; font-weight: 500; cursor: pointer;
  display: flex; align-items: center; justify-content: center; gap: 8px; transition: background 80ms;
}
.btn-sso:hover { background: var(--bg-hover); }

.btn-submit {
  width: 100%; height: 40px; border-radius: var(--r-md); background: var(--accent); color: var(--accent-fg);
  border: none; font-family: var(--font-ui); font-size: 14px; font-weight: 500; cursor: pointer; margin-top: 16px;
  display: flex; align-items: center; justify-content: center; gap: 8px; transition: background 80ms;
}
.btn-submit:hover:not(:disabled) { background: var(--accent-hover); }
.btn-submit:disabled { opacity: 0.55; cursor: not-allowed; }

.btn-back { display: inline-flex; align-items: center; gap: 6px; background: none; border: none; cursor: pointer; color: var(--accent); font-size: 13px; font-weight: 500; padding: 0; margin-top: 12px; }
.btn-back:hover { text-decoration: underline; }

.skip-row { text-align: center; margin-top: 12px; }
.btn-link { background: none; border: none; cursor: pointer; color: var(--fg-subtle); font-size: 12px; padding: 0; }
.btn-link:hover { text-decoration: underline; }

@keyframes spin { to { transform: rotate(360deg); } }
.spinner { animation: spin 0.6s linear infinite; }
</style>
