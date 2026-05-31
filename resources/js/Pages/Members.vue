<template>
  <AppLayout>
    <!-- Top bar -->
    <div
      class="flex items-center gap-3 px-5 py-2.5 shrink-0"
      style="border-bottom:1px solid var(--border);background:var(--bg-panel)"
    >
      <span class="w-3 h-3 rounded-full shrink-0" :style="{ background: project.color }" />
      <h1 class="text-sm font-semibold shrink-0" style="color:var(--fg)">{{ project.name }}</h1>
      <div class="h-4 w-px mx-1 shrink-0" style="background:var(--border)" />
      <span class="text-sm" style="color:var(--fg-muted)">Members</span>
    </div>

    <!-- Content -->
    <div class="flex-1 overflow-auto px-5 py-5 max-w-2xl">
      <!-- Invite form (owner only) -->
      <div v-if="isOwner" class="invite-card mb-6">
        <h2 class="text-sm font-semibold mb-3" style="color:var(--fg)">Invite a team member</h2>
        <form @submit.prevent="submitInvite" class="flex gap-2">
          <input
            v-model="inviteForm.email"
            type="email"
            placeholder="colleague@company.com"
            class="field-input flex-1"
            :class="{ 'field-input--error': inviteForm.errors.email }"
            required
          />
          <select v-model="inviteForm.role" class="field-input" style="width:110px">
            <option value="member">Member</option>
            <option value="admin">Admin</option>
          </select>
          <button type="submit" class="btn-invite" :disabled="inviteForm.processing">
            <svg v-if="inviteForm.processing" class="spinner" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>
            <svg v-else width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Invite
          </button>
        </form>
        <p v-if="inviteForm.errors.email" class="field-error mt-1">{{ inviteForm.errors.email }}</p>
        <p class="text-xs mt-2" style="color:var(--fg-subtle)">
          The user must already have an account on Taskline.
        </p>
      </div>

      <!-- Members list -->
      <div class="members-list">
        <div class="members-list-header">
          <span>{{ members.length }} member{{ members.length !== 1 ? 's' : '' }}</span>
        </div>

        <div
          v-for="m in members"
          :key="m.id"
          class="member-row"
        >
          <Avatar :name="m.name" size="md" />
          <div class="flex-1 min-w-0">
            <div class="text-sm font-medium truncate" style="color:var(--fg)">{{ m.name }}</div>
            <div class="text-xs truncate" style="color:var(--fg-muted)">{{ m.email }}</div>
          </div>

          <!-- Role badge / selector -->
          <div v-if="m.role === 'owner'" class="role-badge role-owner">Owner</div>
          <template v-else-if="isOwner">
            <select
              :value="m.role"
              class="role-select"
              @change="updateRole(m, $event.target.value)"
            >
              <option value="admin">Admin</option>
              <option value="member">Member</option>
            </select>
            <button
              type="button"
              class="remove-btn"
              title="Remove member"
              @click="removeMember(m)"
            >
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
          </template>
          <div v-else class="role-badge" :class="m.role === 'admin' ? 'role-admin' : 'role-member'">
            {{ m.role === 'admin' ? 'Admin' : 'Member' }}
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Avatar from '@/Components/UI/Avatar.vue'
import { useForm, router } from '@inertiajs/vue3'

const props = defineProps({
  project: { type: Object, required: true },
  members: { type: Array,  default: () => [] },
  isOwner: { type: Boolean, default: false },
})

const inviteForm = useForm({
  email: '',
  role: 'member',
})

function submitInvite() {
  inviteForm.post(route('projects.members.invite', props.project.uuid), {
    onSuccess: () => inviteForm.reset(),
    preserveScroll: true,
  })
}

function updateRole(member, role) {
  router.patch(
    route('projects.members.role', [props.project.uuid, member.id]),
    { role },
    { preserveScroll: true }
  )
}

function removeMember(member) {
  if (!confirm(`Remove ${member.name} from this project?`)) return
  router.delete(
    route('projects.members.remove', [props.project.uuid, member.id]),
    { preserveScroll: true }
  )
}
</script>

<style scoped>
.invite-card {
  background: var(--bg-panel);
  border: 1px solid var(--border);
  border-radius: var(--r-lg);
  padding: 16px;
}

.field-input {
  height: 34px;
  padding: 0 10px;
  border-radius: var(--r-md);
  border: 1px solid var(--border);
  background: var(--bg-sunken);
  color: var(--fg);
  font-size: 13px;
  outline: none;
  transition: border-color 80ms;
  box-sizing: border-box;
}
.field-input:focus { border-color: var(--accent); }
.field-input--error { border-color: var(--status-blocked); }
.field-error { font-size: 12px; color: var(--status-blocked); }

.btn-invite {
  height: 34px;
  padding: 0 14px;
  border-radius: var(--r-md);
  background: var(--accent);
  color: var(--accent-fg);
  border: none;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 6px;
  transition: background 80ms;
  white-space: nowrap;
}
.btn-invite:hover:not(:disabled) { background: var(--accent-hover); }
.btn-invite:disabled { opacity: 0.6; cursor: not-allowed; }

@keyframes spin { to { transform: rotate(360deg); } }
.spinner { animation: spin 0.7s linear infinite; }

.members-list {
  background: var(--bg-panel);
  border: 1px solid var(--border);
  border-radius: var(--r-lg);
  overflow: hidden;
}
.members-list-header {
  padding: 10px 16px;
  font-size: 12px;
  font-weight: 500;
  color: var(--fg-muted);
  border-bottom: 1px solid var(--border);
  background: var(--bg-sunken);
}
.member-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 16px;
  border-bottom: 1px solid var(--border);
}
.member-row:last-child { border-bottom: none; }

.role-badge {
  font-size: 11px;
  font-weight: 500;
  padding: 2px 8px;
  border-radius: 20px;
  white-space: nowrap;
}
.role-owner  { background: var(--accent-soft); color: var(--accent); }
.role-admin  { background: #fef3c7; color: #92400e; }
.role-member { background: var(--bg-sunken); color: var(--fg-muted); }
[data-theme="dark"] .role-admin { background: #78350f33; color: #fbbf24; }

.role-select {
  height: 28px;
  padding: 0 6px;
  border-radius: var(--r-sm);
  border: 1px solid var(--border);
  background: var(--bg-sunken);
  color: var(--fg);
  font-size: 12px;
  cursor: pointer;
  outline: none;
}
.role-select:focus { border-color: var(--accent); }

.remove-btn {
  width: 28px; height: 28px;
  border-radius: var(--r-sm);
  border: none;
  background: transparent;
  color: var(--fg-muted);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background 80ms, color 80ms;
}
.remove-btn:hover { background: #fee2e2; color: var(--status-blocked); }
[data-theme="dark"] .remove-btn:hover { background: #7f1d1d33; }
</style>
