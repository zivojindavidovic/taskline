<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue'
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue'
import DeleteUserForm from './Partials/DeleteUserForm.vue'
import { Head, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

defineProps({
    mustVerifyEmail: { type: Boolean },
    status: { type: String },
})

const page = usePage()
const user = computed(() => page.props.auth.user)
const workspace = computed(() => page.props.workspace)

const isOwner = computed(() =>
    workspace.value && workspace.value.owner_id === user.value.id
)
const roleName = computed(() => isOwner.value ? 'Owner' : 'Member')

const NOTIF_OPTS = [
    { id: 'task_assign',    label: 'Task assigned to me' },
    { id: 'task_mention',   label: 'Mentioned in a comment' },
    { id: 'task_complete',  label: 'Task I\'m watching is completed' },
    { id: 'sprint_lock',    label: 'Sprint locked or unlocked' },
    { id: 'member_join',    label: 'New member joins the workspace' },
]

const STORAGE_KEY = 'taskline_notif_prefs'
const storedNotifs = (() => {
    try { return JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}') } catch { return {} }
})()
const notifs = ref({
    task_assign: storedNotifs.task_assign ?? true,
    task_mention: storedNotifs.task_mention ?? true,
    task_complete: storedNotifs.task_complete ?? false,
    sprint_lock: storedNotifs.sprint_lock ?? false,
    member_join: storedNotifs.member_join ?? true,
})
const notifSaved = ref(false)

function saveNotifs() {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(notifs.value))
    notifSaved.value = true
    setTimeout(() => { notifSaved.value = false }, 2000)
}
</script>

<template>
    <Head title="Profile" />

    <AppLayout>
        <div class="profile-page">
            <h2 class="page-title">Profile settings</h2>
            <p class="page-desc">Manage your account and preferences.</p>

            <!-- Your profile -->
            <div class="profile-section">
                <div class="section-title">Your profile</div>
                <UpdateProfileInformationForm :must-verify-email="mustVerifyEmail" :status="status" />

                <div style="margin-top:12px">
                    <div class="field-label" style="margin-bottom:4px">Role</div>
                    <span class="role-badge" :class="'role-badge--' + roleName.toLowerCase()">{{ roleName }}</span>
                </div>
            </div>

            <!-- Change password -->
            <div class="profile-section">
                <div class="section-title">Change password</div>
                <UpdatePasswordForm />
            </div>

            <!-- Notification preferences -->
            <div class="profile-section">
                <div class="section-title">Notification preferences</div>
                <div class="notif-list">
                    <label
                        v-for="opt in NOTIF_OPTS"
                        :key="opt.id"
                        class="notif-item"
                    >
                        <div
                            class="checkbox"
                            :class="{ checked: notifs[opt.id] }"
                            @click="notifs[opt.id] = !notifs[opt.id]"
                        >
                            <svg v-if="notifs[opt.id]" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                        </div>
                        <span>{{ opt.label }}</span>
                    </label>
                </div>
                <div style="margin-top:16px;display:flex;align-items:center;gap:10px">
                    <button class="btn-secondary" @click="saveNotifs">Save preferences</button>
                    <Transition enter-active-class="fade-in" leave-active-class="fade-out">
                        <span v-if="notifSaved" class="saved-label">Saved!</span>
                    </Transition>
                </div>
            </div>

            <!-- Account / danger zone -->
            <div class="profile-section">
                <div class="section-title">Account</div>
                <div class="danger-box">
                    <div class="danger-box-title">Delete account</div>
                    <div class="danger-box-desc">
                        Permanently deletes your account and removes you from all workspaces. This cannot be undone.
                    </div>
                    <DeleteUserForm />
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.profile-page {
    max-width: 640px;
    margin: 0 auto;
    padding: 32px 24px 64px;
}
.page-title {
    font-size: 20px;
    font-weight: 600;
    color: var(--fg);
    margin: 0 0 4px;
}
.page-desc {
    font-size: 13px;
    color: var(--fg-muted);
    margin: 0 0 32px;
}
.profile-section {
    margin-bottom: 32px;
}
.section-title {
    font-size: 14px;
    font-weight: 600;
    color: var(--fg);
    margin-bottom: 16px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--border);
}
.field-label { font-size: 12px; font-weight: 500; color: var(--fg-muted); }
.role-badge {
    display: inline-flex;
    font-size: 11px;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 99px;
    letter-spacing: 0.02em;
}
.role-badge--owner {
    background: color-mix(in oklab, var(--accent) 12%, var(--bg-panel));
    color: var(--accent);
}
.role-badge--member {
    background: var(--bg-sunken);
    color: var(--fg-muted);
}
.notif-list { display: flex; flex-direction: column; gap: 12px; }
.notif-item { display: flex; align-items: center; gap: 12px; cursor: pointer; font-size: 13px; color: var(--fg); }
.checkbox {
    width: 16px;
    height: 16px;
    border-radius: 4px;
    border: 1.5px solid var(--border-strong, var(--border));
    background: transparent;
    color: #fff;
    display: grid;
    place-items: center;
    flex-shrink: 0;
    cursor: pointer;
    transition: background 100ms, border-color 100ms;
}
.checkbox.checked { background: var(--accent); border-color: var(--accent); }
.btn-secondary {
    height: 34px;
    padding: 0 14px;
    border-radius: 6px;
    background: transparent;
    color: var(--fg);
    border: 1px solid var(--border);
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    transition: background 80ms;
}
.btn-secondary:hover { background: var(--bg-hover); }
.saved-label { font-size: 12px; font-weight: 500; color: var(--status-done); }
.fade-in { animation: fadein 150ms ease; }
.fade-out { animation: fadeout 150ms ease; }
@keyframes fadein { from { opacity: 0 } to { opacity: 1 } }
@keyframes fadeout { from { opacity: 1 } to { opacity: 0 } }
.danger-box {
    padding: 16px 20px;
    border: 1px solid color-mix(in oklab, var(--status-blocked) 25%, var(--border));
    border-radius: 8px;
    background: color-mix(in oklab, var(--status-blocked) 4%, var(--bg-panel));
}
.danger-box-title {
    font-weight: 600;
    font-size: 13px;
    color: var(--status-blocked);
    margin-bottom: 4px;
}
.danger-box-desc {
    font-size: 13px;
    color: var(--fg-muted);
    margin-bottom: 12px;
}
</style>
