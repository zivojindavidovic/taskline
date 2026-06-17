<template>
  <AppLayout>
    <div class="settings-pad">
      <div class="settings-page">
        <!-- Head -->
        <div class="settings-head">
          <Link :href="route('projects.show', project.uuid)" class="btn ghost sm back-link">
            <ArrowLeftIcon style="width:14px;height:14px" /> Back to board
          </Link>
          <h2>Project settings</h2>
          <p class="muted">Manage <strong>{{ project.name }}</strong> — name, members, and access.</p>
        </div>

        <!-- General -->
        <section class="settings-card">
          <div class="settings-card-head">
            <SettingsIcon style="width:16px;height:16px" /><span class="title">General</span>
          </div>
          <div class="settings-body">
            <div class="field">
              <label class="field-label">Project name</label>
              <input
                class="input"
                v-model="nameDraft"
                :disabled="!canManage"
                placeholder="Project name"
                @blur="saveName"
                @keydown.enter.prevent="saveName"
              />
              <p v-if="errors.name" class="field-error">{{ errors.name }}</p>
            </div>

            <div class="field">
              <label class="field-label">Project key</label>
              <div class="key-readonly mono">{{ project.key }}</div>
              <span class="field-hint">Used as the prefix for task IDs (e.g. {{ project.key }}-128). Can't be changed.</span>
            </div>

            <div class="field">
              <label class="field-label">Color</label>
              <div class="color-grid wide">
                <button
                  v-for="c in COLUMN_COLORS"
                  :key="c"
                  type="button"
                  class="swatch"
                  :class="{ active: (project.color || '').toLowerCase() === c.toLowerCase() }"
                  :style="{ background: c }"
                  :title="c"
                  :disabled="!canManage"
                  @click="setColor(c)"
                />
              </div>
            </div>
          </div>
        </section>

        <!-- Members -->
        <section class="settings-card">
          <div class="settings-card-head">
            <UserIcon style="width:16px;height:16px" /><span class="title">Members</span>
            <span class="count">{{ members.length }}</span>
          </div>

          <div v-if="isOwner" class="add-member-row">
            <DropdownMenu align="left" :width="248">
              <template #trigger>
                <button type="button" class="btn secondary sm" :disabled="!available.length">
                  <PlusIcon style="width:13px;height:13px" /> Add member
                </button>
              </template>
              <template #default="{ close }">
                <div class="menu-label">Add someone to this project</div>
                <MenuItem
                  v-for="u in available"
                  :key="u.id"
                  @click="addMember(u); close()"
                >
                  <Avatar :name="u.name" :color="u.avatar_color || null" size="sm" />
                  <span class="truncate" style="flex:1">{{ u.name }}</span>
                </MenuItem>
                <div v-if="!available.length" class="menu-empty">Everyone's already a member.</div>
              </template>
            </DropdownMenu>
            <div class="add-role-pick">
              <span class="muted">as</span>
              <select v-model="addRole" class="input role-select">
                <option value="member">Member</option>
                <option value="admin">Admin</option>
              </select>
            </div>
          </div>

          <div class="member-list">
            <div v-for="m in members" :key="m.id" class="member-row">
              <Avatar :name="m.name" :color="m.avatar_color || null" size="md" />
              <div class="member-info">
                <div class="member-name">
                  {{ m.name }}<span v-if="m.id === authId" class="muted"> (you)</span>
                </div>
                <div class="muted member-sub">{{ roleDescription(m.role) }}</div>
              </div>

              <DropdownMenu v-if="isOwner && m.role !== 'owner'" align="right" :width="190">
                <template #trigger>
                  <button type="button" class="role-trigger">
                    <span class="role-badge" :class="`role-${m.role}`">{{ roleLabel(m.role) }}</span>
                    <ChevronIcon style="width:12px;height:12px" />
                  </button>
                </template>
                <template #default="{ close }">
                  <MenuItem @click="changeRole(m, 'admin'); close()">
                    <span class="check-slot"><CheckIcon v-if="m.role === 'admin'" style="width:13px;height:13px" /></span> Admin
                  </MenuItem>
                  <MenuItem @click="changeRole(m, 'member'); close()">
                    <span class="check-slot"><CheckIcon v-if="m.role === 'member'" style="width:13px;height:13px" /></span> Member
                  </MenuItem>
                  <div class="menu-divider" />
                  <MenuItem danger @click="removeMember(m); close()">
                    <TrashIcon style="width:13px;height:13px" /> Remove from project
                  </MenuItem>
                </template>
              </DropdownMenu>
              <span v-else class="role-badge" :class="`role-${m.role}`">{{ roleLabel(m.role) }}</span>
            </div>
          </div>
        </section>

        <!-- Danger zone -->
        <section v-if="canManage" class="settings-card danger-zone">
          <div class="settings-card-head">
            <TrashIcon style="width:16px;height:16px" /><span class="title">Danger zone</span>
          </div>
          <div class="danger-row">
            <div class="danger-text">
              <div class="danger-title">Delete this project</div>
              <div class="muted">
                Permanently removes <strong>{{ project.name }}</strong> and all of its tasks and sprints. This can't be undone.
              </div>
              <p v-if="errors.project" class="field-error" style="margin-top:6px">{{ errors.project }}</p>
            </div>
            <button
              type="button"
              class="btn danger"
              :disabled="projectCount <= 1"
              :title="projectCount <= 1 ? 'You can’t delete the last project' : ''"
              @click="showDelete = true"
            >
              <TrashIcon style="width:14px;height:14px" /> Delete project
            </button>
          </div>
        </section>
      </div>
    </div>

    <!-- Delete confirmation -->
    <AppModal :show="showDelete" :title="`Delete ${project.name}?`" @close="showDelete = false">
      <p style="margin:0;line-height:1.6;color:var(--fg)">
        This permanently deletes <strong>{{ project.name }}</strong> along with all of its tasks and sprints.
      </p>
      <div class="banner warn">
        <TrashIcon class="icon" />
        <span class="text">This can't be undone. Team members will lose access to everything in this project.</span>
      </div>
      <template #footer>
        <button type="button" class="btn ghost" @click="showDelete = false">Cancel</button>
        <button type="button" class="btn danger" @click="doDelete">
          <TrashIcon style="width:14px;height:14px" /> Delete project
        </button>
      </template>
    </AppModal>
  </AppLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Avatar from '@/Components/UI/Avatar.vue'
import DropdownMenu from '@/Components/UI/DropdownMenu.vue'
import MenuItem from '@/Components/UI/MenuItem.vue'
import AppModal from '@/Components/UI/AppModal.vue'
import {
  ArrowLeftIcon, SettingsIcon, UserIcon, PlusIcon, TrashIcon, CheckIcon, ChevronIcon,
} from '@/Components/UI/Icons.vue'

const props = defineProps({
  project:      { type: Object, required: true },
  members:      { type: Array,  default: () => [] },
  available:    { type: Array,  default: () => [] },
  isOwner:      { type: Boolean, default: false },
  canManage:    { type: Boolean, default: false },
  projectCount: { type: Number, default: 1 },
})

const page = usePage()
const authId = computed(() => page.props.auth.user.id)
const errors = computed(() => page.props.errors ?? {})

// Project color palette (shared with the column color picker).
const COLUMN_COLORS = [
  '#64748b', '#94948c', '#6b7280', '#0f172a',
  '#dc2626', '#ea580c', '#d97706', '#ca8a04',
  '#65a30d', '#16a34a', '#059669', '#0d9488',
  '#0891b2', '#0284c7', '#2563eb', '#4f46e5',
  '#7c3aed', '#9333ea', '#c026d3', '#db2777',
]

const nameDraft = ref(props.project.name)
watch(() => props.project.name, (v) => { nameDraft.value = v })

const addRole = ref('member')
const showDelete = ref(false)

function roleLabel(role) {
  return role.charAt(0).toUpperCase() + role.slice(1)
}
function roleDescription(role) {
  if (role === 'owner') return 'Project owner'
  if (role === 'admin') return 'Can manage project & members'
  return 'Can create and edit tasks'
}

function saveName() {
  const name = nameDraft.value.trim()
  if (!props.canManage || !name || name === props.project.name) return
  router.patch(route('projects.update', props.project.uuid), { name }, { preserveScroll: true })
}
function setColor(color) {
  if (!props.canManage || color === props.project.color) return
  router.patch(route('projects.update', props.project.uuid), { color }, { preserveScroll: true })
}
function addMember(u) {
  router.post(route('projects.members.invite', props.project.uuid), { email: u.email, role: addRole.value }, { preserveScroll: true })
}
function changeRole(m, role) {
  if (role === m.role) return
  router.patch(route('projects.members.role', [props.project.uuid, m.id]), { role }, { preserveScroll: true })
}
function removeMember(m) {
  router.delete(route('projects.members.remove', [props.project.uuid, m.id]), { preserveScroll: true })
}
function doDelete() {
  showDelete.value = false
  router.delete(route('projects.destroy', props.project.uuid))
}
</script>

<style scoped>
/* Ported 1:1 from the prototype's settings styles. */
/* Own scroll region (full width) so the whole page — incl. the danger zone —
   is reachable; the inner .settings-page stays centered via margin:auto.
   Relying on the parent flex-column's scroll clipped the last section. */
.settings-pad {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  padding: var(--s-6) var(--s-5) 48px;
}
.settings-page { max-width: 760px; margin: 0 auto; }

.settings-head { margin-bottom: var(--s-5); }
.settings-head .back-link { margin: 0 0 var(--s-4) calc(var(--s-2) * -1); color: var(--fg-muted); }
.settings-head h2 { margin: 0 0 4px; font-size: var(--fs-20); font-weight: 600; }
.settings-head .muted { font-size: var(--fs-13); }

.settings-card {
  background: var(--bg-panel);
  border: 1px solid var(--border);
  border-radius: var(--r-lg, 10px);
  margin-bottom: var(--s-4);
  overflow: hidden;
}
.settings-card-head {
  display: flex; align-items: center; gap: var(--s-2);
  padding: var(--s-3) var(--s-4);
  border-bottom: 1px solid var(--border);
}
.settings-card-head .title { font-weight: 600; font-size: var(--fs-14); }
.settings-card-head .count {
  margin-left: auto; font-size: var(--fs-12);
  background: var(--bg-sunken); border-radius: 99px; padding: 1px 8px; color: var(--fg-muted);
}
.settings-body { padding: var(--s-4); display: flex; flex-direction: column; gap: var(--s-4); }

.field { display: flex; flex-direction: column; gap: 6px; max-width: 420px; }
.field-label { font-size: var(--fs-13); font-weight: 500; color: var(--fg-muted); }
.field-hint { font-size: var(--fs-12); color: var(--fg-subtle); }
.field-error { font-size: var(--fs-12); color: var(--status-blocked); margin: 0; }
.key-readonly {
  font-size: var(--fs-13); padding: 7px 10px; width: max-content;
  background: var(--bg-sunken); border: 1px dashed var(--border-strong);
  border-radius: 6px; letter-spacing: 0.04em; color: var(--fg-muted);
}
.mono { font-family: var(--font-mono, ui-monospace, monospace); }
.muted { color: var(--fg-subtle); }

/* Member list */
.add-member-row {
  display: flex; align-items: center; gap: var(--s-3);
  padding: var(--s-3) var(--s-4);
  border-bottom: 1px solid var(--border);
}
.add-role-pick { display: flex; align-items: center; gap: 6px; font-size: var(--fs-13); }
.role-select { height: 32px; padding: 0 6px; cursor: pointer; width: auto; }
.menu-empty { padding: 8px 10px; font-size: var(--fs-12); color: var(--fg-subtle); }
.member-list { display: flex; flex-direction: column; }
.member-row { display: flex; align-items: center; gap: var(--s-3); padding: var(--s-3) var(--s-4); }
.member-row + .member-row { border-top: 1px solid var(--border); }
.member-info { flex: 1; min-width: 0; }
.member-name { font-size: var(--fs-13); font-weight: 500; }
.member-sub { font-size: var(--fs-12); }
.role-trigger {
  display: inline-flex; align-items: center; gap: 4px;
  background: none; border: none; cursor: pointer; padding: 2px;
  color: var(--fg-subtle); border-radius: 6px;
}
.role-trigger:hover { background: var(--bg-hover); }
.check-slot { display: inline-flex; width: 14px; flex-shrink: 0; }

.role-badge { font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 99px; letter-spacing: 0.02em; }
.role-owner  { background: color-mix(in oklab, var(--accent) 12%, var(--bg-panel)); color: var(--accent); }
.role-admin  { background: color-mix(in oklab, #7c3aed 10%, var(--bg-panel)); color: #7c3aed; }
.role-member { background: var(--bg-sunken); color: var(--fg-muted); }

/* Danger zone */
.settings-card.danger-zone { border-color: color-mix(in oklab, var(--status-blocked) 35%, var(--border)); }
.settings-card.danger-zone .settings-card-head {
  color: var(--status-blocked);
  border-bottom-color: color-mix(in oklab, var(--status-blocked) 20%, var(--border));
}
.danger-row { display: flex; align-items: center; gap: var(--s-4); padding: var(--s-4); }
.danger-text { flex: 1; min-width: 0; }
.danger-title { font-size: var(--fs-13); font-weight: 600; margin-bottom: 2px; }
.danger-row .muted { font-size: var(--fs-12); line-height: 1.5; }

/* Delete-confirmation banner */
.banner {
  display: flex; align-items: center; gap: var(--s-3);
  padding: var(--s-3) var(--s-4);
  background: var(--accent-soft); border: 1px solid var(--border);
  color: var(--fg); font-size: var(--fs-13); border-radius: var(--r-md);
}
.banner.warn {
  background: color-mix(in oklab, var(--status-progress) 12%, var(--bg-panel));
  border-color: color-mix(in oklab, var(--status-progress) 30%, var(--border));
}
.banner .icon { width: 16px; height: 16px; flex-shrink: 0; }
.banner .text { flex: 1; }

@media (max-width: 768px) {
  .danger-row { flex-direction: column; align-items: stretch; }
}
</style>
