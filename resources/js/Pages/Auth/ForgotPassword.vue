<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'

defineProps({
  status: { type: String },
})

const form = useForm({
  email: '',
})

const submit = () => {
  form.post(route('password.email'))
}
</script>

<template>
  <GuestLayout>
    <Head title="Forgot Password" />

    <!-- Sent state -->
    <template v-if="status">
      <div class="icon-circle">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m2 7 10 7 10-7"/></svg>
      </div>
      <div style="text-align:center">
        <h1 class="auth-title" style="margin-bottom:8px">Check your inbox</h1>
        <p class="auth-subtitle" style="margin-bottom:0">
          We sent a reset link to your email address.<br />
          The link expires in 60 minutes.
        </p>
      </div>
      <Link :href="route('login')" class="btn-submit" style="text-decoration:none;justify-content:center">
        Back to sign in
      </Link>
    </template>

    <!-- Default state -->
    <template v-else>
      <Link :href="route('login')" class="btn-back">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
        Back to sign in
      </Link>

      <h1 class="auth-title">Forgot your password?</h1>
      <p class="auth-subtitle">Enter your email and we'll send a reset link — expires in 60 minutes.</p>

      <form @submit.prevent="submit" class="auth-form">
        <div class="field">
          <label class="field-label" for="email">Email</label>
          <input
            id="email"
            v-model="form.email"
            type="email"
            class="field-input"
            :class="{ 'field-input--error': form.errors.email }"
            placeholder="you@company.com"
            autocomplete="email"
            autofocus
            required
          />
          <p v-if="form.errors.email" class="field-error">{{ form.errors.email }}</p>
        </div>

        <button
          type="submit"
          class="btn-submit"
          :disabled="form.processing || !form.email.includes('@')"
        >
          <svg v-if="form.processing" class="spinner" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>
          {{ form.processing ? 'Sending…' : 'Send reset link' }}
        </button>
      </form>
    </template>
  </GuestLayout>
</template>

<style scoped>
.icon-circle {
  width: 56px; height: 56px; border-radius: 50%;
  background: var(--accent-soft);
  display: grid; place-items: center;
  margin: 0 auto 20px;
  color: var(--accent);
}

.btn-back {
  display: inline-flex; align-items: center; gap: 6px;
  font-size: 13px; color: var(--fg-muted); text-decoration: none;
  margin-bottom: 24px; background: none; border: none; cursor: pointer;
  transition: color 80ms;
}
.btn-back:hover { color: var(--fg); }

.auth-title { font-size: 20px; font-weight: 600; color: var(--fg); margin: 0 0 6px; line-height: 1.3; }
.auth-subtitle { font-size: 14px; color: var(--fg-muted); margin: 0 0 28px; line-height: 1.5; }

.auth-form { display: flex; flex-direction: column; gap: 14px; }
.field { display: flex; flex-direction: column; gap: 5px; }
.field-label { font-size: 13px; font-weight: 500; color: var(--fg-muted); }
.field-input {
  width: 100%; height: 40px; padding: 0 12px;
  border-radius: var(--r-md); border: 1px solid var(--border);
  background: var(--bg-panel); color: var(--fg); font-size: 14px;
  font-family: var(--font-ui);
  outline: none; transition: border-color 120ms, box-shadow 120ms; box-sizing: border-box;
}
.field-input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-soft); }
.field-input--error { border-color: var(--status-blocked); }
.field-input::placeholder { color: var(--fg-subtle); }
.field-error { font-size: 12px; color: var(--status-blocked); margin: 0; }

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
</style>
