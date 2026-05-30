<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import { ref, computed, watch } from 'vue'

const page = usePage()

// Cloud vs Self-hosted is resolved server-side and shared via Inertia.
const deployment = computed(() => page.props.deployment ?? { mode: 'cloud', host: '' })
const selfHosted = computed(() => deployment.value.mode === 'self-hosted')
const host = computed(() => deployment.value.host)

const form = useForm({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
})

// Keep confirmation in sync so backend validation passes
watch(() => form.password, (v) => { form.password_confirmation = v })

const showPassword = ref(false)
const agreedToTerms = ref(false)

const passwordStrength = computed(() => {
  const p = form.password
  if (!p) return 0
  let score = 0
  if (p.length >= 8) score++
  if (/[A-Z]/.test(p)) score++
  if (/[0-9]/.test(p)) score++
  if (/[^A-Za-z0-9]/.test(p)) score++
  return score
})

const strengthLabel = computed(() => {
  const labels = ['', 'Weak', 'Fair', 'Good', 'Strong']
  return labels[passwordStrength.value] ?? ''
})

const strengthColor = computed(() => {
  const colors = ['', '#dc2626', '#d97706', '#65a30d', '#16a34a']
  return colors[passwordStrength.value] ?? ''
})

const submit = () => {
  form.post(route('register'), {
    onFinish: () => form.reset('password', 'password_confirmation'),
  })
}
</script>

<template>
  <GuestLayout>
    <Head :title="selfHosted ? 'Create admin account' : 'Create account'" />

    <!-- Brand -->
    <div class="auth-brand">
      <div class="brand-logo">T</div>
      <span class="brand-name">Taskline</span>
    </div>

    <!-- Self-hosted setup is a guided first-run, so show the progress rail -->
    <div v-if="selfHosted" class="step-bar">
      <span class="step-seg is-active" />
      <span class="step-seg" />
      <span class="step-seg" />
      <span class="step-label">Step 1 of 3</span>
    </div>

    <h1 class="auth-title">
      {{ selfHosted ? 'Create your admin account' : 'Create your account' }}
    </h1>
    <p class="auth-subtitle">
      <template v-if="selfHosted">
        You'll be the <strong>admin</strong> of this self-hosted Taskline on
        <code class="host-code">{{ host }}</code>. Next, you'll set up your workspace.
      </template>
      <template v-else>
        Start organizing your team's work — free, no card needed.
      </template>
    </p>

    <!-- Google SSO — Cloud only (visual only) -->
    <template v-if="!selfHosted">
      <button type="button" class="btn-sso">
        <svg width="18" height="18" viewBox="0 0 18 18">
          <path d="M17.64 9.2c0-.637-.057-1.251-.164-1.84H9v3.481h4.844a4.14 4.14 0 0 1-1.796 2.716v2.258h2.908c1.702-1.567 2.684-3.874 2.684-6.615z" fill="#4285F4"/>
          <path d="M9 18c2.43 0 4.467-.806 5.956-2.18l-2.908-2.259c-.806.54-1.837.86-3.048.86-2.344 0-4.328-1.584-5.036-3.711H.957v2.332A8.997 8.997 0 0 0 9 18z" fill="#34A853"/>
          <path d="M3.964 10.71A5.41 5.41 0 0 1 3.682 9c0-.593.102-1.17.282-1.71V4.958H.957A8.996 8.996 0 0 0 0 9c0 1.452.348 2.827.957 4.042l3.007-2.332z" fill="#FBBC05"/>
          <path d="M9 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.463.891 11.426 0 9 0A8.997 8.997 0 0 0 .957 4.958L3.964 7.29C4.672 5.163 6.656 3.58 9 3.58z" fill="#EA4335"/>
        </svg>
        Sign up with Google
      </button>

      <div class="divider">or with email</div>
    </template>

    <form @submit.prevent="submit" class="auth-form">
      <!-- Full name -->
      <div class="field">
        <label class="field-label" for="name">Full name</label>
        <input
          id="name"
          v-model="form.name"
          type="text"
          class="field-input"
          :class="{ 'field-input--error': form.errors.name }"
          placeholder="Jane Smith"
          autocomplete="name"
          autofocus
          required
        />
        <p v-if="form.errors.name" class="field-error">{{ form.errors.name }}</p>
      </div>

      <!-- Email -->
      <div class="field">
        <label class="field-label" for="email">Work email</label>
        <input
          id="email"
          v-model="form.email"
          type="email"
          class="field-input"
          :class="{ 'field-input--error': form.errors.email }"
          placeholder="you@company.com"
          autocomplete="username"
          required
        />
        <p v-if="form.errors.email" class="field-error">{{ form.errors.email }}</p>
      </div>

      <!-- Password with strength meter -->
      <div class="field">
        <label class="field-label" for="password">Password</label>
        <div class="input-wrap">
          <input
            id="password"
            v-model="form.password"
            :type="showPassword ? 'text' : 'password'"
            class="field-input"
            :class="{ 'field-input--error': form.errors.password }"
            placeholder="Min. 8 characters"
            autocomplete="new-password"
            required
          />
          <button
            type="button"
            class="eye-btn"
            @click="showPassword = !showPassword"
          >
            <svg v-if="showPassword" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/>
            </svg>
            <svg v-else width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
            </svg>
          </button>
        </div>
        <!-- Strength meter -->
        <div v-if="form.password" class="strength-wrap">
          <div class="strength-bars">
            <div
              v-for="i in 4" :key="i"
              class="strength-bar"
              :style="{ background: i <= passwordStrength ? strengthColor : 'var(--border)' }"
            />
          </div>
          <span class="strength-label" :style="{ color: strengthColor }">{{ strengthLabel }}</span>
        </div>
        <p v-if="form.errors.password" class="field-error">{{ form.errors.password }}</p>
      </div>

      <!-- Terms -->
      <label class="check-label">
        <input type="checkbox" class="check-input" v-model="agreedToTerms" />
        <span class="check-box" />
        <span class="check-text">
          I agree to the <a href="#" class="field-link">Terms of Service</a> and <a href="#" class="field-link">Privacy Policy</a>
        </span>
      </label>

      <!-- Submit -->
      <button
        type="submit"
        class="btn-submit"
        :disabled="form.processing || !agreedToTerms"
      >
        <svg v-if="form.processing" class="spinner" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>
        {{ form.processing
            ? (selfHosted ? 'Creating admin account…' : 'Creating account…')
            : (selfHosted ? 'Create admin account' : 'Create account') }}
      </button>
    </form>

    <p class="auth-footer">
      Already have an account?
      <Link :href="route('login')" class="auth-footer-link">Sign in</Link>
    </p>
  </GuestLayout>
</template>

<style scoped>
.auth-brand {
  display: flex; align-items: center; gap: 10px; margin-bottom: 28px;
}
.brand-logo {
  width: 32px; height: 32px; border-radius: 7px;
  background: var(--accent); color: var(--accent-fg);
  font-size: 15px; font-weight: 700; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
}
.brand-name { font-size: 16px; font-weight: 600; color: var(--fg); }
.auth-title { font-size: 20px; font-weight: 600; color: var(--fg); margin: 0 0 6px; line-height: 1.3; }
.auth-subtitle { font-size: 14px; color: var(--fg-muted); margin: 0 0 28px; line-height: 1.5; }
.auth-subtitle strong { color: var(--fg); font-weight: 600; }
.host-code {
  font-family: var(--font-mono); font-size: 0.92em;
  color: var(--fg); background: var(--bg-sunken);
  border: 1px solid var(--border); border-radius: var(--r-sm);
  padding: 1px 5px;
}

/* Self-hosted first-run progress rail */
.step-bar { display: flex; align-items: center; gap: 6px; margin-bottom: 24px; }
.step-seg {
  height: 3px; flex: 1; border-radius: 2px;
  background: var(--border); transition: background 200ms;
}
.step-seg.is-active { background: var(--accent); }
.step-label {
  font-size: 11px; color: var(--fg-subtle);
  margin-left: 4px; white-space: nowrap;
}

.btn-sso {
  width: 100%; height: 40px; border-radius: var(--r-md);
  border: 1px solid var(--border); background: var(--bg-panel);
  color: var(--fg); font-family: var(--font-ui); font-size: 14px; font-weight: 500;
  display: flex; align-items: center; justify-content: center; gap: 10px;
  cursor: pointer; transition: background 80ms;
}
.btn-sso:hover { background: var(--bg-hover); }

.divider {
  display: flex; align-items: center; gap: 12px;
  margin: 20px 0; font-size: 12px; color: var(--fg-subtle);
}
.divider::before, .divider::after { content: ''; flex: 1; height: 1px; background: var(--border); }

.auth-form { display: flex; flex-direction: column; gap: 14px; }
.field { display: flex; flex-direction: column; gap: 5px; }
.field-label { font-size: 13px; font-weight: 500; color: var(--fg-muted); }
.field-link { font-size: 12px; color: var(--accent); text-decoration: none; }
.field-link:hover { text-decoration: underline; }

.input-wrap { position: relative; }
.field-input {
  width: 100%; height: 40px; padding: 0 12px;
  border-radius: var(--r-md); border: 1px solid var(--border);
  background: var(--bg-panel); color: var(--fg); font-size: 14px;
  font-family: var(--font-ui);
  outline: none; transition: border-color 120ms, box-shadow 120ms; box-sizing: border-box;
}
.input-wrap .field-input { padding-right: 44px; }
.field-input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-soft); }
.field-input--error { border-color: var(--status-blocked); box-shadow: 0 0 0 3px color-mix(in oklab, var(--status-blocked) 12%, transparent); }
.field-input::placeholder { color: var(--fg-subtle); }
.field-error { font-size: 12px; color: var(--status-blocked); margin: 0; }

.eye-btn {
  position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
  display: grid; place-items: center; padding: 2px;
  background: none; border: none; cursor: pointer; color: var(--fg-subtle);
  transition: color 80ms;
}
.eye-btn:hover { color: var(--fg-muted); }

.strength-wrap { display: flex; align-items: center; gap: 8px; margin-top: 2px; }
.strength-bars { display: flex; gap: 4px; flex: 1; }
.strength-bar { flex: 1; height: 3px; border-radius: 2px; transition: background 200ms; }
.strength-label { font-size: 11px; font-weight: 500; }

.check-label { display: flex; align-items: center; gap: 10px; cursor: pointer; user-select: none; font-size: 13px; color: var(--fg-muted); }
.check-input { display: none; }
.check-box {
  width: 16px; height: 16px; border-radius: 4px; flex-shrink: 0;
  border: 1.5px solid var(--border-strong);
  display: grid; place-items: center;
  transition: background 100ms, border-color 100ms;
}
.check-input:checked + .check-box {
  background: var(--accent); border-color: var(--accent);
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='3.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='20 6 9 17 4 12'/%3E%3C/svg%3E");
  background-repeat: no-repeat; background-position: center;
}
.check-text { font-size: 13px; color: var(--fg-muted); line-height: 1.5; }

.btn-submit {
  width: 100%; height: 40px; border-radius: var(--r-md);
  background: var(--accent); color: var(--accent-fg);
  border: none; font-family: var(--font-ui); font-size: 14px; font-weight: 500;
  cursor: pointer; transition: background 80ms, transform 60ms;
  display: flex; align-items: center; justify-content: center; gap: 8px;
  margin-top: 20px;
}
.btn-submit:hover:not(:disabled) { background: var(--accent-hover); }
.btn-submit:active:not(:disabled) { transform: scale(0.99); }
.btn-submit:disabled { opacity: 0.55; cursor: not-allowed; }

@keyframes spin { to { transform: rotate(360deg); } }
.spinner { animation: spin 0.6s linear infinite; }

.auth-footer {
  text-align: center; font-size: 13px; color: var(--fg-muted);
  margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--border);
}
.auth-footer-link { color: var(--accent); text-decoration: none; font-weight: 500; }
.auth-footer-link:hover { text-decoration: underline; }
</style>
