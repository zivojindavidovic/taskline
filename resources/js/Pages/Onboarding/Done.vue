<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue'
import { Head, router } from '@inertiajs/vue3'
import { onMounted, onUnmounted } from 'vue'

defineProps({ workspaceName: String })

let timer = null
onMounted(() => { timer = setTimeout(() => router.visit(route('dashboard')), 1800) })
onUnmounted(() => clearTimeout(timer))
</script>

<template>
  <GuestLayout>
    <Head title="Workspace ready" />

    <div class="center">
      <div class="icon-circle success">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
      </div>
      <h1 class="auth-title">{{ workspaceName }} is ready</h1>
      <p class="auth-subtitle">Taking you to your workspace…</p>
      <div class="spinner-wrap">
        <svg class="spinner" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>
      </div>
    </div>
  </GuestLayout>
</template>

<style scoped>
.center { text-align: center; }
.icon-circle {
  width: 56px; height: 56px; border-radius: 50%; margin: 0 auto 20px; display: grid; place-items: center;
  animation: scaleIn 300ms cubic-bezier(0.32, 0.72, 0, 1);
}
.icon-circle.success { background: color-mix(in oklab, var(--status-done) 14%, transparent); color: var(--status-done); }
@keyframes scaleIn { from { transform: scale(0.6); opacity: 0; } to { transform: scale(1); opacity: 1; } }
.auth-title { font-size: 20px; font-weight: 600; color: var(--fg); margin: 0 0 6px; }
.auth-subtitle { font-size: 14px; color: var(--fg-muted); margin: 0 0 8px; }
.spinner-wrap { display: flex; justify-content: center; margin-top: 8px; }
@keyframes spin { to { transform: rotate(360deg); } }
.spinner { animation: spin 0.6s linear infinite; color: var(--accent); }
</style>
