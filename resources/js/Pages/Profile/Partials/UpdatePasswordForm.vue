<script setup>
import { useForm } from '@inertiajs/vue3'
import { ref } from 'vue'

const passwordInput = ref(null)
const currentPasswordInput = ref(null)

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
})

function updatePassword() {
    form.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: () => {
            if (form.errors.password) {
                form.reset('password', 'password_confirmation')
                passwordInput.value?.focus()
            }
            if (form.errors.current_password) {
                form.reset('current_password')
                currentPasswordInput.value?.focus()
            }
        },
    })
}
</script>

<template>
    <form @submit.prevent="updatePassword" class="flex flex-col gap-4">
        <div class="form-field">
            <label class="field-label" for="current_password">Current password</label>
            <input
                id="current_password"
                ref="currentPasswordInput"
                v-model="form.current_password"
                type="password"
                class="field-input"
                :class="{ 'field-input--error': form.errors.current_password }"
                autocomplete="current-password"
            />
            <p v-if="form.errors.current_password" class="field-error">{{ form.errors.current_password }}</p>
        </div>

        <div class="pw-grid">
            <div class="form-field">
                <label class="field-label" for="password">New password</label>
                <input
                    id="password"
                    ref="passwordInput"
                    v-model="form.password"
                    type="password"
                    class="field-input"
                    :class="{ 'field-input--error': form.errors.password }"
                    placeholder="Min 8 characters"
                    autocomplete="new-password"
                />
                <p v-if="form.errors.password" class="field-error">{{ form.errors.password }}</p>
            </div>

            <div class="form-field">
                <label class="field-label" for="password_confirmation">Confirm new password</label>
                <input
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    class="field-input"
                    :class="{ 'field-input--error': form.errors.password_confirmation }"
                    placeholder="Repeat"
                    autocomplete="new-password"
                />
                <p v-if="form.errors.password_confirmation" class="field-error">{{ form.errors.password_confirmation }}</p>
            </div>
        </div>

        <div class="flex items-center gap-3 pt-1">
            <button type="submit" class="btn-primary" :disabled="form.processing">
                <span v-if="form.processing" class="spinner" />
                Update password
            </button>
            <Transition
                enter-active-class="transition-opacity duration-150"
                enter-from-class="opacity-0"
                leave-active-class="transition-opacity duration-150"
                leave-to-class="opacity-0"
            >
                <span v-if="form.recentlySuccessful" class="text-xs font-medium" style="color:var(--status-done)">Saved!</span>
            </Transition>
        </div>
    </form>
</template>

<style scoped>
.form-field { display: flex; flex-direction: column; gap: 5px; }
.field-label { font-size: 12px; font-weight: 500; color: var(--fg-muted); }
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
}
.field-input:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px var(--accent-soft);
}
.field-input--error { border-color: var(--status-blocked); }
.field-error { font-size: 11px; color: var(--status-blocked); }
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
.pw-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
@keyframes spin { to { transform: rotate(360deg); } }
.spinner { width: 13px; height: 13px; border: 2px solid rgba(255,255,255,0.35); border-top-color: #fff; border-radius: 50%; animation: spin 0.6s linear infinite; flex-shrink: 0; }
</style>
