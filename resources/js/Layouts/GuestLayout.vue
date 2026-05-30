<template>
  <div class="auth-shell">
    <button class="theme-btn" @click="toggleDark" :title="dark ? 'Switch to light' : 'Switch to dark'">
      <svg v-if="dark" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
      <svg v-else width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
    </button>

    <div class="auth-card">
      <div class="card-stripe" />
      <div class="card-inner">
        <slot />
      </div>
    </div>

    <!-- Detected deployment (read-only) -->
    <div
      v-if="deployment"
      class="deploy-pill"
      :class="{ 'is-cloud': deployment.mode === 'cloud' }"
      :title="deployment.mode === 'cloud'
        ? 'Hosted Taskline Cloud'
        : 'Self-hosted Taskline instance'"
    >
      <span class="dp-dot" />
      <svg v-if="deployment.mode === 'cloud'" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 10h-1.26A8 8 0 1 0 9 20h9a5 5 0 0 0 0-10z"/></svg>
      <svg v-else width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="8" rx="2"/><rect x="2" y="14" width="20" height="8" rx="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg>
      <span>{{ deployment.mode === 'cloud' ? 'Cloud' : 'Self-hosted' }} · <code>{{ deployment.host }}</code></span>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { usePage } from '@inertiajs/vue3'

const page = usePage()
const deployment = computed(() => page.props.deployment ?? null)

const dark = ref(false)

onMounted(() => {
  dark.value = window.matchMedia?.('(prefers-color-scheme: dark)').matches ?? false
  applyTheme()
})

function applyTheme() {
  document.documentElement.dataset.theme = dark.value ? 'dark' : ''
}

function toggleDark() {
  dark.value = !dark.value
  applyTheme()
}
</script>

<style>
*, *::before, *::after { box-sizing: border-box; }
:root {
  --font-ui: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
  --font-mono: "JetBrains Mono", ui-monospace, Menlo, monospace;
  --bg-app:     #fafaf9;
  --bg-panel:   #ffffff;
  --bg-sunken:  #f4f4f2;
  --bg-hover:   #f0f0ed;
  --bg-active:  #e8e8e3;
  --border:       #e7e7e2;
  --border-strong:#d4d4cf;
  --fg:         #1a1a17;
  --fg-muted:   #6b6b65;
  --fg-subtle:  #9a9a93;
  --accent:       #4f46e5;
  --accent-hover: #4338ca;
  --accent-soft:  #eef2ff;
  --accent-fg:    #ffffff;
  --status-done: #16a34a;
  --status-blocked: #dc2626;
  --shadow-lg: 0 12px 40px rgba(20,20,17,0.10), 0 2px 8px rgba(20,20,17,0.05);
  --r-sm: 4px; --r-md: 6px; --r-lg: 8px; --r-xl: 12px;
}
[data-theme="dark"] {
  --bg-app:     #0e0e0d;
  --bg-panel:   #18181a;
  --bg-sunken:  #131314;
  --bg-hover:   #232326;
  --bg-active:  #2c2c30;
  --border:     #26262a;
  --border-strong: #36363b;
  --fg:         #f1f1ee;
  --fg-muted:   #9a9a95;
  --fg-subtle:  #6a6a65;
  --accent:     #6366f1;
  --accent-hover: #818cf8;
  --accent-soft: #1e1b3a;
  --shadow-lg: 0 12px 40px rgba(0,0,0,0.5), 0 2px 8px rgba(0,0,0,0.3);
}
html, body { margin: 0; padding: 0; height: 100%; font-family: var(--font-ui); font-size: 14px; color: var(--fg); background: var(--bg-app); -webkit-font-smoothing: antialiased; }
</style>

<style scoped>
.auth-shell {
  min-height: 100vh;
  display: grid;
  place-items: center;
  padding: 24px 16px;
  position: relative;
  overflow: hidden;
  background: var(--bg-app);
  font-family: var(--font-ui);
}
.auth-shell::before {
  content: "";
  position: fixed;
  inset: 0;
  background-image: radial-gradient(circle, var(--border) 1px, transparent 1px);
  background-size: 28px 28px;
  opacity: 0.6;
  pointer-events: none;
  z-index: 0;
}
.auth-shell::after {
  content: "";
  position: fixed;
  top: -20%;
  left: 50%;
  transform: translateX(-50%);
  width: 600px;
  height: 600px;
  background: radial-gradient(ellipse, color-mix(in oklab, var(--accent) 8%, transparent) 0%, transparent 70%);
  pointer-events: none;
  z-index: 0;
}
.auth-card {
  position: relative;
  z-index: 1;
  width: 100%;
  max-width: 420px;
  background: var(--bg-panel);
  border: 1px solid var(--border);
  border-radius: var(--r-xl);
  box-shadow: var(--shadow-lg);
  overflow: hidden;
  animation: cardIn 220ms cubic-bezier(0.32, 0.72, 0, 1);
}
@keyframes cardIn {
  from { opacity: 0; transform: translateY(10px); }
  to   { opacity: 1; transform: translateY(0); }
}
.card-stripe {
  height: 4px;
  background: linear-gradient(90deg, var(--accent), color-mix(in oklab, var(--accent) 55%, transparent));
}
.card-inner { padding: 32px; }
.theme-btn {
  position: fixed;
  top: 14px; right: 14px;
  z-index: 10;
  width: 32px; height: 32px;
  border-radius: 50%;
  border: 1px solid var(--border);
  background: var(--bg-panel);
  color: var(--fg-muted);
  cursor: pointer;
  display: grid; place-items: center;
  box-shadow: 0 1px 2px rgba(20,20,17,0.04);
  transition: background 80ms;
}
.theme-btn:hover { background: var(--bg-hover); color: var(--fg); }

.deploy-pill {
  position: fixed;
  bottom: 14px; left: 14px;
  z-index: 10;
  display: flex; align-items: center; gap: 8px;
  padding: 6px 11px;
  border-radius: 999px;
  border: 1px solid var(--border);
  background: var(--bg-panel);
  box-shadow: 0 1px 2px rgba(20,20,17,0.04);
  font-size: 12px;
  color: var(--fg-muted);
}
.deploy-pill .dp-dot {
  width: 7px; height: 7px; border-radius: 50%;
  background: var(--status-done);
  flex-shrink: 0;
}
.deploy-pill.is-cloud .dp-dot { background: var(--accent); }
.deploy-pill code { font-family: var(--font-mono); font-size: 11.5px; color: var(--fg); }
</style>
