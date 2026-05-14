<script setup>
import { Link, useForm, usePage } from '@inertiajs/vue3'
import { ref } from 'vue'

defineProps({
    mustVerifyEmail: { type: Boolean },
    status: { type: String },
})

const user = usePage().props.auth.user

const AVATAR_COLORS = ['#4f46e5', '#0891b2', '#16a34a', '#7c3aed', '#d97706', '#dc2626', '#0d9488', '#0f766e']

const form = useForm({
    name: user.name,
    email: user.email,
    avatar_color: user.avatar_color || '#4f46e5',
})

const editingName = ref(false)
const nameInput = ref(null)

function startEditName() {
    editingName.value = true
    setTimeout(() => nameInput.value?.focus(), 0)
}

function initials(name) {
    return (name ?? '?').split(' ').filter(Boolean).map(w => w[0]).join('').slice(0, 2).toUpperCase()
}
</script>

<template>
    <form @submit.prevent="form.patch(route('profile.update'))">
        <!-- Avatar + color picker -->
        <div class="avatar-row">
            <div class="avatar-preview" :style="{ background: form.avatar_color }">
                {{ initials(form.name) }}
            </div>
            <div>
                <div class="field-label" style="margin-bottom:8px">Avatar color</div>
                <div class="color-swatches">
                    <button
                        v-for="c in AVATAR_COLORS"
                        :key="c"
                        type="button"
                        class="color-swatch"
                        :class="{ active: form.avatar_color === c }"
                        :style="{ background: c, '--c': c }"
                        @click="form.avatar_color = c"
                    />
                </div>
            </div>
        </div>

        <!-- Name + email grid -->
        <div class="fields-grid">
            <div class="form-field">
                <label class="field-label">Display name</label>
                <div v-if="!editingName" class="name-display">
                    <span>{{ form.name }}</span>
                    <button type="button" class="btn-edit" @click="startEditName">Edit</button>
                </div>
                <div v-else style="display:flex;gap:6px">
                    <input
                        ref="nameInput"
                        v-model="form.name"
                        type="text"
                        class="field-input"
                        :class="{ 'field-input--error': form.errors.name }"
                        style="flex:1"
                        @keydown.enter.prevent="editingName = false"
                        @keydown.escape="editingName = false"
                    />
                    <button type="button" class="btn-primary-sm" @click="editingName = false">Done</button>
                </div>
                <p v-if="form.errors.name" class="field-error">{{ form.errors.name }}</p>
            </div>

            <div class="form-field">
                <label class="field-label">Email</label>
                <div class="field-readonly">{{ form.email }}</div>
                <p v-if="form.errors.email" class="field-error">{{ form.errors.email }}</p>
            </div>
        </div>

        <!-- Email verification notice -->
        <div
            v-if="mustVerifyEmail && user.email_verified_at === null"
            class="notice notice--warn"
        >
            Your email is unverified.
            <Link :href="route('verification.send')" method="post" as="button" class="notice-link">
                Resend verification email
            </Link>
        </div>
        <div v-if="status === 'verification-link-sent'" class="notice notice--success">
            A new verification link has been sent to your email.
        </div>

        <!-- Save -->
        <div class="form-actions">
            <button type="submit" class="btn-primary" :disabled="form.processing">
                <span v-if="form.processing" class="spinner" />
                Save changes
            </button>
            <Transition enter-active-class="fade-enter" leave-active-class="fade-leave">
                <span v-if="form.recentlySuccessful" class="saved-label">Saved!</span>
            </Transition>
        </div>
    </form>
</template>

<style scoped>
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
.color-swatches {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
.color-swatch {
    width: 24px;
    height: 24px;
    border-radius: 6px;
    border: none;
    cursor: pointer;
    outline: none;
    transition: box-shadow 100ms;
}
.color-swatch.active {
    outline: 2px solid var(--c);
    outline-offset: 2px;
    box-shadow: 0 0 0 4px color-mix(in oklab, var(--c) 22%, transparent);
}
.fields-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-bottom: 12px;
}
.form-field { display: flex; flex-direction: column; gap: 5px; }
.field-label { font-size: 12px; font-weight: 500; color: var(--fg-muted); }
.name-display {
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
.name-display span { flex: 1; }
.btn-edit {
    padding: 0 6px;
    height: 24px;
    font-size: 11px;
    font-weight: 500;
    background: transparent;
    border: 1px solid var(--border);
    border-radius: 4px;
    color: var(--fg-muted);
    cursor: pointer;
    transition: background 80ms, color 80ms;
}
.btn-edit:hover { background: var(--bg-hover); color: var(--fg); }
.field-readonly {
    height: 36px;
    padding: 0 10px;
    border: 1px solid var(--border);
    border-radius: 6px;
    background: var(--bg-sunken);
    font-size: 14px;
    color: var(--fg-muted);
    display: flex;
    align-items: center;
}
.field-input {
    height: 36px;
    padding: 0 10px;
    border-radius: 6px;
    border: 1px solid var(--border);
    background: var(--bg-panel);
    color: var(--fg);
    font-size: 13px;
    outline: none;
    transition: border-color 120ms, box-shadow 120ms;
    width: 100%;
    box-sizing: border-box;
}
.field-input:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px var(--accent-soft);
}
.field-input--error { border-color: var(--status-blocked); }
.field-error { font-size: 11px; color: var(--status-blocked); }
.notice {
    font-size: 12px;
    padding: 10px 12px;
    border-radius: 6px;
    margin-bottom: 12px;
}
.notice--warn { background: var(--status-warn-bg); color: var(--status-warn-fg); }
.notice--success { background: var(--status-done-bg); color: var(--status-done-fg); }
.notice-link { font-weight: 500; text-decoration: underline; margin-left: 4px; background: none; border: none; cursor: pointer; }
.form-actions { display: flex; align-items: center; gap: 12px; margin-top: 4px; }
.btn-primary {
    height: 34px;
    padding: 0 14px;
    border-radius: 6px;
    background: var(--accent);
    color: var(--accent-fg);
    border: none;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: background 80ms;
}
.btn-primary:hover:not(:disabled) { background: var(--accent-hover); }
.btn-primary:disabled { opacity: 0.55; cursor: not-allowed; }
.btn-primary-sm {
    height: 36px;
    padding: 0 12px;
    border-radius: 6px;
    background: var(--accent);
    color: var(--accent-fg);
    border: none;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    flex-shrink: 0;
}
.saved-label { font-size: 12px; font-weight: 500; color: var(--status-done); }
.fade-enter { animation: fadein 150ms ease; }
.fade-leave { animation: fadeout 150ms ease; }
@keyframes fadein { from { opacity: 0 } to { opacity: 1 } }
@keyframes fadeout { from { opacity: 1 } to { opacity: 0 } }
@keyframes spin { to { transform: rotate(360deg); } }
.spinner { width: 13px; height: 13px; border: 2px solid rgba(255,255,255,0.35); border-top-color: #fff; border-radius: 50%; animation: spin 0.6s linear infinite; flex-shrink: 0; }
</style>
