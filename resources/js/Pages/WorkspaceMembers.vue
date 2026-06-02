<template>
  <AppLayout title="Members">
    <div class="members-page">

      <!-- Page header -->
      <div class="page-header">
        <div class="page-header-row">
          <h2 class="page-title">Members</h2>
          <span class="page-meta">{{ localMembers.length }} member{{ localMembers.length !== 1 ? 's' : '' }}<template v-if="!selfHosted"> · {{ localPending.length }} pending</template></span>
        </div>
        <p class="page-desc">
          Manage who has access to <strong>{{ workspace?.name }}</strong>.
          <template v-if="selfHosted">New members are created instantly with a generated password — no email invite needed.</template>
        </p>
      </div>

      <!-- Just-created credential (self-hosted only, shown once) -->
      <div v-if="selfHosted && createdCred" class="list-card cred-card">
        <div class="cred-head">
          <LockIcon class="cred-icon" />
          <div class="cred-head-text">
            <div class="cred-title">Account created for {{ createdCred.name }}</div>
            <div class="cred-sub">Share this password securely — it won't be shown again.</div>
          </div>
          <button type="button" class="cred-dismiss" @click="createdCred = null">Dismiss</button>
        </div>
        <div class="cred-row">
          <div class="cred-value">{{ createdCred.email }} · {{ createdCred.password }}</div>
          <button type="button" class="cred-copy" @click="copyCred">{{ copied ? 'Copied' : 'Copy' }}</button>
        </div>
      </div>

      <!-- Add member (self-hosted) / Invite by email (cloud) — owner only -->
      <div v-if="isOwner" class="list-card invite-card">
        <div class="invite-label">{{ selfHosted ? 'Add a member' : 'Invite by email' }}</div>

        <!-- Self-hosted: create account directly -->
        <form v-if="selfHosted" @submit.prevent="submitAdd" class="invite-form">
          <input
            v-model="addForm.name"
            type="text"
            placeholder="Full name (optional)"
            class="field-input name-input"
          />
          <div class="invite-email-col">
            <input
              v-model="addForm.email"
              type="email"
              placeholder="name@northstarlabs.local"
              class="field-input"
              :class="{ 'field-input--error': addForm.errors.email }"
            />
            <span v-if="addForm.errors.email" class="field-error">{{ addForm.errors.email }}</span>
          </div>
          <select v-model="addForm.role" class="field-input role-select">
            <option value="admin">Admin</option>
            <option value="member">Member</option>
            <option value="viewer">Viewer</option>
          </select>
          <div class="invite-access">
            <ProjectAccessControl
              v-model="addForm.projects"
              :projects="projects"
              aria-label="Project access for new member"
            />
          </div>
          <button type="submit" class="btn-send-invite" :disabled="addForm.processing">
            <svg v-if="addForm.processing" class="spinner" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
            </svg>
            {{ addForm.processing ? 'Adding…' : 'Add member' }}
          </button>
        </form>

        <!-- Cloud: invite by email -->
        <form v-else @submit.prevent="submitInvite" class="invite-form">
          <div class="invite-email-col">
            <input
              v-model="inviteForm.email"
              type="email"
              placeholder="colleague@company.com"
              class="field-input"
              :class="{ 'field-input--error': inviteForm.errors.email }"
            />
            <span v-if="inviteForm.errors.email" class="field-error">{{ inviteForm.errors.email }}</span>
          </div>
          <select v-model="inviteForm.role" class="field-input role-select">
            <option value="admin">Admin</option>
            <option value="member">Member</option>
            <option value="viewer">Viewer</option>
          </select>
          <div class="invite-access">
            <ProjectAccessControl
              v-model="inviteForm.projects"
              :projects="projects"
              aria-label="Project access for new invite"
            />
          </div>
          <button type="submit" class="btn-send-invite" :disabled="inviteForm.processing">
            <svg v-if="inviteForm.processing" class="spinner" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
            </svg>
            {{ inviteForm.processing ? 'Sending…' : 'Send invite' }}
          </button>
        </form>

        <div class="invite-hint">
          <template v-if="selfHosted">A password is generated automatically and the account is active immediately. Choose which projects they can see.</template>
          <template v-else>Choose which projects this teammate can see. You can change access at any time.</template>
        </div>
      </div>

      <!-- Current members -->
      <div class="list-card" :style="{ marginBottom: (!selfHosted && localPending.length) ? '20px' : '0' }">
        <div class="head">
          <UsersIcon class="head-icon" />
          <span class="title">Current members</span>
        </div>

        <div v-for="m in localMembers" :key="m.id" class="task-row member-row">
          <Avatar :name="m.name" size="md" />

          <div class="member-info">
            <div class="member-name">
              {{ m.name }}<span v-if="m.id === authUserId" class="you-label"> (you)</span>
            </div>
            <div class="member-email">{{ m.email }}</div>
          </div>

          <div class="member-right">
            <ProjectAccessControl
              :model-value="m.projectAccess"
              :projects="projects"
              :locked="m.role === 'owner' || !isOwner"
              :aria-label="`Project access for ${m.name}`"
              @change="(ids) => updateMemberAccess(m, ids)"
            />

            <span class="joined-date">Joined {{ m.joined }}</span>

            <!-- Own row or owner: static badge -->
            <template v-if="m.id === authUserId || m.role === 'owner'">
              <RoleBadge :role="m.role" />
            </template>

            <!-- Others (owner viewing): clickable badge → dropdown -->
            <template v-else-if="isOwner">
              <DropdownMenu align="right" :width="150">
                <template #trigger>
                  <span class="role-badge-btn">
                    <RoleBadge :role="m.role" />
                  </span>
                </template>

                <MenuItem @click="updateRole(m, 'admin')">
                  <CheckIcon v-if="m.role === 'admin'" class="check-icon" />
                  <span v-else class="check-spacer" />
                  Admin
                </MenuItem>
                <MenuItem @click="updateRole(m, 'member')">
                  <CheckIcon v-if="m.role === 'member'" class="check-icon" />
                  <span v-else class="check-spacer" />
                  Member
                </MenuItem>
                <MenuItem @click="updateRole(m, 'viewer')">
                  <CheckIcon v-if="m.role === 'viewer'" class="check-icon" />
                  <span v-else class="check-spacer" />
                  Viewer
                </MenuItem>
                <div class="menu-divider-line" />
                <MenuItem danger @click="removeMember(m)">
                  Remove member
                </MenuItem>
              </DropdownMenu>
            </template>

            <!-- Non-owner viewing others: static badge -->
            <template v-else>
              <RoleBadge :role="m.role" />
            </template>
          </div>
        </div>
      </div>

      <!-- Pending invitations — cloud only; self-hosted accounts are active immediately -->
      <div v-if="!selfHosted && localPending.length > 0" class="list-card">
        <div class="head">
          <InboxIcon class="head-icon" />
          <span class="title">Pending invitations</span>
          <span class="head-meta">{{ localPending.length }} awaiting acceptance</span>
        </div>

        <div v-for="p in localPending" :key="p.id" class="task-row pending-row">
          <div class="pending-avatar">?</div>

          <div class="pending-info">
            <div class="pending-email">{{ p.email }}</div>
            <div class="pending-sent">Sent {{ p.sent }}</div>
          </div>

          <ProjectAccessControl
            :model-value="p.projectAccess"
            :projects="projects"
            :locked="!isOwner"
            :aria-label="`Project access for ${p.email}`"
            @change="(ids) => updatePendingAccess(p, ids)"
          />

          <RoleBadge :role="p.role" />

          <button v-if="isOwner" type="button" class="revoke-btn" @click="revokeInvite(p)">
            Revoke
          </button>
        </div>
      </div>

    </div>
  </AppLayout>
</template>

<script setup>
import { computed, defineComponent, h, ref, watch, onMounted, onBeforeUnmount } from 'vue'
import { usePage, useForm, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Avatar from '@/Components/UI/Avatar.vue'
import DropdownMenu from '@/Components/UI/DropdownMenu.vue'
import MenuItem from '@/Components/UI/MenuItem.vue'
import ProjectAccessControl from '@/Components/UI/ProjectAccessControl.vue'
import { UsersIcon, InboxIcon, CheckIcon, LockIcon } from '@/Components/UI/Icons.vue'
import { copyText } from '@/utils/clipboard'

const ROLE_STYLES = {
  owner:  { bg: 'color-mix(in oklab, var(--accent) 12%, var(--bg-panel))', fg: 'var(--accent)' },
  admin:  { bg: 'color-mix(in oklab, #7c3aed 10%, var(--bg-panel))',       fg: '#7c3aed' },
  member: { bg: 'var(--bg-sunken)',                                         fg: 'var(--fg-muted)' },
  viewer: { bg: 'var(--bg-sunken)',                                         fg: 'var(--fg-subtle)' },
}

const RoleBadge = defineComponent({
  name: 'RoleBadge',
  props: { role: { type: String, required: true } },
  setup(props) {
    return () => {
      const key = String(props.role).toLowerCase()
      const s   = ROLE_STYLES[key] ?? ROLE_STYLES.member
      return h('span', {
        style: {
          fontSize: '11px',
          fontWeight: 600,
          padding: '2px 8px',
          borderRadius: '99px',
          background: s.bg,
          color: s.fg,
          letterSpacing: '0.02em',
          whiteSpace: 'nowrap',
        },
      }, key.charAt(0).toUpperCase() + key.slice(1))
    }
  },
})

const props = defineProps({
  members:    { type: Array,   default: () => [] },
  pending:    { type: Array,   default: () => [] },
  projects:   { type: Array,   default: () => [] },
  isOwner:    { type: Boolean, default: false },
  authUserId: { type: Number,  default: null },
})

const page      = usePage()
const workspace = computed(() => page.props.workspace)
const selfHosted = computed(() => page.props.deployment?.mode === 'self-hosted')

const allProjectIds = computed(() => props.projects.map(p => p.id))

// Local mirrors so realtime events can mutate them without an Inertia refresh.
const localMembers = ref(props.members.map(m => ({ ...m, projectAccess: [...(m.projectAccess ?? [])] })))
const localPending = ref(props.pending.map(p => ({ ...p, projectAccess: [...(p.projectAccess ?? [])] })))

watch(() => props.members, (next) => {
  localMembers.value = next.map(m => ({ ...m, projectAccess: [...(m.projectAccess ?? [])] }))
}, { deep: true })

watch(() => props.pending, (next) => {
  localPending.value = next.map(p => ({ ...p, projectAccess: [...(p.projectAccess ?? [])] }))
}, { deep: true })

// Realtime: listen for project-access changes from other admins on this workspace.
let workspaceChannel = null
onMounted(() => {
  const id = workspace.value?.id
  if (!id || !window.Echo) return

  workspaceChannel = window.Echo.private(`workspace.${id}`)
    .listen('MemberProjectAccessUpdated', ({ member_id, project_access }) => {
      const row = localMembers.value.find(m => m.id === member_id)
      if (row) row.projectAccess = Array.isArray(project_access) ? [...project_access] : []
    })
    .listen('InvitationProjectAccessUpdated', ({ invitation_id, project_access }) => {
      const row = localPending.value.find(p => p.id === invitation_id)
      if (row) row.projectAccess = Array.isArray(project_access) ? [...project_access] : []
    })
    // Roster changed (member added/invited/role-updated/removed, invite revoked)
    // — re-pull members + pending so every admin's table stays in sync.
    .listen('WorkspaceMembersChanged', () => {
      router.reload({ only: ['members', 'pending'], preserveScroll: true, preserveState: true })
    })
    // A new project appeared in this workspace — refresh the project list that
    // backs the per-member access matrix.
    .listen('ProjectCreated', () => {
      router.reload({ only: ['members', 'pending', 'projects'], preserveScroll: true, preserveState: true })
    })
})

onBeforeUnmount(() => {
  const id = workspace.value?.id
  if (id && window.Echo) window.Echo.leave(`workspace.${id}`)
  workspaceChannel = null
})

const inviteForm = useForm({
  email:    '',
  role:     'member',
  projects: [...allProjectIds.value],
})

function submitInvite() {
  inviteForm.post(route('settings.members.invite'), {
    onSuccess: () => inviteForm.reset('email', 'role').setData('projects', [...allProjectIds.value]),
    preserveScroll: true,
  })
}

// --- Self-hosted: add account directly with a generated password ---
const addForm = useForm({
  name:     '',
  email:    '',
  role:     'member',
  projects: [...allProjectIds.value],
})

function submitAdd() {
  addForm.post(route('settings.members.add'), {
    onSuccess: () => addForm.reset('name', 'email').setData('projects', [...allProjectIds.value]),
    preserveScroll: true,
  })
}

// One-time credential reveal, captured from the flash so it survives until dismissed.
const createdCred = ref(page.props.flash?.createdCred ?? null)
const copied      = ref(false)

watch(() => page.props.flash?.createdCred, (cred) => {
  if (cred) { createdCred.value = cred; copied.value = false }
})

async function copyCred() {
  if (!createdCred.value) return
  const ok = await copyText(`${createdCred.value.email}  ${createdCred.value.password}`)
  copied.value = ok
}

function updateRole(member, role) {
  router.patch(
    route('settings.members.role', member.id),
    { role },
    { preserveScroll: true }
  )
}

function removeMember(member) {
  if (!confirm(`Remove ${member.name} from this workspace?`)) return
  router.delete(
    route('settings.members.remove', member.id),
    { preserveScroll: true }
  )
}

function revokeInvite(invitation) {
  router.delete(
    route('settings.members.revoke', invitation.id),
    { preserveScroll: true }
  )
}

function updateMemberAccess(member, projectIds) {
  if (member.role === 'owner') return
  router.patch(
    route('settings.members.projects', member.id),
    { projects: projectIds },
    { preserveScroll: true, preserveState: true }
  )
}

function updatePendingAccess(invite, projectIds) {
  router.patch(
    route('settings.members.invitations.projects', invite.id),
    { projects: projectIds },
    { preserveScroll: true, preserveState: true }
  )
}

</script>

<style scoped>
/* The page scroll is owned by .main-area (AppLayout). Keeping this a plain
   block — rather than a second flex scroll container — avoids the nested
   overflow conflict that stopped the Current members list from scrolling.
   A normal block's bottom padding is honored across browsers, so no spacer
   hack is needed. */
.members-page {
  padding: 24px 32px;
  max-width: 860px;
  margin: 0 auto;
  width: 100%;
}

/* Page header */
.page-header {
  margin-bottom: 24px;
}
.page-header-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 4px;
}
.page-title {
  margin: 0;
  font-size: 20px;
  font-weight: 600;
  color: var(--fg);
}
.page-meta {
  font-size: 13px;
  color: var(--fg-muted);
}
.page-desc {
  margin: 0;
  font-size: 13px;
  color: var(--fg-muted);
}
.page-desc strong {
  color: var(--fg);
}

/* Invite card */
.invite-card {
  margin-bottom: 24px;
  padding: 16px 20px;
}
.invite-label {
  font-size: 13px;
  font-weight: 600;
  color: var(--fg);
  margin-bottom: 12px;
}
.invite-form {
  display: flex;
  gap: 8px;
  align-items: flex-start;
  flex-wrap: wrap;
}
.invite-email-col {
  flex: 1;
  min-width: 220px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.invite-access {
  height: 36px;
  display: inline-flex;
  align-items: center;
}
.invite-hint {
  margin-top: 8px;
  font-size: 11px;
  color: var(--fg-subtle);
}

.name-input {
  width: 160px;
  flex: 0 0 auto;
}

/* Generated-credential reveal (self-hosted) */
.cred-card {
  margin-bottom: 16px;
  padding: 12px 16px;
  border-color: var(--accent);
  background: color-mix(in oklab, var(--accent) 5%, var(--bg-panel));
}
.cred-head {
  display: flex;
  align-items: center;
  gap: 10px;
}
.cred-icon {
  width: 16px;
  height: 16px;
  color: var(--accent);
  flex-shrink: 0;
}
.cred-head-text {
  flex: 1;
  min-width: 0;
}
.cred-title {
  font-size: 13px;
  font-weight: 600;
  color: var(--fg);
}
.cred-sub {
  font-size: 12px;
  color: var(--fg-muted);
}
.cred-dismiss {
  border: none;
  background: transparent;
  color: var(--fg-subtle);
  font-size: 12px;
  font-family: inherit;
  cursor: pointer;
  padding: 4px 6px;
  border-radius: var(--r-sm);
}
.cred-dismiss:hover { background: var(--bg-hover); color: var(--fg); }
.cred-row {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 10px;
}
.cred-value {
  flex: 1;
  min-width: 0;
  font-family: var(--font-mono);
  font-size: 13px;
  padding: 8px 10px;
  background: var(--bg-sunken);
  border: 1px dashed var(--border-strong, var(--border));
  border-radius: var(--r-sm);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.cred-copy {
  height: 32px;
  padding: 0 12px;
  border-radius: var(--r-sm);
  border: 1px solid var(--border);
  background: var(--bg-panel);
  color: var(--fg);
  font-size: 13px;
  font-family: inherit;
  cursor: pointer;
  white-space: nowrap;
  transition: background 80ms;
}
.cred-copy:hover { background: var(--bg-hover); }

.field-input {
  height: 36px;
  padding: 0 10px;
  border-radius: var(--r-md);
  border: 1px solid var(--border);
  background: var(--bg-panel);
  color: var(--fg);
  font-size: 13px;
  font-family: inherit;
  outline: none;
  transition: border-color 80ms, box-shadow 80ms;
  box-sizing: border-box;
  width: 100%;
}
.field-input:focus {
  border-color: var(--accent);
  box-shadow: 0 0 0 3px var(--accent-soft);
}
.field-input--error { border-color: var(--status-blocked); }
.field-error { font-size: 12px; color: var(--status-blocked); }

.role-select {
  width: 110px;
  cursor: pointer;
}

.btn-send-invite {
  height: 36px;
  padding: 0 14px;
  border-radius: var(--r-md);
  background: var(--accent);
  color: var(--accent-fg);
  border: none;
  font-size: 13px;
  font-weight: 500;
  font-family: inherit;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 6px;
  transition: background 80ms;
  white-space: nowrap;
}
.btn-send-invite:hover:not(:disabled) { background: var(--accent-hover); }
.btn-send-invite:disabled { opacity: 0.6; cursor: not-allowed; }

@keyframes spin { to { transform: rotate(360deg); } }
.spinner { animation: spin 0.7s linear infinite; }

/* Members card head */
.head-icon {
  width: 14px;
  height: 14px;
  color: var(--fg-muted);
  flex-shrink: 0;
}
.head-meta {
  font-size: 12px;
  color: var(--fg-muted);
}

/* Member rows */
.member-row {
  cursor: default;
  padding: 10px 16px;
  gap: 12px;
}
.member-row:hover { background: transparent; }

.member-info {
  flex: 1;
  min-width: 0;
}
.member-name {
  font-weight: 500;
  font-size: 13px;
  color: var(--fg);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.you-label {
  font-weight: 400;
  color: var(--fg-muted);
}
.member-email {
  font-size: 12px;
  color: var(--fg-muted);
  font-family: var(--font-mono);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.member-right {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-shrink: 0;
}
.joined-date {
  font-size: 12px;
  color: var(--fg-muted);
  white-space: nowrap;
}

.role-badge-btn {
  cursor: pointer;
  display: inline-flex;
}
.role-badge-btn:hover { opacity: 0.8; }

.check-icon {
  width: 14px;
  height: 14px;
  color: var(--accent);
  flex-shrink: 0;
}
.check-spacer {
  display: inline-block;
  width: 14px;
  flex-shrink: 0;
}

.menu-divider-line {
  height: 1px;
  background: var(--border);
  margin: 4px 0;
}

/* Pending rows */
.pending-row {
  cursor: default;
  padding: 10px 16px;
  gap: 12px;
}
.pending-row:hover { background: transparent; }

.pending-avatar {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: var(--bg-active);
  color: var(--fg-subtle);
  font-family: var(--font-mono);
  font-size: 11px;
  display: grid;
  place-items: center;
  flex-shrink: 0;
}

.pending-info {
  flex: 1;
  min-width: 0;
}
.pending-email {
  font-size: 13px;
  font-family: var(--font-mono);
  color: var(--fg);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.pending-sent {
  font-size: 12px;
  color: var(--fg-muted);
}

.revoke-btn {
  height: 26px;
  padding: 0 10px;
  border-radius: var(--r-sm);
  border: 1px solid var(--border);
  background: transparent;
  color: var(--fg-subtle);
  font-size: 12px;
  font-weight: 500;
  font-family: inherit;
  cursor: pointer;
  white-space: nowrap;
  transition: background 80ms, color 80ms;
}
.revoke-btn:hover {
  background: var(--bg-hover);
  color: var(--fg);
}
</style>
