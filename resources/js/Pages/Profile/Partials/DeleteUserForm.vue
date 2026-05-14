<script setup>
import AppModal from '@/Components/UI/AppModal.vue'
import { useForm } from '@inertiajs/vue3'
import { nextTick, ref } from 'vue'

const showModal = ref(false)
const passwordInput = ref(null)

const form = useForm({ password: '' })

function openModal() {
    showModal.value = true
    nextTick(() => passwordInput.value?.focus())
}

function closeModal() {
    showModal.value = false
    form.clearErrors()
    form.reset()
}

function deleteUser() {
    form.delete(route('profile.destroy'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: () => passwordInput.value?.focus(),
        onFinish: () => form.reset(),
    })
}
</script>

<template>
    <div>
        <button type="button" class="btn-danger" @click="openModal">
            Delete account
        </button>

        <AppModal :show="showModal" title="Delete account?" @close="closeModal">
            <p class="text-sm" style="color:var(--fg-muted)">
                This action is permanent and cannot be undone. All your projects, tasks,
                and data will be deleted. Enter your password to confirm.
            </p>

            <div class="form-field">
                <label class="field-label" for="delete-password">Your password</label>
                <input
                    id="delete-password"
                    ref="passwordInput"
                    v-model="form.password"
                    type="password"
                    class="field-input"
                    :class="{ 'field-input--error': form.errors.password }"
                    placeholder="Enter your password"
                    @keyup.enter="deleteUser"
                />
                <p v-if="form.errors.password" class="field-error">{{ form.errors.password }}</p>
            </div>

            <template #footer>
                <button type="button" class="btn-ghost" @click="closeModal">Cancel</button>
                <button
                    type="button"
                    class="btn-danger"
                    :disabled="form.processing || !form.password"
                    @click="deleteUser"
                >
                    <span v-if="form.processing" class="spinner" />
                    Yes, delete my account
                </button>
            </template>
        </AppModal>
    </div>
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
.btn-danger {
    height: 34px;
    padding: 0 14px;
    border-radius: 6px;
    background: var(--status-blocked);
    color: #fff;
    border: none;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: opacity 80ms;
}
.btn-danger:hover:not(:disabled) { opacity: 0.85; }
.btn-danger:disabled { opacity: 0.45; cursor: not-allowed; }
.btn-ghost {
    height: 34px;
    padding: 0 14px;
    border-radius: 6px;
    background: transparent;
    color: var(--fg-muted);
    border: 1px solid var(--border);
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: background 80ms;
}
.btn-ghost:hover { background: var(--bg-hover); color: var(--fg); }
@keyframes spin { to { transform: rotate(360deg); } }
.spinner { width: 13px; height: 13px; border: 2px solid rgba(255,255,255,0.35); border-top-color: #fff; border-radius: 50%; animation: spin 0.6s linear infinite; flex-shrink: 0; }
</style>
