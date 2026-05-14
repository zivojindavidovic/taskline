<template>
  <AppLayout>
    <div class="px-8 py-6 max-w-3xl mx-auto flex flex-col gap-6">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-xl font-semibold" style="color:var(--fg)">Inbox</h1>
          <p class="text-sm mt-0.5" style="color:var(--fg-muted)">
            {{ unreadCount > 0 ? `${unreadCount} new notification${unreadCount !== 1 ? 's' : ''}` : 'All caught up' }}
          </p>
        </div>
        <button
          v-if="notifications.length > 0"
          type="button"
          class="text-xs font-medium"
          style="color:var(--accent)"
          @click="markAllRead"
        >Mark all read</button>
      </div>

      <!-- Notifications list -->
      <div
        v-if="notifications.length > 0"
        class="rounded-xl overflow-hidden"
        style="border:1px solid var(--border)"
      >
        <div
          v-for="(n, i) in notifications"
          :key="n.id"
          class="flex items-start gap-3 px-4 py-3 cursor-pointer transition-colors hover:bg-[var(--bg-hover)]"
          :style="[
            i > 0 ? 'border-top:1px solid var(--border)' : '',
            !n.read ? 'background:var(--bg-sunken)' : '',
          ].filter(Boolean).join(';')"
          @click="openNotification(n)"
        >
          <!-- Unread dot -->
          <div class="w-2 h-2 rounded-full shrink-0 mt-1.5" :style="n.read ? 'background:transparent' : 'background:var(--accent)'" />

          <!-- Avatar -->
          <Avatar :name="n.actor" size="sm" class="shrink-0 mt-0.5" />

          <!-- Content -->
          <div class="flex-1 min-w-0">
            <p class="text-sm" style="color:var(--fg)">
              <strong>{{ n.actor }}</strong>
              {{ n.verb }}
              <span class="font-medium" style="color:var(--accent)">{{ n.target }}</span>
            </p>
            <p v-if="n.excerpt" class="text-xs mt-0.5 truncate" style="color:var(--fg-muted)">
              {{ n.excerpt }}
            </p>
            <p class="text-xs mt-1" style="color:var(--fg-muted)">{{ n.time }}</p>
          </div>

          <!-- Project badge -->
          <span
            v-if="n.project_key"
            class="shrink-0 text-xs px-2 py-0.5 rounded-full"
            style="background:var(--bg-sunken);color:var(--fg-muted)"
          >{{ n.project_key }}</span>
        </div>
      </div>

      <!-- Empty state -->
      <div v-else class="text-center py-16 flex flex-col items-center gap-3">
        <div
          class="w-12 h-12 rounded-full flex items-center justify-center"
          style="background:var(--bg-sunken)"
        >
          <BellIcon class="w-5 h-5" style="color:var(--fg-muted)" />
        </div>
        <p class="text-sm font-medium" style="color:var(--fg)">You're all caught up!</p>
        <p class="text-xs" style="color:var(--fg-muted)">Notifications will appear here when team members interact with your tasks.</p>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Avatar from '@/Components/UI/Avatar.vue'
import { BellIcon } from '@/Components/UI/Icons.vue'

const props = defineProps({
  notifications: { type: Array, default: () => [] },
})

// Local read state (no persistence — just for this session feel)
const readIds = ref(new Set())

const notifications = computed(() =>
  props.notifications.map(n => ({ ...n, read: readIds.value.has(n.id) }))
)

const unreadCount = computed(() => notifications.value.filter(n => !n.read).length)

function openNotification(n) {
  readIds.value.add(n.id)
  if (n.task_id && n.project_id) {
    router.visit(route('projects.show', n.project_id) + '?task=' + n.task_id)
  }
}

function markAllRead() {
  props.notifications.forEach(n => readIds.value.add(n.id))
}
</script>
