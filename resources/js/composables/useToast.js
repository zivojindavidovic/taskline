import { ref } from 'vue'

const toasts = ref([])
let nextId = 0

export function useToast() {
  function toast(message, options = {}) {
    const id = ++nextId
    const item = { id, message, undo: options.undo ?? null }
    toasts.value.push(item)

    const delay = options.duration ?? 4000
    const timer = setTimeout(() => dismiss(id), delay)
    item._timer = timer
  }

  function dismiss(id) {
    const idx = toasts.value.findIndex(t => t.id === id)
    if (idx !== -1) {
      clearTimeout(toasts.value[idx]._timer)
      toasts.value.splice(idx, 1)
    }
  }

  function undo(item) {
    item.undo?.()
    dismiss(item.id)
  }

  return { toast, dismiss, undo, toasts }
}
