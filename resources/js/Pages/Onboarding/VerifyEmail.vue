<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import { ref, computed, nextTick, onMounted } from 'vue'

const props = defineProps({
  email: String,
  dev_otp: { type: String, default: null },
})

const digits = ref(['', '', '', '', '', ''])
const inputs = ref([])
const resent = ref(false)

const form = useForm({ code: '' })
const code = computed(() => digits.value.join(''))

onMounted(() => nextTick(() => inputs.value[0]?.focus()))

function submitIfComplete() {
  if (code.value.length === 6 && !form.processing) {
    form.code = code.value
    form.post(route('onboarding.verify.confirm'), {
      preserveScroll: true,
      onError: () => {
        digits.value = ['', '', '', '', '', '']
        nextTick(() => inputs.value[0]?.focus())
      },
    })
  }
}

function onInput(i, e) {
  const clean = e.target.value.replace(/\D/g, '').slice(-1)
  digits.value[i] = clean
  form.clearErrors('code')
  if (clean && i < 5) inputs.value[i + 1]?.focus()
  submitIfComplete()
}

function onKeydown(i, e) {
  if (e.key === 'Backspace') {
    if (digits.value[i]) {
      digits.value[i] = ''
    } else if (i > 0) {
      inputs.value[i - 1]?.focus()
    }
  }
}

function onPaste(e) {
  const text = (e.clipboardData.getData('text') || '').replace(/\D/g, '').slice(0, 6)
  if (text) {
    digits.value = Array.from({ length: 6 }, (_, i) => text[i] || '')
    e.preventDefault()
    nextTick(() => {
      inputs.value[Math.min(text.length, 5)]?.focus()
      submitIfComplete()
    })
  }
}

function resend() {
  router.post(route('onboarding.verify.resend'), {}, {
    preserveScroll: true,
    onSuccess: () => { resent.value = true },
  })
}
</script>

<template>
  <GuestLayout>
    <Head title="Verify your email" />

    <div class="icon-circle">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
    </div>

    <div class="center">
      <h1 class="auth-title">Verify your email</h1>
      <p class="auth-subtitle small">
        We sent a 6-digit code to <strong>{{ email }}</strong>.
      </p>
      <span v-if="dev_otp" class="dev-hint">Dev code: <strong>{{ dev_otp }}</strong></span>
    </div>

    <div v-if="form.errors.code" class="inline-alert">{{ form.errors.code }}</div>

    <div class="otp-wrap" @paste="onPaste">
      <input
        v-for="(d, i) in digits" :key="i"
        ref="inputs"
        class="otp-digit"
        type="text"
        inputmode="numeric"
        maxlength="1"
        :value="d"
        :disabled="form.processing"
        @input="onInput(i, $event)"
        @keydown="onKeydown(i, $event)"
      />
    </div>

    <div v-if="form.processing" class="verifying">
      <svg class="spinner" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>
      Verifying…
    </div>

    <div class="resend-row">
      Didn't get it?
      <button type="button" class="btn-link" @click="resend">{{ resent ? 'Sent!' : 'Resend code' }}</button>
    </div>
  </GuestLayout>
</template>

<style scoped>
.auth-title { font-size: 20px; font-weight: 600; color: var(--fg); margin: 0 0 6px; line-height: 1.3; }
.auth-subtitle { font-size: 14px; color: var(--fg-muted); margin: 0 0 28px; line-height: 1.5; }
.auth-subtitle strong { color: var(--fg); font-weight: 600; }
.auth-subtitle.small { margin-bottom: 4px; }
.center { text-align: center; margin-bottom: 24px; }
.icon-circle {
  width: 52px; height: 52px; border-radius: 50%; margin: 0 auto 20px;
  display: grid; place-items: center;
  background: var(--accent-soft); color: var(--accent);
}
.dev-hint { font-size: 12px; color: var(--fg-subtle); }
.dev-hint strong { font-family: var(--font-mono); color: var(--fg-muted); }

.inline-alert {
  font-size: 13px; color: var(--status-blocked);
  background: color-mix(in oklab, var(--status-blocked) 8%, transparent);
  border: 1px solid color-mix(in oklab, var(--status-blocked) 25%, transparent);
  border-radius: var(--r-md); padding: 8px 12px; margin-bottom: 16px; text-align: center;
}

.otp-wrap { display: flex; gap: 8px; justify-content: center; }
.otp-digit {
  width: 46px; height: 56px; text-align: center;
  font-size: 22px; font-weight: 600; font-family: var(--font-mono);
  border: 1px solid var(--border); border-radius: var(--r-md);
  background: var(--bg-panel); color: var(--fg); outline: none;
  transition: border-color 120ms, box-shadow 120ms;
}
.otp-digit:focus { border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-soft); }

.verifying {
  display: flex; align-items: center; justify-content: center; gap: 8px;
  margin-top: 20px; color: var(--fg-muted); font-size: 13px;
}
@keyframes spin { to { transform: rotate(360deg); } }
.spinner { animation: spin 0.6s linear infinite; color: var(--accent); }

.resend-row { text-align: center; margin-top: 24px; font-size: 13px; color: var(--fg-muted); }
.btn-link { background: none; border: none; cursor: pointer; color: var(--accent); font-weight: 500; font-size: 13px; padding: 0; }
.btn-link:hover { text-decoration: underline; }
</style>
