<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import AppModal from '@/Components/UI/AppModal.vue'
import { Head, useForm, usePage, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { useToast } from '@/composables/useToast'
import {
    SunIcon, MoonIcon, MonitorIcon,
    LogoutIcon, CheckIcon,
} from '@/Components/UI/Icons.vue'

defineProps({
    mustVerifyEmail: { type: Boolean },
    status: { type: String },
})

const page = usePage()
const user = computed(() => page.props.auth.user)
const workspace = computed(() => page.props.workspace)
const { toast } = useToast()

const isOwner = computed(() =>
    workspace.value && workspace.value.owner_id === user.value?.id
)
const roleName = computed(() => isOwner.value ? 'Owner' : 'Member')

/* ── Profile ──────────────────────────────────────────────────── */
const AVATAR_COLORS = ['#4f46e5', '#0891b2', '#16a34a', '#7c3aed', '#d97706', '#dc2626', '#0d9488', '#0f766e']

const profileForm = useForm({
    name: user.value?.name ?? '',
    email: user.value?.email ?? '',
    avatar_color: user.value?.avatar_color || '#4f46e5',
})

const editingName = ref(false)
const nameDraft = ref(profileForm.name)

function initials(name) {
    return (name ?? '?').split(' ').filter(Boolean).map(w => w[0]).join('').slice(0, 2).toUpperCase()
}

function setAvatarColor(c) {
    profileForm.avatar_color = c
    profileForm.patch(route('profile.update'), {
        preserveScroll: true,
        onSuccess: () => toast('Avatar updated.'),
    })
}

function saveName() {
    profileForm.name = nameDraft.value
    profileForm.patch(route('profile.update'), {
        preserveScroll: true,
        onSuccess: () => {
            editingName.value = false
            toast('Display name updated.')
        },
    })
}

function cancelEditName() {
    editingName.value = false
    nameDraft.value = profileForm.name
}

/* ── Theme ───────────────────────────────────────────────────── */
const THEME_OPTS = [
    { id: 'light',  label: 'Light',  hint: 'Bright surfaces',          Icon: SunIcon },
    { id: 'dark',   label: 'Dark',   hint: 'Dim surfaces',             Icon: MoonIcon },
    { id: 'system', label: 'System', hint: 'Match your OS preference', Icon: MonitorIcon },
]

const theme = ref(user.value?.theme ?? 'system')

function setTheme(val) {
    theme.value = val
    router.patch(route('profile.theme'), { theme: val }, {
        preserveScroll: true,
        onSuccess: () => {
            if (val === 'dark') {
                document.documentElement.dataset.theme = 'dark'
            } else if (val === 'light') {
                document.documentElement.dataset.theme = ''
            } else {
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches
                document.documentElement.dataset.theme = prefersDark ? 'dark' : ''
            }
        },
    })
}

/* ── Password ─────────────────────────────────────────────────── */
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

/* ── Notifications ────────────────────────────────────────────── */
const NOTIF_KEY = 'taskline_notif_prefs'
const storedNotifs = (() => {
    try { return JSON.parse(localStorage.getItem(NOTIF_KEY) || '{}') } catch { return {} }
})()
const notifs = ref({
    task_assign:   storedNotifs.task_assign   ?? true,
    task_mention:  storedNotifs.task_mention  ?? true,
    task_complete: storedNotifs.task_complete ?? false,
    sprint_lock:   storedNotifs.sprint_lock   ?? false,
    member_join:   storedNotifs.member_join   ?? true,
})
const NOTIF_OPTS = [
    { id: 'task_assign',   label: 'Task assigned to me' },
    { id: 'task_mention',  label: 'Mentioned in a comment' },
    { id: 'task_complete', label: "Task I'm watching is completed" },
    { id: 'sprint_lock',   label: 'Sprint locked or unlocked' },
    { id: 'member_join',   label: 'New member joins the workspace' },
]

function saveNotifs() {
    localStorage.setItem(NOTIF_KEY, JSON.stringify(notifs.value))
    toast('Notification preferences saved.')
}

/* ── Delete account ───────────────────────────────────────────── */
const showDeleteModal = ref(false)
const deleteForm = useForm({ password: '' })

function confirmDelete() {
    deleteForm.delete(route('profile.destroy'), {
        onSuccess: () => { showDeleteModal.value = false },
    })
}

/* ── Sign out ─────────────────────────────────────────────────── */
function signOut() {
    router.post(route('logout'))
}
</script>

<template>
    <Head title="Profile settings" />

    <AppLayout>
        <div class="profile-page">
            <h2 class="page-title">Profile settings</h2>
            <p class="page-desc">Manage your account and preferences.</p>

            <!-- Appearance -->
            <div class="ps">
                <div class="ps-title">Appearance</div>
                <div class="theme-label">Theme</div>
                <div class="theme-grid" role="radiogroup" aria-label="Theme">
                    <button
                        v-for="opt in THEME_OPTS"
                        :key="opt.id"
                        type="button"
                        class="theme-card"
                        :class="{ 'theme-card--active': theme === opt.id }"
                        role="radio"
                        :aria-checked="theme === opt.id"
                        @click="setTheme(opt.id)"
                    >
                        <div class="theme-card-row">
                            <component
                                :is="opt.Icon"
                                :style="{ width: '15px', height: '15px', color: theme === opt.id ? 'var(--accent)' : 'var(--fg-muted)' }"
                            />
                            <span class="theme-card-label">{{ opt.label }}</span>
                            <span style="flex:1" />
                            <span
                                class="theme-radio"
                                :class="{ 'theme-radio--active': theme === opt.id }"
                                aria-hidden="true"
                            />
                        </div>
                        <span class="theme-card-hint">{{ opt.hint }}</span>
                    </button>
                </div>
                <p class="hint-text" style="margin-top:10px">System follows your operating system's light/dark setting and updates automatically.</p>
            </div>

            <!-- Your profile -->
            <div class="ps">
                <div class="ps-title">Your profile</div>

                <div class="avatar-row">
                    <div class="avatar-preview" :style="{ background: profileForm.avatar_color }">
                        {{ initials(profileForm.name) }}
                    </div>
                    <div>
                        <div class="field-label" style="margin-bottom:8px">Avatar color</div>
                        <div class="color-row">
                            <button
                                v-for="c in AVATAR_COLORS"
                                :key="c"
                                type="button"
                                class="color-chip"
                                :class="{ 'color-chip--active': profileForm.avatar_color === c }"
                                :style="{ background: c, '--c': c }"
                                @click="setAvatarColor(c)"
                            />
                        </div>
                    </div>
                </div>

                <div class="profile-grid">
                    <div class="field">
                        <label class="field-label">Display name</label>
                        <div v-if="editingName" class="inline-edit-row">
                            <input
                                class="field-input"
                                v-model="nameDraft"
                                autofocus
                                @keydown.enter="saveName"
                                @keydown.esc="cancelEditName"
                            />
                            <button
                                type="button"
                                class="btn-primary"
                                style="height:36px;padding:0 12px;font-size:13px"
                                :disabled="profileForm.processing"
                                @click="saveName"
                            >Save</button>
                        </div>
                        <div v-else class="field-display">
                            <span>{{ profileForm.name }}</span>
                            <button type="button" class="ghost-sm-btn" @click="editingName = true">Edit</button>
                        </div>
                    </div>
                    <div class="field">
                        <label class="field-label">Email</label>
                        <div class="field-display field-display--readonly">{{ profileForm.email }}</div>
                    </div>
                </div>

                <div class="field" style="margin-top:12px">
                    <label class="field-label">Role</label>
                    <div style="display:inline-flex;margin-top:4px">
                        <span class="role-badge" :class="'role-' + roleName.toLowerCase()">{{ roleName }}</span>
                    </div>
                </div>
            </div>

            <!-- Change password -->
            <div class="ps">
                <div class="ps-title">Change password</div>
                <form @submit.prevent="savePassword" style="display:flex;flex-direction:column;gap:12px">
                    <div class="field">
                        <label class="field-label">Current password</label>
                        <input
                            v-model="passwordForm.current_password"
                            type="password"
                            class="field-input"
                            :class="{ 'field-input--error': passwordForm.errors.current_password }"
                            placeholder="••••••••"
                            autocomplete="current-password"
                        />
                        <p v-if="passwordForm.errors.current_password" class="field-error">{{ passwordForm.errors.current_password }}</p>
                    </div>
                    <div class="profile-grid">
                        <div class="field">
                            <label class="field-label">New password</label>
                            <input
                                v-model="passwordForm.password"
                                type="password"
                                class="field-input"
                                :class="{ 'field-input--error': passwordForm.errors.password }"
                                placeholder="Min 8 characters"
                                autocomplete="new-password"
                            />
                            <p v-if="passwordForm.errors.password" class="field-error">{{ passwordForm.errors.password }}</p>
                        </div>
                        <div class="field">
                            <label class="field-label">Confirm new password</label>
                            <input
                                v-model="passwordForm.password_confirmation"
                                type="password"
                                class="field-input"
                                placeholder="Repeat"
                                autocomplete="new-password"
                            />
                        </div>
                    </div>
                    <div>
                        <button
                            type="submit"
                            class="btn-secondary"
                            :disabled="!passwordForm.current_password || !passwordForm.password || passwordForm.processing"
                        >
                            <template v-if="passwordForm.recentlySuccessful">
                                <CheckIcon style="width:13px;height:13px;color:var(--status-done)" />
                                Password updated
                            </template>
                            <template v-else>Update password</template>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Notification preferences -->
            <div class="ps">
                <div class="ps-title">Notification preferences</div>
                <div style="display:flex;flex-direction:column;gap:12px">
                    <label v-for="opt in NOTIF_OPTS" :key="opt.id" class="notif-row">
                        <div
                            class="notif-check"
                            :class="{ 'notif-check--on': notifs[opt.id] }"
                            @click="notifs[opt.id] = !notifs[opt.id]"
                        >
                            <CheckIcon v-if="notifs[opt.id]" style="width:10px;height:10px" />
                        </div>
                        <span>{{ opt.label }}</span>
                    </label>
                </div>
                <button type="button" class="btn-secondary" style="margin-top:16px" @click="saveNotifs">
                    Save preferences
                </button>
            </div>

            <!-- Account -->
            <div class="ps">
                <div class="ps-title">Account</div>

                <div class="account-card" style="margin-bottom:12px">
                    <div>
                        <div class="account-card-title">Sign out</div>
                        <p class="hint-text">End your session on this device. You can sign back in any time.</p>
                    </div>
                    <button
                        type="button"
                        class="btn-secondary"
                        style="height:32px;padding:0 12px;display:inline-flex;align-items:center;gap:6px;flex-shrink:0"
                        @click="signOut"
                    >
                        <LogoutIcon style="width:13px;height:13px" />
                        Sign out
                    </button>
                </div>

                <div class="account-card account-card--danger">
                    <div class="account-card-title account-card-title--danger">Delete account</div>
                    <p class="hint-text" style="margin-bottom:12px">Permanently deletes your account and removes you from all workspaces. This cannot be undone.</p>
                    <button type="button" class="btn-ghost-danger" @click="showDeleteModal = true">
                        Delete my account
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>

    <AppModal :show="showDeleteModal" title="Delete account?" @close="showDeleteModal = false">
        <p class="hint-text">
            Enter your password to permanently delete your account. All your data will be removed from every workspace.
        </p>
        <div class="field" style="margin-top:4px">
            <label class="field-label">Password</label>
            <input
                v-model="deleteForm.password"
                type="password"
                class="field-input"
                :class="{ 'field-input--error': deleteForm.errors.password }"
                placeholder="Your current password"
                autocomplete="current-password"
            />
            <p v-if="deleteForm.errors.password" class="field-error">{{ deleteForm.errors.password }}</p>
        </div>
        <template #footer>
            <button type="button" class="btn-ghost" @click="showDeleteModal = false">Cancel</button>
            <button
                type="button"
                class="btn-danger"
                :disabled="!deleteForm.password || deleteForm.processing"
                @click="confirmDelete"
            >
                Delete account
            </button>
        </template>
    </AppModal>
</template>

<style scoped>
/* ── Page ── */
.profile-page {
    max-width: 640px;
    margin: 0 auto;
    padding: 32px 40px 64px;
}

.page-title {
    font-size: 22px;
    font-weight: 700;
    color: var(--fg);
    margin: 0 0 4px;
}

.page-desc {
    font-size: 13px;
    color: var(--fg-muted);
    margin: 0 0 32px;
    line-height: 1.5;
}

/* ── Sections ── */
.ps { margin-bottom: 32px; }

.ps-title {
    font-weight: 600;
    font-size: 14px;
    color: var(--fg);
    margin-bottom: 16px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--border);
}

/* ── Theme ── */
.theme-label {
    font-size: 12px;
    font-weight: 500;
    color: var(--fg-muted);
    margin-bottom: 10px;
}

.theme-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
}

.theme-card {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 6px;
    text-align: left;
    cursor: pointer;
    padding: 12px 14px;
    border: 1px solid var(--border);
    background: var(--bg-panel);
    border-radius: 8px;
    color: var(--fg);
    box-shadow: none;
    font-family: inherit;
    transition: border-color 100ms, box-shadow 100ms, background 100ms;
}

.theme-card--active {
    border: 1.5px solid var(--accent);
    background: color-mix(in oklab, var(--accent) 6%, var(--bg-panel));
    box-shadow: 0 0 0 3px color-mix(in oklab, var(--accent) 15%, transparent);
}

.theme-card-row {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
}

.theme-card-label {
    font-size: 13px;
    font-weight: 600;
}

.theme-radio {
    width: 14px;
    height: 14px;
    border-radius: 50%;
    border: 1.5px solid var(--border-strong, var(--border));
    background: var(--bg-panel);
    flex-shrink: 0;
    transition: border 100ms;
}

.theme-radio--active { border: 4px solid var(--accent); }

.theme-card-hint {
    font-size: 11.5px;
    color: var(--fg-muted);
    line-height: 1.3;
}

/* ── Avatar ── */
.avatar-row {
    display: flex;
    align-items: center;
    gap: 20px;
    margin-bottom: 20px;
}

.avatar-preview {
    width: 64px;
    height: 64px;
    border-radius: 16px;
    color: #fff;
    display: grid;
    place-items: center;
    font-size: 22px;
    font-weight: 700;
    flex-shrink: 0;
    transition: background 150ms;
}

/* ── Profile grid ── */
.profile-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

/* ── Fields ── */
.field {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.field-label {
    font-size: 12px;
    font-weight: 500;
    color: var(--fg-muted);
}

.field-input {
    height: 36px;
    padding: 0 10px;
    border-radius: 6px;
    border: 1px solid var(--border);
    background: var(--bg-panel);
    color: var(--fg);
    font-size: 14px;
    font-family: inherit;
    outline: none;
    width: 100%;
    box-sizing: border-box;
    transition: border-color 120ms, box-shadow 120ms;
}

.field-input:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px var(--accent-soft);
}

.field-input--error { border-color: var(--status-blocked); }

.field-error {
    font-size: 12px;
    color: var(--status-blocked);
    margin: 0;
}

.field-display {
    display: flex;
    align-items: center;
    gap: 8px;
    height: 36px;
    padding: 0 10px;
    border: 1px solid var(--border);
    border-radius: 6px;
    background: var(--bg-sunken);
    font-size: 14px;
    color: var(--fg);
}

.field-display span { flex: 1; }
.field-display--readonly { color: var(--fg-muted); }

.inline-edit-row { display: flex; gap: 6px; }

/* ── Color chips ── */
.color-row { display: flex; gap: 8px; flex-wrap: wrap; }

.color-chip {
    width: 24px;
    height: 24px;
    border-radius: 6px;
    border: none;
    cursor: pointer;
    outline: 2px solid transparent;
    outline-offset: 2px;
    transition: outline-color 100ms, box-shadow 100ms, transform 80ms;
}

.color-chip:hover { transform: scale(1.1); }
.color-chip--active {
    outline-color: var(--c, currentColor);
    box-shadow: 0 0 0 4px color-mix(in oklab, var(--c, currentColor) 25%, transparent);
}

/* ── Role badge ── */
.role-badge {
    display: inline-flex;
    font-size: 11px;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 99px;
    letter-spacing: 0.02em;
}

.role-owner {
    background: color-mix(in oklab, var(--accent) 12%, var(--bg-panel));
    color: var(--accent);
}

.role-member {
    background: var(--bg-sunken);
    color: var(--fg-muted);
}

/* ── Hint text ── */
.hint-text {
    font-size: 13px;
    color: var(--fg-muted);
    line-height: 1.5;
    margin: 0;
}

/* ── Notifications ── */
.notif-row {
    display: flex;
    align-items: center;
    gap: 12px;
    cursor: pointer;
    font-size: 13px;
    user-select: none;
}

.notif-check {
    width: 16px;
    height: 16px;
    border-radius: 4px;
    border: 1.5px solid var(--border-strong, var(--border));
    background: transparent;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    cursor: pointer;
    color: #fff;
    transition: background 100ms, border-color 100ms;
}

.notif-check--on {
    background: var(--accent);
    border-color: var(--accent);
}

/* ── Account cards ── */
.account-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 16px 20px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--bg-panel);
}

.account-card--danger {
    flex-direction: column;
    align-items: flex-start;
    border-color: color-mix(in oklab, var(--status-blocked) 25%, var(--border));
    background: color-mix(in oklab, var(--status-blocked) 4%, var(--bg-panel));
}

.account-card-title {
    font-weight: 600;
    font-size: 13px;
    margin-bottom: 4px;
    color: var(--fg);
}

.account-card-title--danger { color: var(--status-blocked); }

/* ── Buttons ── */
.btn-primary {
    height: 36px;
    padding: 0 14px;
    border-radius: 6px;
    background: var(--accent);
    color: var(--accent-fg);
    border: none;
    font-size: 13px;
    font-weight: 500;
    font-family: inherit;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    white-space: nowrap;
    transition: background 80ms;
}
.btn-primary:hover:not(:disabled) { background: var(--accent-hover); }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }

.btn-secondary {
    height: 36px;
    padding: 0 14px;
    border-radius: 6px;
    background: var(--bg-panel);
    color: var(--fg);
    border: 1px solid var(--border);
    font-size: 13px;
    font-weight: 500;
    font-family: inherit;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    white-space: nowrap;
    transition: background 80ms, border-color 80ms;
}
.btn-secondary:hover:not(:disabled) { background: var(--bg-hover); }
.btn-secondary:disabled { opacity: 0.4; cursor: not-allowed; }

.btn-ghost {
    height: 36px;
    padding: 0 14px;
    border-radius: 6px;
    background: transparent;
    color: var(--fg-muted);
    border: 1px solid var(--border);
    font-size: 13px;
    font-weight: 500;
    font-family: inherit;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: background 80ms;
}
.btn-ghost:hover { background: var(--bg-hover); color: var(--fg); }

.btn-ghost-danger {
    height: 32px;
    padding: 0 12px;
    border-radius: 6px;
    background: transparent;
    color: var(--status-blocked);
    border: 1px solid color-mix(in oklab, var(--status-blocked) 35%, var(--border));
    font-size: 13px;
    font-weight: 500;
    font-family: inherit;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: background 80ms;
}
.btn-ghost-danger:hover { background: color-mix(in oklab, var(--status-blocked) 8%, var(--bg-panel)); }

.btn-danger {
    height: 36px;
    padding: 0 14px;
    border-radius: 6px;
    background: var(--status-blocked);
    color: #ffffff;
    border: none;
    font-size: 13px;
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

.ghost-sm-btn {
    padding: 0 6px;
    height: 24px;
    border-radius: 4px;
    background: none;
    border: 1px solid var(--border);
    color: var(--fg-muted);
    font-size: 11px;
    font-weight: 500;
    font-family: inherit;
    cursor: pointer;
    white-space: nowrap;
    flex-shrink: 0;
    transition: background 80ms, color 80ms;
}
.ghost-sm-btn:hover { background: var(--bg-hover); color: var(--fg); }
</style>
