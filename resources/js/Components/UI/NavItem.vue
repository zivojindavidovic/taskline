<template>
  <Link
    :href="href"
    class="nav-item-link"
    :class="{ active }"
  >
    <slot />
  </Link>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
  href:   { type: String, required: true },
  active: { type: Boolean, default: false },
})
</script>

<style scoped>
.nav-item-link {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 0 8px;
  height: 32px;
  border-radius: 4px;
  font-size: 13px;
  font-family: var(--font-ui);
  font-weight: 400;
  color: var(--fg-muted);
  cursor: pointer;
  text-decoration: none;
  transition: background 80ms, color 80ms;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  user-select: none;
}
.nav-item-link:hover {
  background: var(--bg-hover);
  color: var(--fg);
}
.nav-item-link.active {
  background: var(--bg-active);
  color: var(--fg);
  font-weight: 500;
}
</style>