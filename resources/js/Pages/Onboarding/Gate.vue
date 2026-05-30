<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue'
import { Head, router } from '@inertiajs/vue3'
import { ref } from 'vue'

defineProps({
  invitations: { type: Array, default: () => [] },
})

const joining = ref(null)

function initials(name) {
  return (name || 'W').split(' ').filter(Boolean).map((w) => w[0]).join('').slice(0, 2).toUpperCase()
}

function accept(inv) {
  joining.value = inv.id
  router.post(route('onboarding.gate.accept'), { invitation_id: inv.id }, {
    onFinish: () => { joining.value = null },
  })
}

function decline(inv) {
  router.post(route('onboarding.gate.decline'), { invitation_id: inv.id }, {
    preserveScroll: true,
  })
}

function createWorkspace() {
  router.visit(route('onboarding.workspace'))
}
</script>

<template>
  <GuestLayout>
    <Head title="Welcome to Taskline" />

    <div class="auth-brand">
      <div class="brand-logo">T</div>
      <span class="brand-name">Taskline</span>
    </div>

    <h1 class="auth-title">Welcome to Taskline</h1>
    <p class="auth-subtitle">
      {{ invitations.length
        ? "You're not in any workspace yet. Create one or join a team below."
        : "You're not in any workspace yet. Create one to get started." }}
    </p>

    <button class="btn-submit create" @click="createWorkspace">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Create a new workspace
    </button>

    <template v-if="invitations.length">
      <div class="divider">pending invitations</div>

      <div class="invite-list">
        <div v-for="inv in invitations" :key="inv.id" class="invite-row">
          <div class="ws-badge" :style="{ background: (inv.workspace?.color || '#4f46e5') + '22', color: inv.workspace?.color || '#4f46e5' }">
            {{ initials(inv.workspace?.name) }}
          </div>
          <div class="invite-meta">
            <div class="invite-name">{{ inv.workspace?.name || 'Workspace' }}</div>
            <div class="invite-sub">Invited by {{ inv.inviter }} · {{ inv.role }}</div>
          </div>
          <div class="invite-actions">
            <button class="btn-link muted" @click="decline(inv)">Decline</button>
            <button class="btn-accept" :disabled="joining !== null" @click="accept(inv)">
              <svg v-if="joining === inv.id" class="spinner" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4"/></svg>
              <span v-else>Accept</span>
            </button>
          </div>
        </div>
      </div>
    </template>
  </GuestLayout>
</template>

<style scoped>
.auth-brand { display: flex; align-items: center; gap: 10px; margin-bottom: 28px; }
.brand-logo {
  width: 32px; height: 32px; border-radius: 7px; background: var(--accent); color: var(--accent-fg);
  font-size: 15px; font-weight: 700; display: flex; align-items: center; justify-content: center;
}
.brand-name { font-size: 16px; font-weight: 600; color: var(--fg); }
.auth-title { font-size: 20px; font-weight: 600; color: var(--fg); margin: 0 0 6px; line-height: 1.3; }
.auth-subtitle { font-size: 14px; color: var(--fg-muted); margin: 0 0 24px; line-height: 1.5; }

.btn-submit {
  width: 100%; height: 40px; border-radius: var(--r-md);
  background: var(--accent); color: var(--accent-fg); border: none;
  font-family: var(--font-ui); font-size: 14px; font-weight: 500; cursor: pointer;
  display: flex; align-items: center; justify-content: center; gap: 8px;
  transition: background 80ms;
}
.btn-submit:hover { background: var(--accent-hover); }
.btn-submit.create { margin-bottom: 4px; }

.divider { display: flex; align-items: center; gap: 12px; margin: 20px 0; font-size: 12px; color: var(--fg-subtle); }
.divider::before, .divider::after { content: ''; flex: 1; height: 1px; background: var(--border); }

.invite-list { display: flex; flex-direction: column; gap: 10px; }
.invite-row {
  display: flex; align-items: center; gap: 12px; padding: 12px 14px;
  border: 1px solid var(--border); border-radius: var(--r-lg); background: var(--bg-panel);
}
.ws-badge {
  width: 36px; height: 36px; border-radius: 8px; flex-shrink: 0;
  display: grid; place-items: center; font-size: 12px; font-weight: 700;
}
.invite-meta { flex: 1; min-width: 0; }
.invite-name { font-weight: 600; font-size: 13px; }
.invite-sub { font-size: 12px; color: var(--fg-subtle); }
.invite-actions { display: flex; align-items: center; gap: 6px; }

.btn-link { background: none; border: none; cursor: pointer; color: var(--accent); font-weight: 500; font-size: 13px; padding: 0; }
.btn-link:hover { text-decoration: underline; }
.btn-link.muted { color: var(--fg-subtle); font-size: 12px; }
.btn-accept {
  height: 30px; padding: 0 14px; border: none; border-radius: var(--r-md);
  background: var(--accent); color: var(--accent-fg); font-size: 13px; font-weight: 500;
  cursor: pointer; display: grid; place-items: center; min-width: 64px;
}
.btn-accept:disabled { opacity: 0.7; cursor: default; }
@keyframes spin { to { transform: rotate(360deg); } }
.spinner { animation: spin 0.6s linear infinite; }
</style>
