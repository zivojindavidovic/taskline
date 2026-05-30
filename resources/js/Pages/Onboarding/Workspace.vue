<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue'
import { Head, useForm, usePage } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

const props = defineProps({
  colors: { type: Array, default: () => ['#4f46e5', '#0891b2', '#16a34a', '#7c3aed', '#d97706', '#dc2626', '#0d9488'] },
})

const page = usePage()
const selfHosted = computed(() => (page.props.deployment?.mode ?? 'cloud') === 'self-hosted')

const step = ref(1) // cloud has a 2nd (invite) step
const slugTouched = ref(false)
const slug = ref('')

const form = useForm({
  name: '',
  color: props.colors[0],
  invites: [
    { email: '', role: 'member' },
    { email: '', role: 'member' },
  ],
})

const initials = computed(() =>
  form.name.split(' ').filter(Boolean).map((w) => w[0]).join('').slice(0, 2).toUpperCase() || 'W'
)

function slugify(v) {
  return v.toLowerCase().replace(/[^a-z0-9]/g, '-').replace(/-+/g, '-').replace(/^-|-$/g, '')
}
function onName(e) {
  form.name = e.target.value
  if (!slugTouched.value) slug.value = slugify(form.name)
}
function onSlug(e) {
  slugTouched.value = true
  slug.value = e.target.value.toLowerCase().replace(/[^a-z0-9-]/g, '')
}

function addInvite() {
  if (form.invites.length < 5) form.invites.push({ email: '', role: 'member' })
}

function next() {
  if (!form.name.trim()) return
  if (selfHosted.value) {
    submit()
  } else {
    step.value = 2
  }
}

function submit() {
  form.transform((data) => ({
    ...data,
    invites: selfHosted.value ? [] : data.invites.filter((r) => r.email.trim()),
  })).post(route('onboarding.workspace.store'))
}
</script>

<template>
  <GuestLayout>
    <Head title="Create your workspace" />

    <!-- Progress rail: self-hosted is step 2 of 3, cloud is x of 2 -->
    <div class="step-bar">
      <template v-if="selfHosted">
        <span class="seg on" /><span class="seg on" /><span class="seg" />
        <span class="step-label">Step 2 of 3</span>
      </template>
      <template v-else>
        <span class="seg on" /><span class="seg" :class="{ on: step >= 2 }" />
        <span class="step-label">Step {{ step }} of 2</span>
      </template>
    </div>

    <!-- Step 1: name + color -->
    <template v-if="step === 1">
      <h1 class="auth-title">Name your workspace</h1>
      <p class="auth-subtitle">This is the home for all your team's projects and tasks.</p>

      <div class="ws-preview">
        <div class="ws-badge" :style="{ background: form.color }">{{ initials }}</div>
        <div class="ws-meta">
          <div class="ws-name">{{ form.name || 'Your workspace' }}</div>
          <div class="ws-slug">taskline.app/{{ slug || 'your-workspace' }}</div>
        </div>
      </div>

      <div class="form-col">
        <div class="field">
          <label class="field-label">Workspace name</label>
          <input class="field-input" type="text" placeholder="e.g. Northstar Labs"
                 :value="form.name" @input="onName" autofocus />
          <p v-if="form.errors.name" class="field-error">{{ form.errors.name }}</p>
        </div>

        <div class="field">
          <label class="field-label">URL</label>
          <div class="url-group">
            <span class="url-prefix">taskline.app/</span>
            <input class="url-input" :value="slug" @input="onSlug" placeholder="your-workspace" />
          </div>
        </div>

        <div class="field">
          <label class="field-label">Color</label>
          <div class="swatches">
            <button v-for="c in colors" :key="c" type="button" class="swatch"
                    :class="{ on: form.color === c }"
                    :style="{ background: c, '--sw': c }" @click="form.color = c" />
          </div>
        </div>
      </div>

      <button class="btn-submit" :disabled="!form.name.trim() || form.processing" @click="next">
        <svg v-if="form.processing && selfHosted" class="spinner" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4"/></svg>
        Continue
      </button>
    </template>

    <!-- Step 2 (Cloud only): invite team -->
    <template v-else>
      <button class="btn-back" @click="step = 1">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        Back
      </button>

      <h1 class="auth-title">Invite your team</h1>
      <p class="auth-subtitle">Add teammates to <strong>{{ form.name }}</strong>. They'll get an email invite.</p>

      <div class="form-col tight">
        <div v-for="(row, i) in form.invites" :key="i" class="invite-row">
          <input class="field-input grow" type="email" :placeholder="`teammate${i + 1}@company.com`" v-model="row.email" />
          <select class="field-input role" v-model="row.role">
            <option value="admin">Admin</option>
            <option value="member">Member</option>
          </select>
        </div>
      </div>

      <button v-if="form.invites.length < 5" class="btn-back add" @click="addInvite">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Add another
      </button>

      <button class="btn-submit" :disabled="form.processing" @click="submit">
        <svg v-if="form.processing" class="spinner" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4"/></svg>
        {{ form.processing ? 'Creating workspace…' : 'Create workspace' }}
      </button>

      <div class="skip-row">
        <button class="btn-link" @click="submit">Skip for now — I'll invite people later</button>
      </div>
    </template>
  </GuestLayout>
</template>

<style scoped>
.auth-title { font-size: 20px; font-weight: 600; color: var(--fg); margin: 0 0 6px; line-height: 1.3; }
.auth-subtitle { font-size: 14px; color: var(--fg-muted); margin: 0 0 20px; line-height: 1.5; }
.auth-subtitle strong { color: var(--fg); font-weight: 600; }

.step-bar { display: flex; align-items: center; gap: 6px; margin-bottom: 24px; }
.seg { height: 3px; flex: 1; border-radius: 2px; background: var(--border); transition: background 200ms; }
.seg.on { background: var(--accent); }
.step-label { font-size: 11px; color: var(--fg-subtle); margin-left: 4px; white-space: nowrap; }

.ws-preview {
  display: flex; align-items: center; gap: 12px; padding: 12px 14px;
  border: 1px solid var(--border); border-radius: var(--r-lg); margin-bottom: 20px; background: var(--bg-sunken);
}
.ws-badge {
  width: 36px; height: 36px; border-radius: 8px; flex-shrink: 0; color: #fff;
  display: grid; place-items: center; font-size: 14px; font-weight: 700; transition: background 150ms;
}
.ws-meta { flex: 1; min-width: 0; }
.ws-name { font-weight: 600; font-size: 13px; }
.ws-slug { font-size: 12px; color: var(--fg-subtle); font-family: var(--font-mono); }

.form-col { display: flex; flex-direction: column; gap: 14px; }
.form-col.tight { gap: 8px; margin-bottom: 12px; }
.field { display: flex; flex-direction: column; gap: 5px; }
.field-label { font-size: 13px; font-weight: 500; color: var(--fg-muted); }
.field-input {
  width: 100%; height: 40px; padding: 0 12px; border-radius: var(--r-md); border: 1px solid var(--border);
  background: var(--bg-panel); color: var(--fg); font-size: 14px; font-family: var(--font-ui);
  outline: none; transition: border-color 120ms, box-shadow 120ms; box-sizing: border-box;
}
.field-input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-soft); }
.field-error { font-size: 12px; color: var(--status-blocked); margin: 0; }

.url-group { display: flex; align-items: center; border: 1px solid var(--border); border-radius: var(--r-md); overflow: hidden; background: var(--bg-panel); }
.url-prefix { padding: 0 10px; height: 40px; display: flex; align-items: center; flex-shrink: 0; color: var(--fg-subtle); font-size: 13px; border-right: 1px solid var(--border); background: var(--bg-sunken); }
.url-input { border: none; outline: none; flex: 1; height: 40px; padding: 0 10px; font-size: 13px; font-family: var(--font-mono); background: transparent; color: var(--fg); }

.swatches { display: flex; gap: 8px; flex-wrap: wrap; }
.swatch { width: 28px; height: 28px; border-radius: 7px; border: none; cursor: pointer; outline-offset: 2px; transition: box-shadow 100ms; }
.swatch.on { outline: 2px solid var(--sw); box-shadow: 0 0 0 4px color-mix(in oklab, var(--sw) 18%, transparent); }

.invite-row { display: flex; gap: 8px; }
.field-input.grow { flex: 1; }
.field-input.role { width: 110px; cursor: pointer; }

.btn-submit {
  width: 100%; height: 40px; border-radius: var(--r-md); background: var(--accent); color: var(--accent-fg);
  border: none; font-family: var(--font-ui); font-size: 14px; font-weight: 500; cursor: pointer; margin-top: 20px;
  display: flex; align-items: center; justify-content: center; gap: 8px; transition: background 80ms;
}
.btn-submit:hover:not(:disabled) { background: var(--accent-hover); }
.btn-submit:disabled { opacity: 0.55; cursor: not-allowed; }

.btn-back {
  display: inline-flex; align-items: center; gap: 6px; background: none; border: none; cursor: pointer;
  color: var(--fg-muted); font-size: 13px; font-weight: 500; padding: 0; margin-bottom: 20px;
}
.btn-back:hover { color: var(--fg); }
.btn-back.add { margin-bottom: 0; color: var(--accent); }

.skip-row { text-align: center; margin-top: 12px; }
.btn-link { background: none; border: none; cursor: pointer; color: var(--fg-subtle); font-size: 12px; padding: 0; }
.btn-link:hover { text-decoration: underline; }

@keyframes spin { to { transform: rotate(360deg); } }
.spinner { animation: spin 0.6s linear infinite; }
</style>
