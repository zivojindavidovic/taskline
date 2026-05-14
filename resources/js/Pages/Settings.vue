<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import AppModal from '@/Components/UI/AppModal.vue'
import Avatar from '@/Components/UI/Avatar.vue'
import { Head, useForm, usePage, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { useToast } from '@/composables/useToast'

const props = defineProps({
    members: { type: Array, default: () => [] },
})

const page = usePage()
const workspace = computed(() => page.props.workspace)
const user = computed(() => page.props.auth.user)
const { toast } = useToast()

/* ── Sidebar nav ──────────────────────────────────────────── */
const activeSection = ref('general')

const navItems = computed(() => {
    const items = [
        { id: 'general', label: 'General' },
        { id: 'security', label: 'Security' },
    ]
    if (workspace.value) {
        items.push({ id: 'workspace', label: 'Workspace' })
        items.push({ id: 'notifications', label: 'Notifications' })
    }
    if (workspace.value && isOwner.value) {
        items.push({ id: 'danger', label: 'Danger zone' })
    }
    return items
})

/* ── Profile form ─────────────────────────────────────────── */
const profileForm = useForm({
    name: user.value?.name ?? '',
    email: user.value?.email ?? '',
})

function saveProfile() {
    profileForm.patch(route('profile.update'), {
        preserveScroll: true,
        onSuccess: () => toast('Profile updated.'),
    })
}

/* ── Password form ────────────────────────────────────────── */
const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
})

function savePassword() {
    passwordForm.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => {
            passwordForm.reset()
            toast('Password changed.')
        },
    })
}

/* ── Theme ───────────────────────────────────────────────── */
const storedTheme = localStorage.getItem('theme') ?? 'system'
const theme = ref(storedTheme)

function setTheme(val) {
    theme.value = val
    localStorage.setItem('theme', val)
    if (val === 'dark') {
        document.documentElement.dataset.theme = 'dark'
    } else if (val === 'light') {
        document.documentElement.dataset.theme = ''
    } else {
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches
        document.documentElement.dataset.theme = prefersDark ? 'dark' : ''
    }
}

/* ── Workspace form ──────────────────────────────────────── */
const wsForm = useForm({
    name:  workspace.value?.name  ?? '',
    color: workspace.value?.color ?? '#4f46e5',
})

function saveWorkspace() {
    wsForm.patch(route('settings.workspace.update'), {
        preserveScroll: true,
        onSuccess: () => toast('Workspace updated.'),
    })
}

/* ── Invite member ───────────────────────────────────────── */
const inviteForm = useForm({ email: '', role: 'member' })

function submitInvite() {
    inviteForm.post(route('settings.members.invite'), {
        preserveScroll: true,
        onSuccess: () => { inviteForm.reset(); toast('Member added.') },
    })
}

function updateMemberRole(memberId, role) {
    router.patch(route('settings.members.role', memberId), { role }, { preserveScroll: true })
}

function removeMember(member) {
    if (!confirm(`Remove ${member.name} from this workspace?`)) return
    router.delete(route('settings.members.remove', member.id), { preserveScroll: true })
}

/* ── Notifications (local-only, no backend) ──────────────── */
const notifications = ref({
    task_assigned: true,
    task_mentioned: true,
    task_completed: false,
    sprint_locked: false,
    member_joined: true,
})

function saveNotifications() {
    toast('Notification preferences saved.')
}

/* ── Delete account ──────────────────────────────────────── */
const showDeleteAccountModal = ref(false)
const deleteAccountForm = useForm({ password: '' })

function confirmDeleteAccount() {
    deleteAccountForm.delete(route('profile.destroy'), {
        onSuccess: () => { showDeleteAccountModal.value = false },
    })
}

/* ── Delete workspace ────────────────────────────────────── */
const deleteWsConfirm = ref('')

function confirmDeleteWorkspace() {
    if (deleteWsConfirm.value !== workspace.value?.name) return
    router.delete(route('settings.workspace.destroy'), {
        data: { confirmation: deleteWsConfirm.value },
        onSuccess: () => { deleteWsConfirm.value = '' },
    })
}

const WS_COLORS = ['#4f46e5', '#0891b2', '#16a34a', '#7c3aed', '#d97706', '#dc2626', '#0d9488', '#db2777']

const isOwner = computed(() =>
    workspace.value && workspace.value.owner_id === user.value?.id
)
</script>

<template>
    <Head title="Settings" />

    <AppLayout>
        <div class="settings-shell">
            <!-- Left sidebar nav -->
            <nav class="settings-nav">
                <div class="settings-nav-title">Settings</div>
                <button
                    v-for="item in navItems"
                    :key="item.id"
                    type="button"
                    class="settings-nav-item"
                    :class="{
                        'settings-nav-item--active': activeSection === item.id,
                        'settings-nav-item--danger': item.id === 'danger',
                    }"
                    @click="activeSection = item.id"
                >
                    {{ item.label }}
                </button>
            </nav>

            <!-- Right content area -->
            <div class="settings-content">

                <!-- ── General ──────────────────────────────────── -->
                <div v-if="activeSection === 'general'" class="settings-section">
                    <!-- Profile -->
                    <div class="section-group">
                        <div class="section-label">Profile</div>
                        <div class="section-card">
                            <form class="card-fields" @submit.prevent="saveProfile">
                                <div class="form-field">
                                    <label class="field-label">Full name</label>
                                    <input
                                        v-model="profileForm.name"
                                        type="text"
                                        class="field-input"
                                        :class="{ 'field-input--error': profileForm.errors.name }"
                                    />
                                    <p v-if="profileForm.errors.name" class="field-error">{{ profileForm.errors.name }}</p>
                                </div>
                                <div class="form-field">
                                    <label class="field-label">Email address</label>
                                    <input
                                        v-model="profileForm.email"
                                        type="email"
                                        class="field-input"
                                        :class="{ 'field-input--error': profileForm.errors.email }"
                                    />
                                    <p v-if="profileForm.errors.email" class="field-error">{{ profileForm.errors.email }}</p>
                                </div>
                                <div class="card-actions">
                                    <button type="submit" class="btn-primary" :disabled="profileForm.processing">
                                        Save changes
                                    </button>
                                    <span v-if="profileForm.recentlySuccessful" class="saved-label">Saved</span>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Appearance -->
                    <div class="section-group">
                        <div class="section-label">Appearance</div>
                        <div class="section-card">
                            <div class="form-field">
                                <label class="field-label">Color theme</label>
                                <div class="theme-row">
                                    <button
                                        v-for="opt in [
                                            { id: 'light',  label: 'Light'  },
                                            { id: 'dark',   label: 'Dark'   },
                                            { id: 'system', label: 'System' },
                                        ]"
                                        :key="opt.id"
                                        type="button"
                                        class="theme-btn"
                                        :class="{ 'theme-btn--active': theme === opt.id }"
                                        @click="setTheme(opt.id)"
                                    >
                                        <span class="theme-swatch" :class="`swatch-${opt.id}`" />
                                        {{ opt.label }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── Security ─────────────────────────────────── -->
                <div v-if="activeSection === 'security'" class="settings-section">
                    <div class="section-group">
                        <div class="section-label">Change password</div>
                        <div class="section-card">
                            <form class="card-fields" @submit.prevent="savePassword">
                                <div class="form-field">
                                    <label class="field-label">Current password</label>
                                    <input
                                        v-model="passwordForm.current_password"
                                        type="password"
                                        class="field-input"
                                        :class="{ 'field-input--error': passwordForm.errors.current_password }"
                                        autocomplete="current-password"
                                    />
                                    <p v-if="passwordForm.errors.current_password" class="field-error">{{ passwordForm.errors.current_password }}</p>
                                </div>
                                <div class="form-field">
                                    <label class="field-label">New password</label>
                                    <input
                                        v-model="passwordForm.password"
                                        type="password"
                                        class="field-input"
                                        :class="{ 'field-input--error': passwordForm.errors.password }"
                                        autocomplete="new-password"
                                    />
                                    <p v-if="passwordForm.errors.password" class="field-error">{{ passwordForm.errors.password }}</p>
                                </div>
                                <div class="form-field">
                                    <label class="field-label">Confirm new password</label>
                                    <input
                                        v-model="passwordForm.password_confirmation"
                                        type="password"
                                        class="field-input"
                                        autocomplete="new-password"
                                    />
                                </div>
                                <div class="card-actions">
                                    <button
                                        type="submit"
                                        class="btn-primary"
                                        :disabled="passwordForm.processing || !passwordForm.current_password || !passwordForm.password"
                                    >
                                        Update password
                                    </button>
                                    <span v-if="passwordForm.recentlySuccessful" class="saved-label">Password updated</span>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="section-group">
                        <div class="section-label">Sessions</div>
                        <div class="section-card">
                            <p class="hint-text">
                                You are currently signed in as <strong style="color:var(--fg)">{{ user.email }}</strong>.
                                If you suspect your account has been compromised, change your password above.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- ── Workspace ────────────────────────────────── -->
                <div v-if="activeSection === 'workspace' && workspace" class="settings-section">
                    <!-- Workspace settings -->
                    <div class="section-group">
                        <div class="section-label">Workspace details</div>
                        <div class="section-card">
                            <form class="card-fields" @submit.prevent="saveWorkspace">
                                <!-- Preview -->
                                <div class="preview-row">
                                    <div class="ws-avatar" :style="{ background: wsForm.color }">
                                        {{ wsForm.name?.[0]?.toUpperCase() ?? 'W' }}
                                    </div>
                                    <div>
                                        <div class="preview-name">{{ wsForm.name || 'Your workspace' }}</div>
                                        <div class="preview-meta">
                                            {{ props.members.length }} member{{ props.members.length !== 1 ? 's' : '' }}
                                        </div>
                                    </div>
                                </div>

                                <div v-if="isOwner" class="form-field">
                                    <label class="field-label">Workspace name</label>
                                    <input
                                        v-model="wsForm.name"
                                        type="text"
                                        class="field-input"
                                        :class="{ 'field-input--error': wsForm.errors.name }"
                                        placeholder="e.g. Acme Corp"
                                        maxlength="100"
                                        required
                                    />
                                    <p v-if="wsForm.errors.name" class="field-error">{{ wsForm.errors.name }}</p>
                                </div>

                                <div v-if="isOwner" class="form-field">
                                    <label class="field-label">Color</label>
                                    <div class="color-row">
                                        <button
                                            v-for="c in WS_COLORS"
                                            :key="c"
                                            type="button"
                                            class="color-chip"
                                            :class="{ 'color-chip--active': wsForm.color === c }"
                                            :style="{ background: c }"
                                            @click="wsForm.color = c"
                                        />
                                    </div>
                                </div>

                                <div v-if="isOwner" class="card-actions">
                                    <button type="submit" class="btn-primary" :disabled="wsForm.processing">
                                        Save workspace
                                    </button>
                                    <span v-if="wsForm.recentlySuccessful" class="saved-label">Saved</span>
                                </div>
                                <p v-else class="hint-text">Only the workspace owner can change these settings.</p>
                            </form>
                        </div>
                    </div>

                    <!-- Members -->
                    <div class="section-group">
                        <div class="section-label">Members</div>
                        <div class="section-card" style="padding:0">
                            <!-- Invite form -->
                            <div v-if="isOwner" class="invite-area">
                                <form class="invite-form" @submit.prevent="submitInvite">
                                    <input
                                        v-model="inviteForm.email"
                                        type="email"
                                        placeholder="colleague@company.com"
                                        class="field-input"
                                        :class="{ 'field-input--error': inviteForm.errors.email }"
                                        style="flex:1"
                                        required
                                    />
                                    <select v-model="inviteForm.role" class="field-input" style="width:110px">
                                        <option value="member">Member</option>
                                        <option value="admin">Admin</option>
                                    </select>
                                    <button type="submit" class="btn-primary" :disabled="inviteForm.processing">
                                        Invite
                                    </button>
                                </form>
                                <p v-if="inviteForm.errors.email" class="field-error" style="padding:0 20px 0">{{ inviteForm.errors.email }}</p>
                            </div>

                            <!-- Members list -->
                            <div v-for="m in props.members" :key="m.id" class="member-row">
                                <Avatar :name="m.name" size="md" />
                                <div class="member-info">
                                    <div class="member-name">{{ m.name }}</div>
                                    <div class="member-email">{{ m.email }}</div>
                                </div>

                                <div v-if="m.role === 'owner'" class="role-badge role-owner">Owner</div>
                                <template v-else-if="isOwner">
                                    <select
                                        :value="m.role"
                                        class="role-select"
                                        @change="updateMemberRole(m.id, $event.target.value)"
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
                </div>

                <!-- ── Notifications ────────────────────────────── -->
                <div v-if="activeSection === 'notifications'" class="settings-section">
                    <div class="section-group">
                        <div class="section-label">Email notifications</div>
                        <div class="section-card">
                            <div class="card-fields">
                                <label
                                    v-for="opt in [
                                        { key: 'task_assigned',   label: 'Task assigned to me' },
                                        { key: 'task_mentioned',  label: 'Mentioned in a comment' },
                                        { key: 'task_completed',  label: 'Task I\'m watching is completed' },
                                        { key: 'sprint_locked',   label: 'Sprint locked or unlocked' },
                                        { key: 'member_joined',   label: 'New member joins the workspace' },
                                    ]"
                                    :key="opt.key"
                                    class="notif-row"
                                >
                                    <div
                                        class="notif-check"
                                        :class="{ 'notif-check--on': notifications[opt.key] }"
                                        @click="notifications[opt.key] = !notifications[opt.key]"
                                    >
                                        <svg v-if="notifications[opt.key]" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                    </div>
                                    <span>{{ opt.label }}</span>
                                </label>
                                <div class="card-actions" style="margin-top:6px">
                                    <button type="button" class="btn-primary" @click="saveNotifications">
                                        Save preferences
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── Danger zone ──────────────────────────────── -->
                <div v-if="activeSection === 'danger' && workspace && isOwner" class="settings-section">
                    <!-- Delete workspace -->
                    <div class="section-group">
                        <div class="section-label">Delete workspace</div>
                        <div class="section-card section-card--danger">
                            <p class="hint-text" style="margin-bottom:14px">
                                Permanently delete <strong style="color:var(--fg)">{{ workspace.name }}</strong>
                                and all its projects, sprints, tasks, and member data. This cannot be undone.
                            </p>
                            <div class="form-field">
                                <label class="field-label">
                                    Type <strong style="color:var(--fg)">{{ workspace.name }}</strong> to confirm
                                </label>
                                <input
                                    v-model="deleteWsConfirm"
                                    type="text"
                                    class="field-input"
                                    :placeholder="workspace.name"
                                />
                            </div>
                            <div class="card-actions" style="margin-top:14px">
                                <button
                                    type="button"
                                    class="btn-danger"
                                    :disabled="deleteWsConfirm !== workspace.name"
                                    @click="confirmDeleteWorkspace"
                                >
                                    Delete workspace
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Delete account -->
                    <div class="section-group">
                        <div class="section-label">Delete account</div>
                        <div class="section-card section-card--danger">
                            <p class="hint-text" style="margin-bottom:14px">
                                Permanently delete your account and remove you from all workspaces. This cannot be undone.
                            </p>
                            <div class="card-actions">
                                <button
                                    type="button"
                                    class="btn-danger"
                                    @click="showDeleteAccountModal = true"
                                >
                                    Delete my account
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AppLayout>

    <!-- Delete account modal -->
    <AppModal :show="showDeleteAccountModal" title="Delete account?" @close="showDeleteAccountModal = false">
        <p class="hint-text">
            Enter your password to permanently delete your account. All your data will be removed from every workspace.
        </p>
        <div class="form-field" style="margin-top:4px">
            <label class="field-label">Password</label>
            <input
                v-model="deleteAccountForm.password"
                type="password"
                class="field-input"
                :class="{ 'field-input--error': deleteAccountForm.errors.password }"
                placeholder="Your current password"
                autocomplete="current-password"
            />
            <p v-if="deleteAccountForm.errors.password" class="field-error">{{ deleteAccountForm.errors.password }}</p>
        </div>
        <template #footer>
            <button type="button" class="btn-ghost" @click="showDeleteAccountModal = false">Cancel</button>
            <button
                type="button"
                class="btn-danger"
                :disabled="!deleteAccountForm.password || deleteAccountForm.processing"
                @click="confirmDeleteAccount"
            >
                Delete account
            </button>
        </template>
    </AppModal>
</template>

<style scoped>
/* ── Shell layout ── */
.settings-shell {
    display: flex;
    flex: 1;
    min-height: 0;
    overflow: hidden;
}

/* ── Left nav ── */
.settings-nav {
    width: 200px;
    flex-shrink: 0;
    padding: 32px 16px;
    border-right: 1px solid var(--border);
    background: var(--bg-app);
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.settings-nav-title {
    font-size: 13px;
    font-weight: 600;
    color: var(--fg);
    padding: 0 10px;
    margin-bottom: 16px;
}

.settings-nav-item {
    display: block;
    width: 100%;
    text-align: left;
    padding: 7px 10px;
    font-size: 13px;
    font-weight: 500;
    color: var(--fg-muted);
    border-radius: 6px;
    border: none;
    background: none;
    cursor: pointer;
    border-left: 2px solid transparent;
    transition: background 80ms, color 80ms;
}

.settings-nav-item:hover {
    background: var(--bg-hover);
    color: var(--fg);
}

.settings-nav-item--active {
    background: var(--accent-soft);
    color: var(--accent);
    border-left-color: var(--accent);
}

.settings-nav-item--danger {
    color: var(--fg-muted);
}

.settings-nav-item--danger.settings-nav-item--active {
    background: color-mix(in oklab, var(--status-blocked) 8%, var(--bg-panel));
    color: var(--status-blocked);
    border-left-color: var(--status-blocked);
}

/* ── Right content ── */
.settings-content {
    flex: 1;
    min-width: 0;
    overflow-y: auto;
    padding: 32px 40px 64px;
}

.settings-section {
    max-width: 640px;
    display: flex;
    flex-direction: column;
    gap: 24px;
}

/* ── Section groups ── */
.section-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.section-label {
    font-size: 13px;
    font-weight: 500;
    color: var(--fg-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding-left: 2px;
}

.section-card {
    background: var(--bg-panel);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 20px;
}

.section-card--danger {
    border-color: color-mix(in oklab, var(--status-blocked) 30%, var(--border));
}


/* ── Form fields ── */
.card-fields {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.form-field {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.field-label {
    font-size: 13px;
    font-weight: 500;
    color: var(--fg-muted);
}

.field-input {
    height: 40px;
    padding: 0 12px;
    border-radius: 6px;
    border: 1px solid var(--border);
    background: var(--bg-panel);
    color: var(--fg);
    font-size: 14px;
    font-family: inherit;
    outline: none;
    transition: border-color 120ms, box-shadow 120ms;
    width: 100%;
    box-sizing: border-box;
}

.field-input:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px var(--accent-soft);
}

.field-input--error {
    border-color: var(--status-blocked);
}

.field-error {
    font-size: 12px;
    color: var(--status-blocked);
    margin: 0;
}

.hint-text {
    font-size: 13px;
    color: var(--fg-muted);
    line-height: 1.5;
    margin: 0;
}

.card-actions {
    display: flex;
    align-items: center;
    gap: 12px;
}

.saved-label {
    font-size: 13px;
    font-weight: 500;
    color: #16a34a;
}

/* ── Buttons ── */
.btn-primary {
    height: 40px;
    padding: 0 16px;
    border-radius: 6px;
    background: var(--accent);
    color: var(--accent-fg);
    border: none;
    font-size: 14px;
    font-weight: 500;
    font-family: inherit;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: background 80ms;
    white-space: nowrap;
}

.btn-primary:hover:not(:disabled) { background: var(--accent-hover); }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }

.btn-danger {
    height: 40px;
    padding: 0 16px;
    border-radius: 6px;
    background: var(--status-blocked);
    color: #ffffff;
    border: none;
    font-size: 14px;
    font-weight: 500;
    font-family: inherit;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: opacity 80ms;
}

.btn-danger:hover:not(:disabled) { opacity: 0.85; }
.btn-danger:disabled { opacity: 0.4; cursor: not-allowed; }

.btn-ghost {
    height: 40px;
    padding: 0 16px;
    border-radius: 6px;
    background: transparent;
    color: var(--fg-muted);
    border: 1px solid var(--border);
    font-size: 14px;
    font-weight: 500;
    font-family: inherit;
    cursor: pointer;
    transition: background 80ms;
}

.btn-ghost:hover { background: var(--bg-hover); color: var(--fg); }

/* ── Theme switcher ── */
.theme-row {
    display: flex;
    gap: 8px;
}

.theme-btn {
    flex: 1;
    padding: 12px 0;
    border-radius: 8px;
    border: 1px solid var(--border);
    background: var(--bg-panel);
    color: var(--fg-muted);
    font-size: 13px;
    font-weight: 500;
    font-family: inherit;
    cursor: pointer;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    transition: background 80ms, border-color 80ms, color 80ms;
}

.theme-btn:hover { background: var(--bg-hover); color: var(--fg); }
.theme-btn--active {
    border-color: var(--accent);
    background: var(--accent-soft);
    color: var(--accent);
}

.theme-swatch {
    width: 40px;
    height: 26px;
    border-radius: 6px;
    border: 1px solid var(--border);
    display: block;
}

.swatch-light  { background: #fafaf9; }
.swatch-dark   { background: #18181a; }
.swatch-system { background: linear-gradient(135deg, #fafaf9 50%, #18181a 50%); }

/* ── Workspace preview ── */
.preview-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 14px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--bg-sunken);
}

.ws-avatar {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    color: #fff;
    font-size: 15px;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: background 150ms;
}

.preview-name {
    font-size: 14px;
    font-weight: 600;
    color: var(--fg);
}

.preview-meta {
    font-size: 12px;
    color: var(--fg-muted);
    margin-top: 1px;
}

/* ── Color chips ── */
.color-row {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.color-chip {
    width: 28px;
    height: 28px;
    border-radius: 7px;
    border: none;
    cursor: pointer;
    outline: 2px solid transparent;
    outline-offset: 2px;
    transition: outline-color 100ms, transform 80ms;
}

.color-chip:hover { transform: scale(1.1); }
.color-chip--active { outline-color: currentColor; }

/* ── Invite area ── */
.invite-area {
    padding: 16px 20px;
    border-bottom: 1px solid var(--border);
}

.invite-form {
    display: flex;
    gap: 8px;
    align-items: center;
}

/* ── Members list ── */
.member-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 20px;
    border-bottom: 1px solid var(--border);
}

.member-row:last-child { border-bottom: none; }

.member-info {
    flex: 1;
    min-width: 0;
}

.member-name {
    font-size: 14px;
    font-weight: 500;
    color: var(--fg);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.member-email {
    font-size: 12px;
    color: var(--fg-muted);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

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
    height: 32px;
    padding: 0 8px;
    border-radius: 6px;
    border: 1px solid var(--border);
    background: var(--bg-panel);
    color: var(--fg);
    font-size: 13px;
    font-family: inherit;
    cursor: pointer;
    outline: none;
}

.role-select:focus { border-color: var(--accent); }

.remove-btn {
    width: 32px;
    height: 32px;
    border-radius: 6px;
    border: none;
    background: transparent;
    color: var(--fg-muted);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 80ms, color 80ms;
}

.remove-btn:hover { background: #fee2e2; color: #dc2626; }

[data-theme="dark"] .remove-btn:hover { background: #7f1d1d33; }

/* ── Notification checkboxes ── */
.notif-row {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 14px;
    color: var(--fg);
    cursor: pointer;
    user-select: none;
}

.notif-check {
    width: 18px;
    height: 18px;
    border-radius: 4px;
    border: 1.5px solid var(--border-strong);
    background: transparent;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    cursor: pointer;
    transition: background 100ms, border-color 100ms;
    color: #fff;
}

.notif-check--on {
    background: var(--accent);
    border-color: var(--accent);
}
</style>
